<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../DB_connection.php";

// Redirect to the main request form
function fail_request(string $message): void
{
    header("Location: ../index.php?error=" . urlencode($message));
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

// Verify CSRF token
$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    fail_request("Your session expired. Please refresh the form and try again.");
}

// Read submitted form fields
$category       = trim($_POST['category'] ?? '');
$title          = trim($_POST['title'] ?? '');
$description    = trim($_POST['description'] ?? '');
$priority       = trim($_POST['priority'] ?? '');
$company_id     = filter_var(
    $_POST['company_id'] ?? null,
    FILTER_VALIDATE_INT
);
$requested_by   = trim($_POST['requested_by'] ?? '');
$requester_no   = trim($_POST['requester_no'] ?? '');
$assigned_input = trim($_POST['assigned_to_id'] ?? '');
$due_date_input = trim($_POST['due_date'] ?? '');

// Allowed categories: keep synchronized with index.php
$allowedCategories = [
    'Network',
    'Internet',
    'Wi-Fi',
    'Printer',
    'Hardware',
    'Software',
    'System/Application',
    'Installation Request',
    'Email',
    'Account',
    'Password Reset',
    'CCTV',
    'Access Control',
    'Data Backup/Recovery',
    'Maintenance',
    'Preventive Maintenance',
    'Others'
];

// Validate category
if (!in_array($category, $allowedCategories, true)) {
    fail_request("Please select a valid category.");
}

// Validate priority
$allowedPriorities = ['Low', 'Medium', 'High', 'Urgent'];

if (!in_array($priority, $allowedPriorities, true)) {
    fail_request("Please select a valid priority.");
}

// Validate required fields
if (
    $title === '' ||
    mb_strlen($title) > 255 ||
    $description === '' ||
    $requested_by === '' ||
    mb_strlen($requested_by) > 150 ||
    $requester_no === '' ||
    mb_strlen($requester_no) > 30 ||
    $company_id === false ||
    $company_id === null ||
    $company_id <= 0
) {
    fail_request("Please check all required fields.");
}

// Validate Philippine mobile number
if (!preg_match('/^(09\d{9}|\+639\d{9})$/', $requester_no)) {
    fail_request("Please enter a valid Philippine mobile number.");
}

// Validate optional due date
$due_date = null;

if ($due_date_input !== '') {
    $date = DateTime::createFromFormat('!Y-m-d', $due_date_input);

    if (!$date || $date->format('Y-m-d') !== $due_date_input) {
        fail_request("Please provide a valid due date.");
    }

    $due_date = $due_date_input;
}

try {
    // Confirm company exists
    $stmt = $conn->prepare(
        "SELECT comp_id FROM companies WHERE comp_id = ?"
    );
    $stmt->execute([$company_id]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        fail_request("Please select a valid company.");
    }

    // Assign a technician if none was selected
    if ($assigned_input === '') {
        // TEMPORARY: this selects from all users.
        // Add your technician role/department condition when confirmed.
        $stmt = $conn->query(
            "SELECT user_id FROM users ORDER BY RAND() LIMIT 1"
        );

        $technician = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$technician) {
            fail_request("No users are available for assignment.");
        }

        $assigned_to_id = (int) $technician['user_id'];

    } else {
        $assigned_to_id = filter_var(
            $assigned_input,
            FILTER_VALIDATE_INT
        );

        if ($assigned_to_id === false || $assigned_to_id <= 0) {
            fail_request("Please select a valid assignee.");
        }

        $stmt = $conn->prepare(
            "SELECT user_id FROM users WHERE user_id = ?"
        );
        $stmt->execute([$assigned_to_id]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            fail_request("The selected assignee does not exist.");
        }
    }

    // Generate the report number on the server.
    // Assumes report_id is numeric and has a UNIQUE index.
    $conn->beginTransaction();

    $stmt = $conn->query("SELECT MAX(report_id) FROM tasks");
    $lastReportId = $stmt->fetchColumn();

    $report_id = ($lastReportId === null)
        ? 100000
        : ((int) $lastReportId + 1);

    if ($report_id > 999999) {
        throw new RuntimeException("Report number range exhausted.");
    }

    // Collect request metadata
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $device_info = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Insert task, including priority
    $sql = "INSERT INTO tasks (
                report_id,
                category,
                title,
                description,
                priority,
                company_id,
                requested_by,
                requester_no,
                assigned_id,
                due_date,
                requester_ipadd,
                device_info
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        $report_id,
        $category,
        $title,
        $description,
        $priority,
        $company_id,
        $requested_by,
        $requester_no,
        $assigned_to_id,
        $due_date,
        $ip_address,
        $device_info
    ]);

    $conn->commit();

    // Prevent reuse of the same CSRF token
    unset($_SESSION['csrf_token']);

    header(
        "Location: ../index.php?success=" .
        urlencode("Request submitted successfully. Report No: " . $report_id)
    );
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    // Log technical details privately
    error_log("MIS task creation failed: " . $e->getMessage());

    fail_request(
        "Unable to submit your request. Please try again or contact MIS."
    );
}