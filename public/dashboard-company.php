<?php
// /public/dashboard_company.php
require_once '../app/config.php';
$pdo = connectDB();
// Enforce security: only logged-in companies can view this page
checkAuth('company'); 

$companyUserId = $_SESSION['user_id'];
$companyName = 'Transport Company'; // Default name

// Fetch Company ID and Name
$stmt = $pdo->prepare("SELECT id, company_name FROM companies WHERE user_id = ?");
$stmt->execute([$companyUserId]);
$company = $stmt->fetch();
$companyId = $company['id'];
if ($company) {
    $companyName = $company['company_name'];
}

// Fetch Bus Fleet (for Manage Fleet tab)
$fleet = [];
$stmt = $pdo->prepare("SELECT id, bus_name, total_seats FROM buses WHERE company_id = ?");
$stmt->execute([$companyId]);
$fleet = $stmt->fetchAll();

// Fetch Routes for the Schedule Form
$routes = $pdo->query("SELECT id, departure_city, arrival_city FROM routes")->fetchAll();

// Fetch Published Schedules (for Manage Schedules tab)
$schedules = [];
$stmt = $pdo->prepare("
    SELECT 
        sch.id, sch.departure_time, sch.price, b.bus_name, b.total_seats, 
        r.departure_city, r.arrival_city,
        (SELECT COUNT(*) FROM bookings WHERE schedule_id = sch.id) AS tickets_sold
    FROM schedules sch
    JOIN buses b ON sch.bus_id = b.id
    JOIN routes r ON sch.route_id = r.id
    WHERE b.company_id = ?
");
$stmt->execute([$companyId]);
$schedules = $stmt->fetchAll();

// KPI Data (Simple simulation, complex logic would be added later)
$totalFleet = count($fleet);
$totalTicketsSoldToday = 0; // Requires complex date query
$totalRevenueToday = 0; // Requires complex JOIN and SUM query
?>

<!DOCTYPE html>
<html lang="en">
<body class="bg-gray-100">

    <header class="bg-red-600 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center h-16">
            <h1 class="text-2xl font-bold text-white">
                <i class="fas fa-industry mr-2"></i> <?php echo htmlspecialchars($companyName); ?> Portal
            </h1>
            <div class="flex items-center space-x-4">
                <span class="text-white text-sm">Role: <strong class="capitalize">Company</strong></span>
                <a href="../app/Auth.php?action=logout" class="text-white bg-red-700 hover:bg-red-800 px-3 py-1 rounded-md text-sm font-medium transition duration-150">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="bg-white shadow-xl rounded-lg p-6">
            
            <div id="overview" class="tab-content block">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Real-Time Performance</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-lg shadow border-l-4 border-red-500">
                        <p class="text-sm font-medium text-gray-500">Total Fleet</p>
                        <p class="text-3xl font-extrabold text-gray-900 mt-1"><?php echo $totalFleet; ?></p>
                        <p class="text-xs text-gray-400 mt-1">Number of buses in the system</p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
                        <p class="text-sm font-medium text-gray-500">Tickets Sold Today</p>
                        <p class="text-3xl font-extrabold text-green-600 mt-1"><?php echo number_format($totalTicketsSoldToday); ?></p>
                        <p class="text-xs text-gray-400 mt-1">Updated information about tickets bought</p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
                        <p class="text-sm font-medium text-gray-500">Revenue (Today)</p>
                        <p class="text-3xl font-extrabold text-blue-600 mt-1">RWF <?php echo number_format($totalRevenueToday); ?></p>
                        <p class="text-xs text-gray-400 mt-1">How much tickets bought by clients</p>
                    </div>
                </div>

                <h3 class="text-xl font-semibold text-gray-800 mb-4">Ongoing Online Bookings</h3>
                </div>

            <div id="manage-fleet" class="tab-content">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Add New Bus</h2>
                
                <form action="../app/BusManager.php" method="POST" enctype="multipart/form-data" class="space-y-4 p-6 border rounded-lg bg-red-50 mb-8">
                    </form>

                <h3 class="text-xl font-semibold text-gray-800 mb-4">Current Fleet</h3>
                <div id="current-buses-list" class="space-y-4">
                    <?php if (count($fleet) > 0): ?>
                        <?php foreach ($fleet as $bus): ?>
                            <div class="flex items-center justify-between bg-white p-4 rounded-lg shadow border border-gray-200">
                                <div class="flex items-center space-x-3">
                                    <img src="assets/<?php echo htmlspecialchars($bus['image_url'] ?? 'default_bus.png'); ?>" alt="Bus <?php echo htmlspecialchars($bus['bus_name']); ?>" class="w-16 h-10 object-cover rounded">
                                    <div>
                                        <p class="text-lg font-semibold text-gray-900">Bus: <?php echo htmlspecialchars($bus['bus_name']); ?></p>
                                        <p class="text-sm text-gray-500">Total Seats: <?php echo htmlspecialchars($bus['total_seats']); ?></p>
                                    </div>
                                </div>
                                <button class="text-sm text-red-600 hover:text-red-800"><i class="fas fa-trash-alt mr-1"></i> Remove</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-gray-500">No buses in your fleet. Add one above!</p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="manage-schedule" class="tab-content">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Create New Schedule</h2>

                <form action="../app/ScheduleManager.php" method="POST" class="space-y-4 p-6 border rounded-lg bg-red-50 mb-8">
                    <input type="hidden" name="action" value="add_schedule">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="schedule-bus" class="block text-sm font-medium text-gray-700">Select Bus</label>
                            <select id="schedule-bus" name="bus_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                                <?php foreach ($fleet as $bus): ?>
                                    <option value="<?php echo $bus['id']; ?>">
                                        <?php echo htmlspecialchars($bus['bus_name']); ?> (<?php echo $bus['total_seats']; ?> Seats)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="route" class="block text-sm font-medium text-gray-700">Route (Departure <i class="fas fa-arrow-right"></i> Arrival)</label>
                            <select id="route" name="route_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                                <?php foreach ($routes as $route): ?>
                                    <option value="<?php echo $route['id']; ?>">
                                        <?php echo htmlspecialchars($route['departure_city']); ?> -> <?php echo htmlspecialchars($route['arrival_city']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        </div>
                </form>

                <h3 class="text-xl font-semibold text-gray-800 mb-4">Published Schedules</h3>
                <div class="overflow-x-auto border rounded-lg shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <tbody id="published-schedules-body" class="bg-white divide-y divide-gray-200">
                            <?php if (count($schedules) > 0): ?>
                                <?php foreach ($schedules as $sch): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($sch['bus_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($sch['departure_city']); ?> -> <?php echo htmlspecialchars($sch['arrival_city']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-red-600"><?php echo date('h:i A', strtotime(htmlspecialchars($sch['departure_time']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600"><?php echo number_format($sch['price']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <?php echo htmlspecialchars($sch['tickets_sold']); ?> / <?php echo htmlspecialchars($sch['total_seats']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No schedules published yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
    </body>
</html>