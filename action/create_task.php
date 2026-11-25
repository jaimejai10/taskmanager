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
// TASK ATTACHMENT UPLOAD FUNCTION
// Accepts JPG, PNG, and PDF files up to 5 MB.
// Returns a relative path for the database, or null.
// ========================================

function upload_task_attachment(?array $file, int $reportId): ?string
{
    if ($file === null || !isset($file['error'])) {
        return null;
    }

    // Attachment is optional.
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('The attachment exceeds the allowed upload size.');
        }

        throw new RuntimeException('The attachment could not be uploaded. Please try again.');
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid uploaded attachment.');
    }

    $maxFileSize = 5 * 1024 * 1024; // 5 MB
    $actualFileSize = filesize($file['tmp_name']);

    if ($actualFileSize === false || $actualFileSize > $maxFileSize) {
        throw new RuntimeException('The attachment must not exceed 5 MB.');
    }

    // Detect the real file type instead of trusting the browser filename/MIME.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedMimeTypes = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'application/pdf' => 'pdf'
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        throw new RuntimeException('Only JPG, PNG, and PDF attachments are allowed.');
    }

    // create_task.php is inside /action, so dirname(__DIR__) is the project root.
    $uploadDirectory = dirname(__DIR__) . '/uploads/task-attachments/';

    if (!is_dir($uploadDirectory)) {
        if (!mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('The attachment folder could not be created.');
        }
    }

    if (!is_writable($uploadDirectory)) {
        throw new RuntimeException('The attachment folder is not writable by the web server.');
    }

    $extension = $allowedMimeTypes[$mimeType];
    $fileName = $reportId . '_' . bin2hex(random_bytes(12)) . '.' . $extension;
    $destination = $uploadDirectory . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('The attachment could not be saved on the server.');
    }

    // Store only this relative path in the database.
    return 'uploads/task-attachments/' . $fileName;
}

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

// Mobile number is optional. Validate its format only when provided.
if ($requester_no !== '' && !preg_match('/^(09\d{9}|\+639\d{9})$/', $requester_no)) {
    fail_request("Please enter a valid Philippine mobile number.");
}

// ========================================
// 10. VALIDATE OPTIONAL DUE DATE
// ========================================

// ========================================
 // HELPER: CALCULATE DUE DATE BY PRIORITY
 // Working days are Monday-Saturday; Sunday is skipped.
 // ========================================

function calculate_priority_due_date(string $priority): string
{
    $workingDaysToAdd = [
        'Urgent' => 0,
        'High'   => 3,
        'Medium' => 5,
        'Low'    => 7
    ];

    $timezone = new DateTimeZone('Asia/Manila');
    $date = new DateTimeImmutable('today', $timezone);

    // Requests should not be processed on Sunday. If one reaches the
    // server on Sunday, begin the calculation from Monday.
    if ((int) $date->format('w') === 0) {
        $date = $date->modify('+1 day');
    }

    $remainingDays = $workingDaysToAdd[$priority] ?? 5;

    while ($remainingDays > 0) {
        $date = $date->modify('+1 day');

        // Sunday = 0; Monday-Saturday count as working days.
        if ((int) $date->format('w') !== 0) {
            $remainingDays--;
        }
    }

    return $date->format('Y-m-d');
}


$due_date = null;

if ($due_date_input !== '') {
    $date = DateTime::createFromFormat(
        '!Y-m-d',
        $due_date_input,
        new DateTimeZone('Asia/Manila')
    );

    if (
        !$date ||
        $date->format('Y-m-d') !== $due_date_input
    ) {
        fail_request("Please provide a valid due date.");
    }

    // A manually selected date is preserved, but Sunday is not an
    // available working day for MIS Helpdesk deadlines.
    if ((int) $date->format('w') === 0) {
        fail_request("Please select a due date from Monday to Saturday. Sundays are non-working days.");
    }

    $due_date = $due_date_input;
} else {
    // Do not rely on JavaScript: calculate the deadline on the server too.
    $due_date = calculate_priority_due_date($priority);
}

// ========================================
// 11. VALIDATE AND INSERT TASK
// ========================================

$savedAttachmentPath = null;

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
            "SELECT user_id, full_name
             FROM users
             ORDER BY RAND()
             LIMIT 1"
        );

        $technician = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$technician) {
            fail_request("No users are available for assignment.");
        }

        $assigned_to_id = (int) $technician['user_id'];
        $assignedStaffName = $technician['full_name'];

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
            "SELECT user_id, full_name
             FROM users
             WHERE user_id = ?
             LIMIT 1"
        );

        $stmt->execute([$assigned_to_id]);
        $technician = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$technician) {
            fail_request("The selected assignee does not exist.");
        }

        $assignedStaffName = $technician['full_name'];
    }

    // ------------------------------------
    // Begin database transaction
    // ------------------------------------

    $conn->beginTransaction();

    // ------------------------------------
    // Generate a concurrency-safe Service Report Number
    // The sequence row is locked until this transaction commits.
    // First number: 100000
    // ------------------------------------

    $stmt = $conn->query(
        "SELECT last_number
         FROM task_report_sequence
         WHERE sequence_id = 1
         FOR UPDATE"
    );

    $lastReportId = $stmt->fetchColumn();

    if ($lastReportId === false) {
        throw new RuntimeException(
            "Service report sequence is not initialized."
        );
    }

    $report_id = (int) $lastReportId + 1;

    if ($report_id > 999999) {
        throw new RuntimeException(
            "Service Report Number range exhausted."
        );
    }

    $stmt = $conn->prepare(
        "UPDATE task_report_sequence
         SET last_number = ?
         WHERE sequence_id = 1"
    );

    $stmt->execute([$report_id]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            "Unable to update the service report sequence."
        );
    }

    // ------------------------------------
    // Save optional attachment
    // ------------------------------------

    $savedAttachmentPath = upload_task_attachment(
        $_FILES['attachment'] ?? null,
        $report_id
    );

    // ------------------------------------
    // Capture requester metadata
    // ------------------------------------

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    $device_info = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // ------------------------------------
    // INSERT INTO TASKS TABLE
    // 13 columns = 13 values
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
                device_info,
                attachment_path
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
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
        $device_info,
        $savedAttachmentPath
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
    // SEND SMS NOTIFICATION TO REQUESTER
    // SMS is optional and must never affect
    // the successful task submission.
    // ------------------------------------

    if ($requester_no !== '') {
        try {
            require_once dirname(__DIR__) . '/includes/sms.php';

            $smsMessage = "Your MIS request has been received successfully. "
                        . "Service Report No: " . $report_id . ". "
                        . "Assigned Staff: " . $assignedStaffName . ". "
                        . "Thank you.";

            send_sms($requester_no, $smsMessage);

        } catch (Throwable $smsError) {
            // Log SMS problems without undoing the saved task.
            error_log(
                "MIS requester SMS failed for Report ID "
                . $report_id . ": " . $smsError->getMessage()
            );
        }
    }

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

    // Remove the uploaded file if the database insert/commit failed.
    if ($savedAttachmentPath !== null) {
        $fullAttachmentPath = dirname(__DIR__) . '/' . $savedAttachmentPath;

        if (is_file($fullAttachmentPath)) {
            @unlink($fullAttachmentPath);
        }
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

