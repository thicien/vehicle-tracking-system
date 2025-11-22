<?php
// /app/config.php

// 1. Session Management
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Database Constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'rwanda_bus_booking');
define('DB_USER', 'root');
// FIX: Using an empty password for default XAMPP MySQL root user.
define('DB_PASS', ''); 

/**
 * Creates and returns a secure PDO database connection object.
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
        // Log the error for security and debugging
        error_log("Database connection failed: " . $e->getMessage());
        die("System error: Database connection failed.");
    }
}

// 3. Redirection Helper
function redirectUser($role) {
    // This is the correct relative path for files inside /app to reach /public dashboards
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
// Function to check if a user is logged in
function checkAuth($requiredRole = null) {
    if (!isset($_SESSION['logged_in'])) {
        // Redirects to the index.php in the public folder
        header('Location: index.php'); 
        exit();
    }
    if ($requiredRole && $_SESSION['role'] !== $requiredRole) {
        // Redirect to their assigned dashboard if they try to access another role's page
        redirectUser($_SESSION['role']); 
    }
}