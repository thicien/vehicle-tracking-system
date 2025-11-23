<?php
require_once '../app/config.php'; 
if (isset($_SESSION['logged_in'])) {
    redirectUser($_SESSION['role']);
}

$message = '';
$messageType = '';

if (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    $messageType = 'bg-red-100 text-red-700 border-red-400';
    unset($_SESSION['error']);
} elseif (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    $messageType = 'bg-green-100 text-green-700 border-green-400';
    unset($_SESSION['success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Sign-Up | Bus Ticket System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <div class="max-w-md w-full">
        <div class="text-center mb-6">
            <i class="fas fa-bus text-6xl text-indigo-600"></i>
            <h1 class="text-3xl font-bold text-gray-800 mt-2">Rwanda Bus Booking System</h1>
            <p class="text-gray-500">Login or Create an Account</p>
        </div>

        <?php if ($message): ?>
        <div class="p-3 mb-4 rounded-md border text-sm <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <div class="bg-white p-8 rounded-xl shadow-2xl border border-gray-200">
            
            <div class="flex border-b mb-6">
                <button id="login-tab-btn" class="flex-1 py-2 text-center text-lg font-medium border-b-2 border-indigo-600 text-indigo-600 transition duration-200" data-form="login-form">
                    Login
                </button>
                <button id="register-tab-btn" class="flex-1 py-2 text-center text-lg font-medium text-gray-500 border-b-2 border-transparent hover:border-gray-300 transition duration-200" data-form="register-form">
                    Register
                </button>
            </div>

            <form id="login-form" action="../app/Auth.php" method="POST" class="space-y-5">
                <input type="hidden" name="action" value="login">
                
                <div>
                    <label for="login-email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" id="login-email" name="email" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                </div>
                
                <div>
                    <label for="login-password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="login-password" name="password" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                </div>

                <div>
                    <label for="login-role" class="block text-sm font-medium text-gray-700">Login As</label>
                    <select id="login-role" name="role" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                        <option value="user">Passenger / Client</option>
                        <option value="company">Transport Company</option>
                        <option value="admin">System Administrator</option>
                    </select>
                </div>
                
                <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                    <i class="fas fa-sign-in-alt mr-2"></i> Log In
                </button>
            </form>

            <form id="register-form" action="../app/Auth.php" method="POST" class="space-y-5 hidden">
                <input type="hidden" name="action" value="register">
                
                <div>
                    <label for="register-username" class="block text-sm font-medium text-gray-700">Full Name (e.g., Aline Uwimana)</label>
                    <input type="text" id="register-username" name="username" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                </div>
                
                <div>
                    <label for="register-email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" id="register-email" name="email" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                </div>
                
                <div>
                    <label for="register-password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="register-password" name="password" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                </div>

                <div>
                    <label for="register-role" class="block text-sm font-medium text-gray-700">Account Type</label>
                    <select id="register-role" name="role" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                        <option value="user">Passenger / Client</option>
                        <option value="company">Transport Company</option>
                    </select>
                </div>
                
                <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                    <i class="fas fa-user-plus mr-2"></i> Create Account
                </button>
            </form>
        </div>
    </div>

<script>
    document.getElementById('login-tab-btn').addEventListener('click', function() {
        document.getElementById('login-form').classList.remove('hidden');
        document.getElementById('register-form').classList.add('hidden');
        this.classList.add('border-indigo-600', 'text-indigo-600');
        document.getElementById('register-tab-btn').classList.remove('border-indigo-600', 'text-indigo-600');
        document.getElementById('register-tab-btn').classList.add('border-transparent', 'text-gray-500');
    });

    document.getElementById('register-tab-btn').addEventListener('click', function() {
        document.getElementById('register-form').classList.remove('hidden');
        document.getElementById('login-form').classList.add('hidden');
        this.classList.add('border-indigo-600', 'text-indigo-600');
        document.getElementById('login-tab-btn').classList.remove('border-indigo-600', 'text-indigo-600');
        document.getElementById('login-tab-btn').classList.add('border-transparent', 'text-gray-500');
    });
</script>

</body>
</html>
