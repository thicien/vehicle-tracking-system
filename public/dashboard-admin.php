<?php
require_once '../app/config.php';
$pdo = connectDB();
checkAuth('admin'); 

$adminName = $_SESSION['username']; 

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalCompanies = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
$totalBookingsMonth = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$totalRevenue = $pdo->query("SELECT SUM(sch.price) FROM bookings b JOIN schedules sch ON b.schedule_id = sch.id")->fetchColumn();

$systemUsers = $pdo->query("SELECT id, username, email, role FROM users ORDER BY id DESC")->fetchAll();

$companiesList = $pdo->query("
    SELECT c.id, c.company_name, c.status, 
           (SELECT COUNT(*) FROM buses WHERE company_id = c.id) as fleet_size 
    FROM companies c
    ORDER BY c.status DESC, c.id DESC
")->fetchAll();

$activities = $pdo->query("
    SELECT id, role, action, timestamp 
    FROM activity_log 
    ORDER BY timestamp DESC 
    LIMIT 10
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<body class="bg-gray-100">

    <header class="bg-teal-600 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center h-16">
            <h1 class="text-2xl font-bold text-white">
                <i class="fas fa-shield-alt mr-2"></i> Admin Control
            </h1>
            <div class="flex items-center space-x-4">
                <span class="text-white text-sm">Welcome, <strong class="capitalize"><?php echo htmlspecialchars($adminName); ?></strong></span>
                <a href="../app/Auth.php?action=logout" class="text-white bg-teal-700 hover:bg-teal-800 px-3 py-1 rounded-md text-sm font-medium transition duration-150">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="bg-white shadow-xl rounded-lg p-6">
            
            <div id="overview" class="tab-content block">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Global System KPIs</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-lg shadow border-l-4 border-teal-500">
                        <p class="text-sm font-medium text-gray-500">Total Users</p>
                        <p class="text-3xl font-extrabold text-gray-900 mt-1"><?php echo number_format($totalUsers); ?></p>
                        </div>
                    <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
                        <p class="text-sm font-medium text-gray-500">Total Companies</p>
                        <p class="text-3xl font-extrabold text-blue-600 mt-1"><?php echo number_format($totalCompanies); ?></p>
                        </div>
                    <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
                        <p class="text-sm font-medium text-gray-500">Total Bookings (Month)</p>
                        <p class="text-3xl font-extrabold text-green-600 mt-1"><?php echo number_format($totalBookingsMonth); ?></p>
                        </div>
                    <div class="bg-white p-6 rounded-lg shadow border-l-4 border-yellow-500">
                        <p class="text-sm font-medium text-gray-500">Total Revenue (System)</p>
                        <p class="text-3xl font-extrabold text-yellow-600 mt-1">RWF <?php echo number_format($totalRevenue ?? 0); ?></p>
                        </div>
                </div>

                <h3 class="text-xl font-semibold text-gray-800 mb-4">Recent System Activities</h3>
                <div class="overflow-x-auto border rounded-lg shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($activity['timestamp']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($activity['action']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-teal-600">N/A</td> <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo ($activity['role'] == 'admin' ? 'teal' : ($activity['role'] == 'company' ? 'red' : 'indigo')); ?>-100 text-<?php echo ($activity['role'] == 'admin' ? 'teal' : ($activity['role'] == 'company' ? 'red' : 'indigo')); ?>-800">
                                            <?php echo htmlspecialchars($activity['role']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="user-management" class="tab-content">
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($systemUsers as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['id']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($user['username']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo ($user['role'] == 'admin' ? 'teal' : ($user['role'] == 'company' ? 'red' : 'indigo')); ?>-100 text-<?php echo ($user['role'] == 'admin' ? 'teal' : ($user['role'] == 'company' ? 'red' : 'indigo')); ?>-800">
                                    <?php echo htmlspecialchars($user['role']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button class="text-yellow-600 hover:text-yellow-800 mr-3">Suspend</button>
                                <button class="text-red-600 hover:text-red-800">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </div>

            <div id="company-management" class="tab-content">
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($companiesList as $company): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($company['id']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 font-semibold"><?php echo htmlspecialchars($company['company_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($company['fleet_size']); ?> Buses</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo ($company['status'] == 'Active' ? 'green' : ($company['status'] == 'Pending' ? 'yellow' : 'red')); ?>-100 text-<?php echo ($company['status'] == 'Active' ? 'green' : ($company['status'] == 'Pending' ? 'yellow' : 'red')); ?>-800">
                                    <?php echo htmlspecialchars($company['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($company['status'] == 'Pending'): ?>
                                    <button class="text-teal-600 hover:text-teal-800 mr-3">Approve</button>
                                <?php endif; ?>
                                <button class="text-yellow-600 hover:text-yellow-800 mr-3">Suspend</button>
                                <button class="text-red-600 hover:text-red-800">Revoke</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </div>
            
        </div>
    </main>

    </body>
</html>