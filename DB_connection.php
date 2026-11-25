
<?php

$sName = "localhost";
$uName = "root";
$pass = "";
$db_name = "moc_mis_db";

try {
    $conn = new PDO(
        "mysql:host=$sName;dbname=$db_name;charset=utf8mb4",
        $uName,
        $pass
    );

    $conn->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $conn->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    exit("Unable to connect to the database. Please contact the administrator.");
}

// Run the daily MIS presence reset once per Manila calendar day.
// The helper safely preserves On Leave and currently approved leave.
require_once __DIR__ . '/biometric/presence_daily_reset.php';
