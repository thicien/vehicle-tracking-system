<?php
require_once 'config.php';
$pdo = connectDB();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'logout') {
    session_destroy();
    session_start();
    $_SESSION['success'] = "You have been successfully logged out.";
    header('Location: ../public/index.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/index.php');
    exit();
}

if ($action === 'register') {
    $username = filter_var($_POST['username'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if (empty($username) || empty($email) || strlen($password) < 6) {
        $_SESSION['error'] = "Please fill all fields and use a password of at least 6 characters.";
        header('Location: ../public/index.php');
        exit();
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $passwordHash, $role]);
        $userId = $pdo->lastInsertId();

        if ($role === 'company') {
            $companyName = $_POST['company_name'] ?? $username . ' Transport';
            $stmt = $pdo->prepare("INSERT INTO companies (user_id, company_name, status) VALUES (?, ?, 'Pending')");
            $stmt->execute([$userId, $companyName]);
            $_SESSION['warning'] = "Your company account is pending approval by an Admin.";
        }
        
        $pdo->commit();

        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['success'] = "Account created successfully! Welcome.";
        redirectUser($role); 

    } catch (\PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) {
            $_SESSION['error'] = "Registration failed. That email is already registered.";
        } else {
            $_SESSION['error'] = "An unexpected database error occurred during registration. " . $e->getMessage();
        }
        header('Location: ../public/index.php');
        exit();
    }
}

elseif ($action === 'login') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    $stmt = $pdo->prepare("SELECT id, password_hash, username, role FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) 
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['success'] = "Login successful! Welcome back, " . $user['username'];
        redirectUser($user['role']);
        
    } else {
        $_SESSION['error'] = "Invalid email, password, or role selection.";
        header('Location: ../public/index.php');
        exit();
    }
}
else {
    header('Location: ../public/index.php');
    exit();
}
?>