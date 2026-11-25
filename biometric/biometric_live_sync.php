<?php
// ======================================================
// MIS HELPDESK - ZKTECO K30 LIVE SYNC ENDPOINT
// Called by monitor_live_biometric.php every 10 seconds.
// Keeps biometric_sync.php unchanged for manual full sync.
// ======================================================

session_start();
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Your session has expired. Please sign in again.']);
    exit;
}

require_once __DIR__ . '/../DB_connection.php';
require_once __DIR__ . '/vendor/autoload.php';

use TahaRafi\ZKTeco\ZKTeco;

$deviceIp = '150.150.150.150';
$devicePort = 4370;
$commKey = 11126;

$zk = null;
$lockAcquired = false;

$punchTypes = [
    0 => 'Check In',
    1 => 'Check Out',
    2 => 'Break Out',
    3 => 'Break In'
];

$presenceStatuses = [
    0 => 'Available',
    1 => 'Away',
    2 => 'Away',
    3 => 'Available'
];

try {
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new RuntimeException('Database connection is not available.');
    }

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Prevent overlapping device reads if a previous 10-second poll is still running.
    $lockStmt = $conn->query("SELECT GET_LOCK('mishelpdesk_zkteco_live_sync', 0)");
    $lockAcquired = ((int)$lockStmt->fetchColumn() === 1);

    if (!$lockAcquired) {
        echo json_encode([
            'ok' => true,
            'skipped' => true,
            'inserted' => 0,
            'message' => 'A biometric synchronization is already running.'
        ]);
        exit;
    }

    // Build the AC-No. => MIS user mapping.
    $employees = $conn->query("
        SELECT user_id, employee_no
        FROM users
        WHERE employee_no IS NOT NULL
          AND TRIM(employee_no) <> ''
    ")->fetchAll();

    $employeeMap = [];
    foreach ($employees as $employee) {
        $employeeMap[trim((string)$employee['employee_no'])] = (int)$employee['user_id'];
    }

    $zk = new ZKTeco($deviceIp, $devicePort, $commKey);

    if (!$zk->connect()) {
        throw new RuntimeException('Unable to connect to ZKTeco K30.');
    }

    $attendance = $zk->getAttendance();
    $zk->disconnect();
    $zk = null;

    if (!is_array($attendance)) {
        throw new RuntimeException('The device did not return attendance records.');
    }

    // Read each employee's latest imported punch time. The 2-minute overlap
    // catches delayed records and same-minute punches; duplicate checks below
    // prevent inserting the same attendance event twice.
    $latestStmt = $conn->query("
        SELECT user_id, MAX(punch_time) AS latest_punch
        FROM attendance_logs
        GROUP BY user_id
    ");

    $latestByUser = [];
    foreach ($latestStmt->fetchAll() as $row) {
        if (!empty($row['latest_punch'])) {
            $latestByUser[(int)$row['user_id']] = strtotime($row['latest_punch']);
        }
    }

    $checkDuplicate = $conn->prepare("
        SELECT id
        FROM attendance_logs
        WHERE user_id = :user_id
          AND biometric_id = :biometric_id
          AND punch_time = :punch_time
          AND punch_type = :punch_type
        LIMIT 1
    ");

    $insertLog = $conn->prepare("
        INSERT INTO attendance_logs
            (user_id, biometric_id, punch_time, punch_type)
        VALUES
            (:user_id, :biometric_id, :punch_time, :punch_type)
    ");

    $newestInsertedByUser = [];
    $inserted = 0;
    $duplicates = 0;
    $unmatched = 0;
    $invalid = 0;

    $conn->beginTransaction();

    foreach ($attendance as $record) {
        if (!isset($record[1], $record[2], $record[3])) {
            $invalid++;
            continue;
        }

        $employeeNo = trim((string)$record[1]);
        if (!isset($employeeMap[$employeeNo])) {
            $unmatched++;
            continue;
        }

        $state = (int)$record[2];
        if (!isset($punchTypes[$state])) {
            $invalid++;
            continue;
        }

        $timestamp = strtotime((string)$record[3]);
        if ($timestamp === false) {
            $invalid++;
            continue;
        }

        $userId = $employeeMap[$employeeNo];
        $punchTime = date('Y-m-d H:i:s', $timestamp);

        // Skip old history already covered by the initial full synchronization.
        // Keep a small overlap to catch late-arriving device records.
        if (isset($latestByUser[$userId]) && $timestamp < ($latestByUser[$userId] - 120)) {
            continue;
        }

        $params = [
            ':user_id' => $userId,
            ':biometric_id' => $employeeNo,
            ':punch_time' => $punchTime,
            ':punch_type' => $punchTypes[$state]
        ];

        $checkDuplicate->execute($params);
        if ($checkDuplicate->fetchColumn()) {
            $duplicates++;
            continue;
        }

        $insertLog->execute($params);
        $inserted++;

        if (
            !isset($newestInsertedByUser[$userId]) ||
            $timestamp > $newestInsertedByUser[$userId]['timestamp']
        ) {
            $newestInsertedByUser[$userId] = [
                'timestamp' => $timestamp,
                'employee_no' => $employeeNo,
                'state' => $state,
                'punch_type' => $punchTypes[$state],
                'punch_time' => $punchTime
            ];
        }
    }

    // Only a newly inserted punch can change a user's live presence.
    // Do not overwrite a manually-set On Leave status or an approved leave
    // that is active today.
    $updateStatus = $conn->prepare("
        UPDATE users
        SET presence_status = :presence_status
        WHERE user_id = :user_id
          AND LOWER(TRIM(COALESCE(presence_status, ''))) <> 'on leave'
          AND NOT EXISTS (
              SELECT 1
              FROM leave_requests lr
              WHERE lr.user_id = users.user_id
                AND LOWER(TRIM(lr.status)) = 'approved'
                AND CURDATE() BETWEEN lr.start_date AND lr.end_date
          )
    ");

    $updatedEmployees = 0;
    $protectedEmployees = 0;

    foreach ($newestInsertedByUser as $userId => $punch) {
        $updateStatus->execute([
            ':presence_status' => $presenceStatuses[$punch['state']],
            ':user_id' => $userId
        ]);

        if ($updateStatus->rowCount() > 0) {
            $updatedEmployees++;
        } else {
            $checkCurrent = $conn->prepare("
                SELECT
                    CASE
                        WHEN LOWER(TRIM(COALESCE(presence_status, ''))) = 'on leave'
                          OR EXISTS (
                              SELECT 1
                              FROM leave_requests lr
                              WHERE lr.user_id = users.user_id
                                AND LOWER(TRIM(lr.status)) = 'approved'
                                AND CURDATE() BETWEEN lr.start_date AND lr.end_date
                          )
                        THEN 1 ELSE 0
                    END
                FROM users
                WHERE user_id = ?
                LIMIT 1
            ");
            $checkCurrent->execute([$userId]);
            if ((int)$checkCurrent->fetchColumn() === 1) {
                $protectedEmployees++;
            }
        }
    }

    $conn->commit();

    echo json_encode([
        'ok' => true,
        'device' => 'ZKTeco K30',
        'device_records' => count($attendance),
        'inserted' => $inserted,
        'duplicates' => $duplicates,
        'unmatched' => $unmatched,
        'invalid' => $invalid,
        'employees_updated' => $updatedEmployees,
        'employees_protected' => $protectedEmployees,
        'synced_at' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $e) {
    if ($zk !== null) {
        try {
            $zk->disconnect();
        } catch (Throwable $ignored) {
        }
    }

    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Biometric live sync error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Biometric synchronization failed. Check the PHP error log and device connection.'
    ]);
} finally {
    if ($lockAcquired && isset($conn) && $conn instanceof PDO) {
        try {
            $conn->query("SELECT RELEASE_LOCK('mishelpdesk_zkteco_live_sync')");
        } catch (Throwable $ignored) {
        }
    }
}
