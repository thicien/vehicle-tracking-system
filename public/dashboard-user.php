<?php
// /public/dashboard_user.php
require_once '../app/config.php';
$pdo = connectDB();
// Enforce security: only logged-in users can view this page
checkAuth('user'); 

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$tickets = [];
$schedules = [];
$routesList = $pdo->query("SELECT DISTINCT departure_city FROM routes UNION SELECT DISTINCT arrival_city FROM routes")->fetchAll(PDO::FETCH_COLUMN);

// --- 1. Fetch User's Tickets ---
try {
    $stmt = $pdo->prepare("
        SELECT 
            b.id AS booking_id, c.company_name, sch.departure_time, 
            r.departure_city, r.arrival_city, b.seat_number, b.status, bus.bus_name, sch.id AS schedule_id
        FROM bookings b
        JOIN schedules sch ON b.schedule_id = sch.id
        JOIN buses bus ON sch.bus_id = bus.id
        JOIN companies c ON bus.company_id = c.id
        JOIN routes r ON sch.route_id = r.id
        WHERE b.user_id = ?
        ORDER BY b.booking_time DESC
    ");
    $stmt->execute([$userId]);
    $tickets = $stmt->fetchAll();
} catch (\PDOException $e) {
    error_log("Error fetching user tickets: " . $e->getMessage());
    // Optionally set an error message for the user:
    $_SESSION['error'] = "Could not load your bookings. Database error.";
}

// --- 2. Handle Schedule Search ---
$departure = filter_input(INPUT_GET, 'departure_city', FILTER_SANITIZE_SPECIAL_CHARS);
$arrival = filter_input(INPUT_GET, 'arrival_city', FILTER_SANITIZE_SPECIAL_CHARS);
$searchDate = filter_input(INPUT_GET, 'travel_date', FILTER_SANITIZE_SPECIAL_CHARS) ?? date('Y-m-d');
$searchPerformed = !empty($departure) || !empty($arrival);

if ($searchPerformed) {
    $searchQuery = "
        SELECT 
            sch.id AS schedule_id, sch.departure_time, sch.price, 
            r.departure_city, r.arrival_city, c.company_name, b.bus_name, b.total_seats,
            (SELECT COUNT(id) FROM bookings WHERE schedule_id = sch.id) AS booked_seats
        FROM schedules sch
        JOIN routes r ON sch.route_id = r.id
        JOIN buses b ON sch.bus_id = b.id
        JOIN companies c ON b.company_id = c.id
        WHERE DATE(sch.departure_time) = ?
    ";

    $params = [$searchDate];
    
    if (!empty($departure)) {
        $searchQuery .= " AND r.departure_city = ?";
        $params[] = $departure;
    }
    if (!empty($arrival)) {
        $searchQuery .= " AND r.arrival_city = ?";
        $params[] = $arrival;
    }
    $searchQuery .= " ORDER BY sch.departure_time ASC";

    try {
        $stmt = $pdo->prepare($searchQuery);
        $stmt->execute($params);
        $schedules = $stmt->fetchAll();
    } catch (\PDOException $e) {
        error_log("Error fetching schedules: " . $e->getMessage());
        $_SESSION['error'] = "Could not perform search. Database error.";
    }
}

// --- 3. Message Handling (from index.php logic) ---
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
    <title>User Dashboard | Ticket System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

    <header class="bg-indigo-600 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center h-16">
            <h1 class="text-2xl font-bold text-white">
                <i class="fas fa-ticket-alt mr-2"></i> Passenger Portal
            </h1>
            <div class="flex items-center space-x-4">
                <span class="text-white text-sm">Welcome, <strong class="capitalize"><?php echo htmlspecialchars($username); ?></strong></span>
                <a href="../app/Auth.php?action=logout" class="text-white bg-indigo-700 hover:bg-indigo-800 px-3 py-1 rounded-md text-sm font-medium transition duration-150">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="bg-white shadow-xl rounded-lg p-6">
            
            <?php if ($message): ?>
            <div class="p-3 mb-4 rounded-md border text-sm <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <button id="tab-search" class="tab-button py-4 px-1 text-center border-b-2 font-medium text-sm text-indigo-600 border-indigo-500" data-target="content-search">
                        <i class="fas fa-search mr-2"></i> Search & Book
                    </button>
                    <button id="tab-tickets" class="tab-button py-4 px-1 text-center border-b-2 font-medium text-sm text-gray-500 border-transparent hover:border-gray-300" data-target="content-tickets">
                        <i class="fas fa-receipt mr-2"></i> My Tickets
                    </button>
                </nav>
            </div>

            <div id="content-search" class="tab-content pt-6 block">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Find Your Bus</h2>
                
                <form action="dashboard_user.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 p-6 border rounded-lg bg-indigo-50 mb-8">
                    
                    <div>
                        <label for="departure-city" class="block text-sm font-medium text-gray-700">From</label>
                        <select id="departure-city" name="departure_city" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                            <option value="">Select Departure</option>
                            <?php foreach ($routesList as $city): ?>
                                <option value="<?php echo htmlspecialchars($city); ?>" <?php echo ($city == $departure) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($city); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="arrival-city" class="block text-sm font-medium text-gray-700">To</label>
                        <select id="arrival-city" name="arrival_city" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                            <option value="">Select Arrival</option>
                            <?php foreach ($routesList as $city): ?>
                                <option value="<?php echo htmlspecialchars($city); ?>" <?php echo ($city == $arrival) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($city); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="travel-date" class="block text-sm font-medium text-gray-700">Date</label>
                        <input type="date" id="travel-date" name="travel_date" value="<?php echo htmlspecialchars($searchDate); ?>" min="<?php echo date('Y-m-d'); ?>" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition duration-150">
                            Search Buses
                        </button>
                    </div>
                </form>

                <?php if ($searchPerformed): ?>
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Available Schedules for <?php echo htmlspecialchars($departure); ?> to <?php echo htmlspecialchars($arrival); ?> on <?php echo date('M d, Y', strtotime($searchDate)); ?></h3>
                    
                    <?php if (count($schedules) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($schedules as $schedule): 
                            $availableSeats = $schedule['total_seats'] - $schedule['booked_seats'];
                            $isFull = $availableSeats <= 0;
                        ?>
                            <div class="bg-white p-4 rounded-lg shadow border border-gray-200 flex justify-between items-center transition hover:shadow-lg">
                                
                                <div class="flex-1 min-w-0">
                                    <p class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($schedule['company_name']); ?></p>
                                    <p class="text-sm text-indigo-600 font-semibold">
                                        Departs: <?php echo date('h:i A', strtotime($schedule['departure_time'])); ?> 
                                    </p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($schedule['departure_city']); ?> to <?php echo htmlspecialchars($schedule['arrival_city']); ?></p>
                                </div>

                                <div class="text-center mx-4">
                                    <p class="text-2xl font-extrabold text-green-600">RWF <?php echo number_format($schedule['price']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($schedule['bus_name']); ?></p>
                                </div>
                                
                                <div class="text-center mx-4">
                                    <?php if ($isFull): ?>
                                        <p class="text-sm font-semibold text-red-500">BUS FULL</p>
                                    <?php else: ?>
                                        <p class="text-sm font-semibold text-gray-700">
                                            <?php echo $availableSeats; ?> Seats Available
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <a href="#booking-modal-<?php echo $schedule['schedule_id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 transition duration-150 <?php echo $isFull ? 'opacity-50 pointer-events-none' : ''; ?>">
                                        <i class="fas fa-chair mr-2"></i> Book Now
                                    </a>
                                    </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                        <p class="text-center py-8 text-gray-500 border rounded-lg">No schedules found for this route and date.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-center py-8 text-gray-500">Use the search form above to find available bus schedules.</p>
                <?php endif; ?>

            </div>

            <div id="content-tickets" class="tab-content pt-6 hidden">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">My Active and Past Bookings</h2>
                
                <div class="overflow-x-auto border rounded-lg shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bus & Company</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route & Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seat</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        
                        <tbody id="tickets-table-body" class="bg-white divide-y divide-gray-200">
                            <?php if (count($tickets) > 0): ?>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">BKN-<?php echo htmlspecialchars($ticket['booking_id']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($ticket['company_name']); ?> / <?php echo htmlspecialchars($ticket['bus_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($ticket['departure_city']); ?> to <?php echo htmlspecialchars($ticket['arrival_city']); ?> 
                                            (<?php echo date('h:i A', strtotime(htmlspecialchars($ticket['departure_time']))); ?>)
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-indigo-600 font-bold"><?php echo htmlspecialchars($ticket['seat_number']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                <?php echo htmlspecialchars($ticket['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="../app/TicketGenerator.php?booking_id=<?php echo htmlspecialchars($ticket['booking_id']); ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900">
                                                <i class="fas fa-download mr-1"></i> Download
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">You have no active bookings. Start a search above!</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = document.querySelectorAll('.tab-button');
            const contents = document.querySelectorAll('.tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const targetId = tab.dataset.target;

                    // Deactivate all tabs and hide all content
                    tabs.forEach(t => {
                        t.classList.remove('text-indigo-600', 'border-indigo-500');
                        t.classList.add('text-gray-500', 'border-transparent');
                    });
                    contents.forEach(c => c.classList.add('hidden'));

                    // Activate the clicked tab and show the target content
                    tab.classList.remove('text-gray-500', 'border-transparent');
                    tab.classList.add('text-indigo-600', 'border-indigo-500');
                    document.getElementById(targetId).classList.remove('hidden');
                });
            });
            // Ensure the search tab is active on load
            document.getElementById('tab-search').click();
        });
    </script>
</body>
</html>