
<?php
// ======================================================
// MIS HELPDESK - ZKTECO K30 BIOMETRIC SYNCHRONIZATION
// ALL EMPLOYEES
// ======================================================

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../DB_connection.php';
require_once __DIR__ . '/vendor/autoload.php';

use TahaRafi\ZKTeco\ZKTeco;

// ======================================================
// 1. DEVICE CONFIGURATION
// ======================================================

$deviceIp   = '150.150.150.150';
$devicePort = 4370;
$commKey    = 11126;

$zk = null;

// ======================================================
// 2. PUNCH TYPE MAPPING
// ======================================================

$punchTypes = [
    0 => 'Check In',
    1 => 'Check Out',
    2 => 'Break Out',
    3 => 'Break In'
];

// ======================================================
// 3. PRESENCE STATUS MAPPING
// ======================================================

$presenceStatuses = [
    0 => 'Available', // Check In
    1 => 'Away',      // Check Out
    2 => 'Away',      // Break Out
    3 => 'Available'  // Break In
];

try {

    // ==================================================
    // 4. GET ALL MIS EMPLOYEES
    // ==================================================

    $stmt = $conn->query("
        SELECT
            user_id,
            employee_no,
            presence_status
        FROM users
        WHERE employee_no IS NOT NULL
    ");

    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map employee number to MIS user
    $employeeMap = [];

    foreach ($employees as $employee) {

        $employeeNo = trim((string)$employee['employee_no']);

        if ($employeeNo === '') {
            continue;
        }

        $employeeMap[$employeeNo] = $employee;
    }

    // ==================================================
    // 5. CONNECT TO ZKTECO DEVICE
    // ==================================================

    $zk = new ZKTeco(
        $deviceIp,
        $devicePort,
        $commKey
    );

    if (!$zk->connect()) {
        throw new Exception(
            "Unable to connect to ZKTeco device."
        );
    }

    // Retrieve all attendance records
    $attendance = $zk->getAttendance();

    $zk->disconnect();
    $zk = null;

    if (!is_array($attendance)) {
        throw new Exception(
            "Unable to retrieve biometric attendance records."
        );
    }

    // ==================================================
    // 6. MATCH BIOMETRIC RECORDS TO MIS EMPLOYEES
    // ==================================================

    $matchedRecords = [];
    $unmatchedRecords = 0;
    $invalidRecords = 0;

    foreach ($attendance as $record) {

        if (
            !isset(
                $record[1],
                $record[2],
                $record[3]
            )
        ) {
            $invalidRecords++;
            continue;
        }

        $employeeNo = trim((string)$record[1]);

        // Match biometric AC-No. with users.employee_no
        if (!isset($employeeMap[$employeeNo])) {
            $unmatchedRecords++;
            continue;
        }

        $state = (int)$record[2];

        // Accept only verified punch states
        if (!isset($punchTypes[$state])) {
            $invalidRecords++;
            continue;
        }

        $timestamp = strtotime($record[3]);

        if ($timestamp === false) {
            $invalidRecords++;
            continue;
        }

        $matchedRecords[] = [
            'user_id' => $employeeMap[$employeeNo]['user_id'],
            'employee_no' => $employeeNo,
            'state' => $state,
            'punch_type' => $punchTypes[$state],
            'punch_time' => date(
                'Y-m-d H:i:s',
                $timestamp
            )
        ];
    }

    // Sort oldest to newest
    usort(
        $matchedRecords,
        function ($a, $b) {
            return strcmp(
                $a['punch_time'],
                $b['punch_time']
            );
        }
    );

    // ==================================================
    // 7. PREPARE DATABASE OPERATIONS
    // ==================================================

    $inserted = 0;
    $duplicates = 0;
    $updatedEmployees = 0;
    $protectedEmployees = 0;

    $conn->beginTransaction();

    // Check duplicate attendance records
    $checkDuplicate = $conn->prepare("
        SELECT id
        FROM attendance_logs
        WHERE user_id = :user_id
          AND biometric_id = :biometric_id
          AND punch_time = :punch_time
          AND punch_type = :punch_type
        LIMIT 1
    ");

    // Insert attendance records
    $insertLog = $conn->prepare("
        INSERT INTO attendance_logs
        (
            user_id,
            biometric_id,
            punch_time,
            punch_type
        )
        VALUES
        (
            :user_id,
            :biometric_id,
            :punch_time,
            :punch_type
        )
    ");

    // Update employee presence status using employee_no
    $updateStatus = $conn->prepare("
        UPDATE users
        SET presence_status = :status
        WHERE employee_no = :employee_no
          AND LOWER(TRIM(presence_status)) <> 'on leave'
    ");

    // ==================================================
    // 8. INSERT ATTENDANCE LOGS
    // ==================================================

    foreach ($matchedRecords as $record) {

        $params = [
            ':user_id' => $record['user_id'],
            ':biometric_id' => $record['employee_no'],
            ':punch_time' => $record['punch_time'],
            ':punch_type' => $record['punch_type']
        ];

        $checkDuplicate->execute($params);

        if ($checkDuplicate->fetch()) {
            $duplicates++;
            continue;
        }

        $insertLog->execute($params);

        $inserted++;
    }

    // ==================================================
    // 9. GET LATEST PUNCH PER EMPLOYEE
    // ==================================================

    $latestPunches = [];

    foreach ($matchedRecords as $record) {

        $employeeNo = $record['employee_no'];

        // Records are sorted oldest to newest,
        // so the latest record replaces the previous one.
        $latestPunches[$employeeNo] = $record;
    }

    // ==================================================
    // 10. UPDATE PRESENCE STATUS FOR ALL EMPLOYEES
    // ==================================================

    $employeeResults = [];

    foreach ($latestPunches as $employeeNo => $record) {

        $state = $record['state'];

        $newStatus = $presenceStatuses[$state];

        $updateStatus->execute([
            ':status' => $newStatus,
            ':employee_no' => $employeeNo
        ]);

        // Retrieve actual current status
        $checkStatus = $conn->prepare("
            SELECT
                employee_no,
                presence_status
            FROM users
            WHERE employee_no = :employee_no
            LIMIT 1
        ");

        $checkStatus->execute([
            ':employee_no' => $employeeNo
        ]);

        $employeeData = $checkStatus->fetch(PDO::FETCH_ASSOC);

        if (!$employeeData) {
            continue;
        }

        $currentStatus = $employeeData['presence_status'];

        if (strtolower(trim($currentStatus)) === 'on leave') {

            $protectedEmployees++;

        } else {

            $updatedEmployees++;
        }

        $employeeResults[] = [
            'employee_no' => $employeeNo,
            'punch_type' => $record['punch_type'],
            'punch_time' => $record['punch_time'],
            'presence_status' => $currentStatus
        ];
    }

    // ==================================================
    // 11. COMMIT TRANSACTION
    // ==================================================

    $conn->commit();

    // ==================================================
    // 12. DISPLAY SYNCHRONIZATION RESULTS
    // ==================================================

    echo "<h2>MIS Biometric Synchronization</h2>";

    echo "<p><strong>Device:</strong> ZKTeco K30</p>";

    echo "<p><strong>Database:</strong> Connected</p>";

    echo "<hr>";

    echo "<h3>Synchronization Summary</h3>";

    echo "<p>Total MIS Employees: " .
        count($employeeMap) .
        "</p>";

    echo "<p>Total Device Records: " .
        count($attendance) .
        "</p>";

    echo "<p>Matched Attendance Records: " .
        count($matchedRecords) .
        "</p>";

    echo "<p>New Attendance Logs Inserted: " .
        $inserted .
        "</p>";

    echo "<p>Duplicate Logs Skipped: " .
        $duplicates .
        "</p>";

    echo "<p>Unmatched Biometric Records: " .
        $unmatchedRecords .
        "</p>";

    echo "<p>Invalid Records Skipped: " .
        $invalidRecords .
        "</p>";

    echo "<p>Employees Processed: " .
        count($employeeResults) .
        "</p>";

    echo "<p>Employees Protected (On Leave): " .
        $protectedEmployees .
        "</p>";

    echo "<hr>";

    echo "<h3>Employee Presence Status Results</h3>";

    echo "<table border='1' cellpadding='8' cellspacing='0'>";

    echo "<tr>";
    echo "<th>Employee No.</th>";
    echo "<th>Latest Punch</th>";
    echo "<th>Punch Time</th>";
    echo "<th>Presence Status</th>";
    echo "</tr>";

    foreach ($employeeResults as $result) {

        echo "<tr>";

        echo "<td>" .
            htmlspecialchars($result['employee_no']) .
            "</td>";

        echo "<td>" .
            htmlspecialchars($result['punch_type']) .
            "</td>";

        echo "<td>" .
            htmlspecialchars($result['punch_time']) .
            "</td>";

        echo "<td>" .
            htmlspecialchars($result['presence_status']) .
            "</td>";

        echo "</tr>";
    }

    echo "</table>";

    echo "<hr>";

    echo "<p><strong>Biometric synchronization completed successfully.</strong></p>";

} catch (Throwable $e) {

    // Disconnect device if necessary
    if ($zk !== null) {

        try {
            $zk->disconnect();
        } catch (Throwable $ignored) {
        }
    }

    // Roll back database operations
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log(
        "Biometric synchronization error: " .
        $e->getMessage()
    );

    echo "<h3>Synchronization Error</h3>";

    echo "<p>" .
        htmlspecialchars($e->getMessage()) .
        "</p>";
}
