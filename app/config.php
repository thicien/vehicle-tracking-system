<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
define('DB_HOST', 'localhost');
define('DB_NAME', 'rwanda_bus_booking');
define('DB_USER', 'root');
define('DB_PASS', ''); 

 * @return PDO
 */
function connectDB() {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (\PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("System error: Database connection failed.");
    }
}

function redirectUser($role) {
    switch ($role) {
        case 'admin':
            header('Location: ../public/dashboard_admin.php');
            break;
        case 'company':
            header('Location: ../public/dashboard_company.php');
            break;
        case 'user':
        default:
            header('Location: ../public/dashboard_user.php');
            break;
    }
    exit();
}
function checkAuth($requiredRole = null) {
    if (!isset($_SESSION['logged_in'])) {
        header('Location: index.php'); 
        exit();
    }
    if ($requiredRole && $_SESSION['role'] !== $requiredRole) 
        redirectUser($_SESSION['role']); 
    }
}