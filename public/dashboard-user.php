<?php
// /public/dashboard_user.php
require_once '../app/config.php';
$pdo = connectDB();
// Enforce security: only logged-in users can view this page
checkAuth('user'); 

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$tickets = [];

// Fetch user's tickets
try {
    $stmt = $pdo->prepare("
        SELECT 
            b.id AS booking_id, c.company_name, sch.departure_time, 
            r.departure_city, r.arrival_city, b.seat_number, b.status, bus.bus_name
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
}

// Add PHP message display block here (similar to index.php)
// ... (start HTML)
?>

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
        <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">You have no active bookings.</td></tr>
    <?php endif; ?>
</tbody>