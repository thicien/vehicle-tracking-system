<?php
// /app/CompanyManager.php

require_once 'config.php';
$pdo = connectDB();
checkAuth('company'); 

// Ensure the request is a POST request and an action is defined
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    $_SESSION['error'] = "Invalid request method or missing action.";
    header('Location: ../public/dashboard_company.php');
    exit();
}

$action = $_POST['action'];
$companyId = $_SESSION['company_id'] ?? null; // Assume company_id is stored in the session after login

// Security check: Verify the company ID
if (!$companyId) {
    // Look up company ID if it wasn't set in the session (good practice)
    try {
        $stmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $companyId = $stmt->fetchColumn();
        if (!$companyId) {
            $_SESSION['error'] = "Company profile not found.";
            header('Location: ../public/dashboard_company.php');
            exit();
        }
        $_SESSION['company_id'] = $companyId; // Save for future use
    } catch (\PDOException $e) {
        error_log("Company ID lookup error: " . $e->getMessage());
        $_SESSION['error'] = "Database error during company lookup.";
        header('Location: ../public/dashboard_company.php');
        exit();
    }
}

// --- 1. Handle Add Bus Action ---
if ($action === 'add_bus') {
    $busName = filter_input(INPUT_POST, 'bus_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $licensePlate = filter_input(INPUT_POST, 'license_plate', FILTER_SANITIZE_SPECIAL_CHARS);
    $totalSeats = filter_input(INPUT_POST, 'total_seats', FILTER_VALIDATE_INT);
    $busType = filter_input(INPUT_POST, 'bus_type', FILTER_SANITIZE_SPECIAL_CHARS);

    if (!$busName || !$licensePlate || $totalSeats === false || $totalSeats < 15 || !$busType) {
        $_SESSION['error'] = "Invalid or missing bus details. Please check all fields.";
        header('Location: ../public/dashboard_company.php');
        exit();
    }

    try {
        // Check for duplicate license plate
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM buses WHERE license_plate = ?");
        $stmt->execute([$licensePlate]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = "Error: A bus with this license plate already exists.";
            header('Location: ../public/dashboard_company.php');
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO buses (company_id, bus_name, license_plate, total_seats, bus_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$companyId, $busName, $licensePlate, $totalSeats, $busType]);

        $_SESSION['success'] = "Successfully added the new bus: **" . $busName . "** (" . $licensePlate . ").";
    } catch (\PDOException $e) {
        error_log("Bus insertion error: " . $e->getMessage());
        $_SESSION['error'] = "Database error: Could not register the bus.";
    }

    header('Location: ../public/dashboard_company.php');
    exit();
}

// --- 2. Handle Add Schedule Action ---
elseif ($action === 'add_schedule') {
    $busId = filter_input(INPUT_POST, 'bus_id', FILTER_VALIDATE_INT);
    $routeId = filter_input(INPUT_POST, 'route_id', FILTER_VALIDATE_INT);
    $departureTime = filter_input(INPUT_POST, 'departure_time', FILTER_SANITIZE_SPECIAL_CHARS);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_INT);

    // Combine current date with user provided time for the datetime format required by the DB
    $currentDate = date('Y-m-d');
    $fullDepartureDatetime = $currentDate . ' ' . $departureTime . ':00';
    
    // Simple validation
    if ($busId === false || $routeId === false || !$departureTime || $price === false || $price < 100) {
        $_SESSION['error'] = "Invalid or missing schedule details. Please check the bus, route, time, and price.";
        header('Location: ../public/dashboard_company.php');
        exit();
    }
    
    try {
        // Security Check: Ensure the selected bus belongs to this company
        $stmt = $pdo->prepare("SELECT company_id FROM buses WHERE id = ?");
        $stmt->execute([$busId]);
        if ($stmt->fetchColumn() != $companyId) {
            $_SESSION['error'] = "Security breach: Attempted to schedule a bus not belonging to your company.";
            header('Location: ../public/dashboard_company.php');
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO schedules (route_id, bus_id, departure_time, price) VALUES (?, ?, ?, ?)");
        $stmt->execute([$routeId, $busId, $fullDepartureDatetime, $price]);

        $_SESSION['success'] = "Successfully published a new schedule.";
    } catch (\PDOException $e) {
        error_log("Schedule insertion error: " . $e->getMessage());
        $_SESSION['error'] = "Database error: Could not publish the schedule.";
    }

    header('Location: ../public/dashboard_company.php');
    exit();
}

// --- 3. Handle Delete Schedule Action (GET Request) ---
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_schedule') {
    $scheduleId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($scheduleId === false) {
        $_SESSION['error'] = "Invalid schedule ID provided for deletion.";
        header('Location: ../public/dashboard_company.php');
        exit();
    }

    try {
        // Security Check: Only delete schedules associated with the company's buses
        $stmt = $pdo->prepare("
            DELETE FROM schedules 
            WHERE id = ? 
            AND bus_id IN (SELECT id FROM buses WHERE company_id = ?)
        ");
        $stmt->execute([$scheduleId, $companyId]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Schedule ID $scheduleId successfully deleted.";
        } else {
            $_SESSION['error'] = "Schedule not found or not owned by your company.";
        }
    } catch (\PDOException $e) {
        error_log("Schedule deletion error: " . $e->getMessage());
        $_SESSION['error'] = "Database error: Could not delete the schedule.";
    }

    header('Location: ../public/dashboard_company.php');
    exit();
}

else {
    $_SESSION['error'] = "Unknown action requested.";
    header('Location: ../public/dashboard_company.php');
    exit();
}