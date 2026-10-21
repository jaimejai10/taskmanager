```php
<?php
include "../DB_connection.php";

function insert_task($conn, $data_task) {
    $sql = "INSERT INTO tasks
        (`report_id`, category, title, description, company_id, requested_by, requester_no, assigned_id, due_date, requester_ipadd, device_info)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->execute($data_task);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $report_id = trim($_POST['report_id'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $company_id = trim($_POST['company_id'] ?? '');
        $requested_by = trim($_POST['requested_by'] ?? '');
        $requester_no = trim($_POST['requester_no'] ?? '');
        $assigned_to_id = trim($_POST['assigned_to_id'] ?? '');
        $due_date = trim($_POST['due_date'] ?? '');

        // Validate required fields
        if (
            $report_id === '' ||
            $category === '' ||
            $title === '' ||
            $description === '' ||
            $company_id === '' ||
            $requested_by === '' ||
            $requester_no === ''
        ) {
            header("Location: index.php?error=" . urlencode("Please fill in all required fields."));
            exit;
        }

        // Server information
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $device_info = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Prepare task data in the same order as the INSERT columns
        $data_task = [
            $report_id,
            $category,
            $title,
            $description,
            $company_id,
            $requested_by,
            $requester_no,
            $assigned_to_id !== '' ? $assigned_to_id : null,
            $due_date !== '' ? $due_date : null,
            $ip_address,
            $device_info
        ];

        // Insert task only
        insert_task($conn, $data_task);

        header("Location: index.php?success=" . urlencode("Request submitted successfully."));
        exit;

    } catch (PDOException $e) {
        error_log("Task insertion failed: " . $e->getMessage());

        header("Location: index.php?error=" . urlencode("Unable to submit your request. Please try again."));
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
?>
```