<?php
declare(strict_types=1);

// ========================================
// 1. SESSION AND DATABASE CONNECTION
// ========================================

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../DB_connection.php";

// ========================================
// 2. REDIRECT HELPER
// ========================================

function fail_request(string $message): void
{
    header("Location: ../index.php?error=" . urlencode($message));
    exit;
}

// ========================================
// 3. ACCEPT POST REQUESTS ONLY
// ========================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

// ========================================
// 4. CSRF SECURITY VALIDATION
// ========================================

$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken   = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    fail_request(
        "Your session expired. Please refresh the form and try again."
    );
}

// ========================================
// 5. RECEIVE FORM DATA
// ========================================

$category       = trim($_POST['category'] ?? '');
$title          = trim($_POST['title'] ?? '');
$description    = trim($_POST['description'] ?? '');
$priority       = trim($_POST['priority'] ?? '');
$requested_by   = trim($_POST['requested_by'] ?? '');
$requester_no   = trim($_POST['requester_no'] ?? '');
$assigned_input = trim($_POST['assigned_to_id'] ?? '');
$due_date_input = trim($_POST['due_date'] ?? '');

$company_id = filter_var(
    $_POST['company_id'] ?? null,
    FILTER_VALIDATE_INT
);

// ========================================
// 6. VALIDATE CATEGORY
// Keep this list synchronized with index.php
// ========================================

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

if (!in_array($category, $allowedCategories, true)) {
    fail_request("Please select a valid category.");
}

// ========================================
// 7. VALIDATE PRIORITY
// ========================================

$allowedPriorities = [
    'Low',
    'Medium',
    'High',
    'Urgent'
];

if (!in_array($priority, $allowedPriorities, true)) {
    fail_request("Please select a valid priority.");
}

// ========================================
// 8. VALIDATE REQUIRED FIELDS
// ========================================

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

// ========================================
// 9. VALIDATE PHILIPPINE MOBILE NUMBER
// ========================================

if (!preg_match('/^(09\d{9}|\+639\d{9})$/', $requester_no)) {
    fail_request("Please enter a valid Philippine mobile number.");
}

// ========================================
// 10. VALIDATE OPTIONAL DUE DATE
// ========================================

$due_date = null;

if ($due_date_input !== '') {
    $date = DateTime::createFromFormat(
        '!Y-m-d',
        $due_date_input
    );

    if (
        !$date ||
        $date->format('Y-m-d') !== $due_date_input
    ) {
        fail_request("Please provide a valid due date.");
    }

    $due_date = $due_date_input;
}

// ========================================
// 11. VALIDATE AND INSERT TASK
// ========================================

try {

    // ------------------------------------
    // Verify company exists
    // ------------------------------------

    $stmt = $conn->prepare(
        "SELECT comp_id
         FROM companies
         WHERE comp_id = ?
         LIMIT 1"
    );

    $stmt->execute([$company_id]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        fail_request("Please select a valid company.");
    }

    // ------------------------------------
    // Resolve technician assignment
    // ------------------------------------

    if ($assigned_input === '') {

        // TEMPORARY:
        // Randomly select one user.
        // Later, filter this query by technician role
        // and exclude technicians on approved leave.

        $stmt = $conn->query(
            "SELECT user_id
             FROM users
             ORDER BY RAND()
             LIMIT 1"
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

        if (
            $assigned_to_id === false ||
            $assigned_to_id <= 0
        ) {
            fail_request("Please select a valid assignee.");
        }

        $stmt = $conn->prepare(
            "SELECT user_id
             FROM users
             WHERE user_id = ?
             LIMIT 1"
        );

        $stmt->execute([$assigned_to_id]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            fail_request("The selected assignee does not exist.");
        }
    }

    // ------------------------------------
    // Begin database transaction
    // ------------------------------------

    $conn->beginTransaction();

    // ------------------------------------
    // Generate Service Report Number
    // First number: 100000
    // ------------------------------------

    $stmt = $conn->query(
        "SELECT MAX(report_id) FROM tasks"
    );

    $lastReportId = $stmt->fetchColumn();

    $report_id = ($lastReportId === null)
        ? 100000
        : ((int) $lastReportId + 1);

    if ($report_id > 999999) {
        throw new RuntimeException(
            "Service Report Number range exhausted."
        );
    }

    // ------------------------------------
    // Capture requester metadata
    // ------------------------------------

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    $device_info = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // ------------------------------------
    // INSERT INTO TASKS TABLE
    // 12 columns = 12 values
    // ------------------------------------

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
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )";

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

    // Ensure the insert affected one row
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            "Task insertion did not create a row."
        );
    }

    // ------------------------------------
    // COMMIT DATABASE TRANSACTION
    // ------------------------------------

    $conn->commit();

    // ------------------------------------
    // SUCCESS MESSAGE FOR INDEX.PHP
    // Only set after successful commit
    // ------------------------------------

    $_SESSION['task_success'] = [
        'report_id' => (string) $report_id
    ];

    // Generate a fresh CSRF token for the next request
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Return to main request form
    header("Location: ../index.php");
    exit;

} catch (Throwable $e) {

    // Roll back any uncommitted database changes
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    // Log technical details privately
    error_log(
        "MIS task creation failed: " . $e->getMessage()
    );

    // Do not show success if the insert fails
    fail_request(
        "Unable to save your request. Please check your details and try again."
    );
}

