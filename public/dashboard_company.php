<?php
require_once '../app/config.php';
$pdo = connectDB();
checkAuth('company'); 

$companyUserId = $_SESSION['user_id'];
$companyUsername = $_SESSION['username'];
$companyInfo = [];
$fleet = [];
$schedules = [];
$routesList = [];

try {
    $stmt = $pdo->prepare("SELECT id, company_name, status FROM companies WHERE user_id = ?");
    $stmt->execute([$companyUserId]);
    $companyInfo = $stmt->fetch();
    
    if (!$companyInfo || $companyInfo['status'] !== 'Active') {
        $_SESSION['error'] = "Your company account is currently **" . htmlspecialchars($companyInfo['status'] ?? 'Pending') . "**. Access to management features is restricted.";
        header('Location: ../public/index.php');
        exit();
    }
    $companyId = $companyInfo['id'];
} catch (\PDOException $e) {
    error_log("Company lookup error: " . $e->getMessage());
    $_SESSION['error'] = "System error: Could not load company profile.";
    header('Location: ../public/index.php');
    exit();
}

$totalFleet = $pdo->query("SELECT COUNT(id) FROM buses WHERE company_id = $companyId")->fetchColumn();
$todayDate = date('Y-m-d');
$todayTicketsSold = $pdo->query("
    SELECT COUNT(b.id) 
    FROM bookings b
    JOIN schedules sch ON b.schedule_id = sch.id
    JOIN buses bus ON sch.bus_id = bus.id
    WHERE bus.company_id = $companyId AND DATE(b.booking_time) = '$todayDate'
")->fetchColumn();

$todayRevenue = $pdo->query("
    SELECT SUM(sch.price) 
    FROM bookings b
    JOIN schedules sch ON b.schedule_id = sch.id
    JOIN buses bus ON sch.bus_id = bus.id
    WHERE bus.company_id = $companyId AND DATE(b.booking_time) = '$todayDate'
")->fetchColumn() ?? 0;

$fleet = $pdo->query("SELECT id, bus_name, license_plate, total_seats FROM buses WHERE company_id = $companyId ORDER BY bus_name")->fetchAll();
$schedules = $pdo->query("
    SELECT 
        sch.id, sch.departure_time, sch.price, 
        r.departure_city, r.arrival_city, b.bus_name
    FROM schedules sch
    JOIN buses b ON sch.bus_id = b.id
    JOIN routes r ON sch.route_id = r.id
    WHERE b.company_id = $companyId
    ORDER BY sch.departure_time DESC
")->fetchAll();
$routesList = $pdo->query("SELECT id, departure_city, arrival_city FROM routes ORDER BY departure_city")->fetchAll();

$message = ''; $messageType = '';
if (isset($_SESSION['error'])) {
    $message = $_SESSION['error']; $messageType = 'bg-red-100 text-red-700 border-red-400';
    unset($_SESSION['error']);
} elseif (isset($_SESSION['success'])) {
    $message = $_SESSION['success']; $messageType = 'bg-green-100 text-green-700 border-green-400';
    unset($_SESSION['success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($companyInfo['company_name']); ?> Transport Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">

    <header class="bg-teal-700 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center h-16">
            <h1 class="text-2xl font-bold text-white">
                <i class="fas fa-bus mr-2"></i> <?php echo htmlspecialchars($companyInfo['company_name']); ?>
            </h1>
            <div class="flex items-center space-x-4">
                <span class="text-white text-sm font-light">Role: Company</span>
                <a href="../app/Auth.php?action=logout" class="text-white bg-teal-800 hover:bg-teal-900 px-3 py-1 rounded-md text-sm font-medium transition duration-150">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-8 sm:px-6 lg:px-8">
        
        <?php if ($message): ?>
        <div class="p-3 mb-6 rounded-lg border text-sm <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <h2 class="text-2xl font-semibold text-gray-800 mb-5">Real-Time Performance</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
            
            <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-teal-500">
                <p class="text-sm font-medium text-gray-500 uppercase">Total Fleet</p>
                <p class="text-3xl font-extrabold text-gray-900 mt-1"><?php echo $totalFleet; ?></p>
                <p class="text-xs text-gray-400">Number of buses in the system</p>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-indigo-500">
                <p class="text-sm font-medium text-gray-500 uppercase">Tickets Sold Today</p>
                <p class="text-3xl font-extrabold text-gray-900 mt-1"><?php echo $todayTicketsSold; ?></p>
                <p class="text-xs text-gray-400">Updated information about tickets bought</p>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-green-500">
                <p class="text-sm font-medium text-gray-500 uppercase">Revenue (Today)</p>
                <p class="text-3xl font-extrabold text-green-600 mt-1">RWF <?php echo number_format($todayRevenue); ?></p>
                <p class="text-xs text-gray-400">Tickets bought by clients today</p>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-yellow-500">
                <p class="text-sm font-medium text-gray-500 uppercase">Ongoing Bookings</p>
                <p class="text-3xl font-extrabold text-gray-900 mt-1">0</p>
                <p class="text-xs text-gray-400">Pending online reservations</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-xl border border-gray-200 h-fit">
                <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
                    <i class="fas fa-plus-circle mr-2 text-teal-600"></i> Add New Bus
                </h3>
                
                <form action="../app/CompanyManager.php" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_bus">
                    <input type="hidden" name="company_id" value="<?php echo htmlspecialchars($companyId); ?>">
                    
                    <div>
                        <label for="bus_name" class="block text-sm font-medium text-gray-700">Bus Name (e.g., Kigali Express)</label>
                        <input type="text" id="bus_name" name="bus_name" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                    </div>
                    
                    <div>
                        <label for="license_plate" class="block text-sm font-medium text-gray-700">License Plate (e.g., RAB123A)</label>
                        <input type="text" id="license_plate" name="license_plate" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                    </div>
                    
                    <div>
                        <label for="total_seats" class="block text-sm font-medium text-gray-700">Total Seats</label>
                        <input type="number" id="total_seats" name="total_seats" min="15" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                    </div>

                    <button type="submit" class="w-full bg-teal-600 text-white py-2 px-4 rounded-md hover:bg-teal-700 transition duration-150">
                        <i class="fas fa-check-circle mr-2"></i> Register Bus
                    </button>
                </form>
            </div>
            
            <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-xl border border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
                    <i class="fas fa-truck-moving mr-2 text-indigo-600"></i> Current Fleet
                </h3>
                
                <?php if (count($fleet) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($fleet as $bus): ?>
                            <div class="border rounded-lg p-4 bg-gray-50 shadow-sm flex justify-between items-center">
                                <div>
                                    <p class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($bus['bus_name']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($bus['license_plate']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xl font-extrabold text-indigo-600"><?php echo htmlspecialchars($bus['total_seats']); ?></p>
                                    <p class="text-xs text-gray-500">Seats</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center py-6 text-gray-500 border-dashed border-2 rounded-lg">No buses in your fleet. Add one using the form on the left!</p>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-3 bg-white p-6 rounded-xl shadow-xl border border-gray-200 mt-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
                    <i class="fas fa-calendar-plus mr-2 text-blue-600"></i> Create New Schedule
                </h3>
                
                <form action="../app/CompanyManager.php" method="POST" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <input type="hidden" name="action" value="add_schedule">
                    
                    <div class="col-span-full md:col-span-2">
                        <label for="bus_id" class="block text-sm font-medium text-gray-700">Select Bus</label>
                        <select id="bus_id" name="bus_id" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 bg-white" <?php echo (count($fleet) == 0) ? 'disabled' : ''; ?>>
                            <option value="">-- Choose Bus --</option>
                            <?php foreach ($fleet as $bus): ?>
                                <option value="<?php echo htmlspecialchars($bus['id']); ?>"><?php echo htmlspecialchars($bus['bus_name']); ?> (<?php echo htmlspecialchars($bus['license_plate']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (count($fleet) == 0): ?>
                            <p class="text-xs text-red-500 mt-1">You must add a bus first!</p>
                        <?php endif; ?>
                    </div>

                    <div class="col-span-full md:col-span-2">
                        <label for="route_id" class="block text-sm font-medium text-gray-700">Route (Departure &rarr; Arrival)</label>
                        <select id="route_id" name="route_id" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 bg-white">
                            <option value="">-- Select Route --</option>
                            <?php foreach ($routesList as $route): ?>
                                <option value="<?php echo htmlspecialchars($route['id']); ?>">
                                    <?php echo htmlspecialchars($route['departure_city']); ?> &rarr; <?php echo htmlspecialchars($route['arrival_city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-span-3 md:col-span-1">
                        <label for="departure_time" class="block text-sm font-medium text-gray-700">Departure Time</label>
                        <input type="time" id="departure_time" name="departure_time" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                    </div>
                    
                    <div class="col-span-3 md:col-span-1">
                        <label for="price" class="block text-sm font-medium text-gray-700">Price (RWF)</label>
                        <input type="number" id="price" name="price" min="100" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                    </div>
                    
                    <div class="col-span-full">
                        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-150" <?php echo (count($fleet) == 0) ? 'disabled' : ''; ?>>
                            <i class="fas fa-calendar-alt mr-2"></i> Publish Schedule
                        </button>
                    </div>
                </form>
            </div>

            <div class="lg:col-span-3 bg-white p-6 rounded-xl shadow-xl border border-gray-200 mt-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
                    <i class="fas fa-list-alt mr-2 text-gray-600"></i> Published Schedules
                </h3>
                
                <?php if (count($schedules) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bus</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Route</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($schedules as $sch): ?>
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($sch['bus_name']); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($sch['departure_city']); ?> &rarr; <?php echo htmlspecialchars($sch['arrival_city']); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-teal-600"><?php echo date('h:i A', strtotime($sch['departure_time'])); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm font-bold text-green-600">RWF <?php echo number_format($sch['price']); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm font-medium">
                                        <a href="../app/CompanyManager.php?action=delete_schedule&id=<?php echo htmlspecialchars($sch['id']); ?>" class="text-red-600 hover:text-red-900 ml-3">Delete</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center py-6 text-gray-500 border-dashed border-2 rounded-lg">No schedules published yet.</p>
                <?php endif; ?>
            </div>

        </div>
    </main>
</body>
</html>