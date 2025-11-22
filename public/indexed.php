// /public/index.php - (Insert this block right after the <body> tag)
<?php
// Include config to start session and access messages
require_once '../app/config.php'; 

// Check if user is already logged in and redirect immediately
if (isset($_SESSION['logged_in'])) {
    redirectUser($_SESSION['role']);
}

// --- Display Messages ---
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
<?php if ($message): ?>
<div class="p-3 mb-4 rounded-md border text-sm <?php echo $messageType; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>
