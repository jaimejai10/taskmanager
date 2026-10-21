```php
<?php
include_once "../DB_connection.php";

// Get the selected Assign To value
$assigned_to_id = trim($_POST['assigned_to_id'] ?? '');

echo '<h3>Assign To Test</h3>';

if ($assigned_to_id === '') {

    // No technician selected: randomly select a user
    $sql = "SELECT user_id, full_name FROM users ORDER BY RAND() LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $assigned_to_id = $user['user_id'];

        echo '<p>Assignment Method: Random Assignment</p>';
        echo '<p>Selected User: ' .
            htmlspecialchars($user['full_name']) . '</p>';
        echo '<p>Assigned User ID: ' .
            htmlspecialchars((string)$assigned_to_id) . '</p>';
    } else {
        echo '<p>No users found in the database.</p>';
    }

} else {

    // A specific user was selected
    $sql = "SELECT user_id, full_name
            FROM users
            WHERE user_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$assigned_to_id]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo '<p>Assignment Method: Manually Selected</p>';
        echo '<p>Selected User: ' .
            htmlspecialchars($user['full_name']) . '</p>';
        echo '<p>Assigned User ID: ' .
            htmlspecialchars((string)$user['user_id']) . '</p>';
    } else {
        echo '<p>Selected user was not found.</p>';
    }
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Please submit the form first.');
}


echo '<h2>Submitted Form Data Preview</h2>';

echo '<h3>POST Data</h3>';
echo '<pre>';
echo htmlspecialchars(print_r($_POST, true));
echo '</pre>';

echo '<h3>Uploaded Files</h3>';
echo '<pre>';
echo htmlspecialchars(print_r($_FILES, true));
echo '</pre>';

echo '<h3>Server Information</h3>';
echo '<pre>';
echo 'IP Address: ' . htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
echo 'Device Info: ' . htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? '');
echo '</pre>';
?>
```