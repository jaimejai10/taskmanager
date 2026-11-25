
<?php
// ======================================================
// MIS HELPDESK - ZKTECO K30 BIOMETRIC SYNCHRONIZATION
// ======================================================

date_default_timezone_set('Asia/Manila');

// Existing MIS database connection
require_once __DIR__ . '/../DB_connection.php';

// ZKTeco library
require_once __DIR__ . '/vendor/autoload.php';

use TahaRafi\ZKTeco\ZKTeco;

// ======================================================
// 1. DEVICE CONFIGURATION
// ======================================================

$deviceIp   = '150.150.150.150';
$devicePort = 4370;
$commKey    = 11126;

// Initial testing: Employee 1091 only
$employeeNo = '1091';

$zk = null;

try {

    // ==================================================
    // 2. FIND EMPLOYEE
    // ==================================================

    $stmt = $conn->prepare("
        SELECT
            user_id,
            employee_no,
            biometric_id,
            presence_status
        FROM users
        WHERE employee_no = :employee_no
        LIMIT 1
    ");

    $stmt->execute([
        ':employee_no' => $employeeNo
    ]);

    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        throw new Exception(
            "Employee number $employeeNo was not found."
        );
    }

    $userId = $employee['user_id'];

    // ==================================================
    // 3. CONNECT TO ZKTECO K30
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

    // Retrieve attendance records
    $attendance = $zk->getAttendance();

    $zk->disconnect();
    $zk = null;

    if (!is_array($attendance)) {
        throw new Exception(
            "Unable to retrieve attendance records."
        );
    }

    // ==================================================
    // 4. FILTER EMPLOYEE ATTENDANCE
    // ==================================================

    $employeeRecords = [];

    foreach ($attendance as $record) {

        if (
            !isset(
                $record[1],
                $record[2],
                $record[3]
            )
        ) {
            continue;
        }

        // Match K30 AC-No. with MIS employee_no
        if ((string)$record[1] !== $employeeNo) {
            continue;
        }

        $state = (int)$record[2];

        // Accept only verified attendance states
        if (!in_array($state, [0, 1, 2, 3], true)) {
            continue;
        }

        $recordTimestamp = strtotime($record[3]);

        if ($recordTimestamp === false) {
            continue;
        }

        $employeeRecords[] = [
            'state' => $state,
            'punch_time' => date(
                'Y-m-d H:i:s',
                $recordTimestamp
            )
        ];
    }

    // Sort records oldest to newest
    usort(
        $employeeRecords,
        function ($a, $b) {
            return strcmp(
                $a['punch_time'],
                $b['punch_time']
            );
        }
    );

    // ==================================================
    // 5. CORRECT PUNCH TYPE MAPPING
    // ==================================================

    $punchTypes = [
        0 => 'Check In',
        1 => 'Check Out',
        2 => 'Break Out',
        3 => 'Break In'
    ];

    // ==================================================
    // 6. PRESENCE STATUS MAPPING
    // ==================================================

    $presenceStatuses = [
        0 => 'Available', // Check In
        1 => 'Away',      // Check Out
        2 => 'Away',      // Break Out
        3 => 'Available'  // Break In
    ];

    // ==================================================
    // 7. INSERT ATTENDANCE LOGS
    // ==================================================

    $inserted = 0;
    $duplicates = 0;

    $conn->beginTransaction();

    // Check for duplicate attendance records
    $checkDuplicate = $conn->prepare("
        SELECT id
        FROM attendance_logs
        WHERE user_id = :user_id
          AND biometric_id = :biometric_id
          AND punch_time = :punch_time
          AND punch_type = :punch_type
        LIMIT 1
    ");

    // Insert new attendance record
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

    foreach ($employeeRecords as $record) {

        $state = $record['state'];

        $punchType = $punchTypes[$state];

        $params = [
            ':user_id' => $userId,
            ':biometric_id' => $employeeNo,
            ':punch_time' => $record['punch_time'],
            ':punch_type' => $punchType
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
    // 8. UPDATE MIS PRESENCE STATUS
    // ==================================================


// ==================================================
// 8. UPDATE MIS PRESENCE STATUS USING EMPLOYEE NO
// ==================================================

$statusMessage = 'No valid attendance records found.';
$newStatus = null;
$latestRecord = null;

if (!empty($employeeRecords)) {

    // Get latest biometric punch
    $latestRecord = end($employeeRecords);

    $latestState = $latestRecord['state'];

    $newStatus = $presenceStatuses[$latestState];

    // Update users table using employee_no
    // Protect employees with On Leave status
    $updateStatus = $conn->prepare("
        UPDATE users
        SET presence_status = :status
        WHERE employee_no = :employee_no
          AND LOWER(TRIM(presence_status)) <> 'on leave'
    ");

    $updateStatus->execute([
        ':status' => $newStatus,
        ':employee_no' => $employeeNo
    ]);

    // Retrieve updated employee status
    $checkStatus = $conn->prepare("
        SELECT employee_no, presence_status
        FROM users
        WHERE employee_no = :employee_no
        LIMIT 1
    ");

    $checkStatus->execute([
        ':employee_no' => $employeeNo
    ]);

    $updatedEmployee = $checkStatus->fetch(PDO::FETCH_ASSOC);

    if (!$updatedEmployee) {
        throw new Exception("Employee number not found.");
    }

    if (
        strtolower(trim($updatedEmployee['presence_status'])) === 'on leave'
    ) {
        $statusMessage = 'Status protected. Employee is On Leave.';
    } else {
        $statusMessage =
            'Employee ' . $employeeNo .
            ' presence status updated to ' .
            $updatedEmployee['presence_status'] . '.';
    }
}


    // ==================================================
    // 9. COMMIT TRANSACTION
    // ==================================================

    $conn->commit();

    // ==================================================
    // 10. DISPLAY RESULTS
    // ==================================================

    echo "<h2>Biometric Synchronization</h2>";

    echo "<p><strong>Database:</strong> Connected</p>";

    echo "<p><strong>Employee No:</strong> " .
        htmlspecialchars($employeeNo) .
        "</p>";

    echo "<p><strong>User ID:</strong> " .
        htmlspecialchars((string)$userId) .
        "</p>";

    echo "<p><strong>Records Retrieved:</strong> " .
        count($employeeRecords) .
        "</p>";

    echo "<p><strong>New Logs Inserted:</strong> " .
        $inserted .
        "</p>";

    echo "<p><strong>Duplicate Logs Skipped:</strong> " .
        $duplicates .
        "</p>";

    echo "<hr>";

    echo "<h3>Latest Biometric Punch</h3>";

    if ($latestRecord !== null) {

        echo "<p><strong>Punch Type:</strong> " .
            htmlspecialchars(
                $punchTypes[$latestRecord['state']]
            ) .
            "</p>";

        echo "<p><strong>Raw State:</strong> " .
            htmlspecialchars(
                (string)$latestRecord['state']
            ) .
            "</p>";

        echo "<p><strong>Punch Time:</strong> " .
            htmlspecialchars(
                $latestRecord['punch_time']
            ) .
            "</p>";

        echo "<p><strong>Mapped Presence Status:</strong> " .
            htmlspecialchars($newStatus) .
            "</p>";

    } else {

        echo "<p>No attendance records found.</p>";
    }

    echo "<hr>";

    echo "<h3>Synchronization Result</h3>";

    echo "<p>" .
        htmlspecialchars($statusMessage) .
        "</p>";

    echo "<p><strong>Synchronization completed.</strong></p>";

} catch (Throwable $e) {

    // Disconnect device if necessary
    if ($zk !== null) {

        try {
            $zk->disconnect();
        } catch (Throwable $ignored) {
        }
    }

    // Roll back database changes
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
