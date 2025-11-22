<?php
// /app/Auth.php
require_once 'config.php';
$pdo = connectDB();
// Session is started in config.php

// Check if an action was submitted via POST or GET
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- 1. LOGOUT LOGIC ---
if ($action === 'logout') {
    session_destroy();
    session_start(); // Restart session to set a success message
    $_SESSION['success'] = "You have been successfully logged out.";
    header('Location: ../public/index.php');
    exit();
}

// Ensure the request is a POST for security (for login/register)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/index.php');
    exit();
}

// --- 2. REGISTRATION LOGIC ---
if ($action === 'register') {
    $username = filter_var($_POST['username'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    // Simple validation
    if (empty($username) || empty($email) || strlen($password) < 6) {
        $_SESSION['error'] = "Please fill all fields and use a password of at least 6 characters.";
        header('Location: ../public/index.php');
        exit();
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Start transaction for atomic operations
        $pdo->beginTransaction();

        // Insert into users table
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $passwordHash, $role]);
        $userId = $pdo->lastInsertId();

        // If the user registered as a 'company', create a corresponding company record
        if ($role === 'company') {
            $companyName = $_POST['company_name'] ?? $username . ' Transport'; // Use username as company name default
            $stmt = $pdo->prepare("INSERT INTO companies (user_id, company_name, status) VALUES (?, ?, 'Pending')");
            $stmt->execute([$userId, $companyName]);
            $_SESSION['warning'] = "Your company account is pending approval by an Admin.";
        }
        
        $pdo->commit();

        // Log the user in immediately after successful registration
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['success'] = "Account created successfully! Welcome.";
        
        // Redirect the new user to their dashboard
        redirectUser($role); 

    } catch (\PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) { // SQLSTATE for Integrity Constraint Violation (Duplicate email)
            $_SESSION['error'] = "Registration failed. That email is already registered.";
        } else {
            $_SESSION['error'] = "An unexpected database error occurred during registration. " . $e->getMessage();
        }
        header('Location: ../public/index.php');
        exit();
    }
}

// --- 3. LOGIN LOGIC ---
elseif ($action === 'login') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user'; // Role selection from the form

    // Fetch user based on email and selected role
    $stmt = $pdo->prepare("SELECT id, password_hash, username, role FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Successful login
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['success'] = "Login successful! Welcome back, " . $user['username'];
        
        // Redirect the user to their dashboard
        redirectUser($user['role']);
        
    } else {
        // Failed login
        $_SESSION['error'] = "Invalid email, password, or role selection.";
        header('Location: ../public/index.php');
        exit();
    }
}

// --- Default Fallback (if action is invalid) ---
else {
    header('Location: ../public/index.php');
    exit();
}
?>