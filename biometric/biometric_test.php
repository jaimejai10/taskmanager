
<?php

/**
 * ZKTeco K30 - MIS Biometric Integration Test
 *
 * PHP Version: 8.0+
 * Device: ZKTeco K30
 *
 * READ-ONLY TEST:
 * - Connects to biometric device
 * - Retrieves attendance records
 * - Filters employee number 1091
 * - Displays latest biometric punches
 * - Does not modify MySQL or presence_status
 */

require_once __DIR__ . '/vendor/autoload.php';

use TahaRafi\ZKTeco\ZKTeco;

// ==========================================
// DEVICE CONFIGURATION
// ==========================================

$deviceIp   = '150.150.150.150';
$devicePort = 4370;
$commKey    = 11126;

// ==========================================
// EMPLOYEE CONFIGURATION
// ==========================================

$targetEmployeeNo = '1091';
$targetEmployeeName = 'Jaime Segador';

// ==========================================
// INITIALIZE
// ==========================================

$zk = null;
$attendance = [];
$employeeRecords = [];
$errorMessage = null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>MIS - Biometric Attendance Test</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
            color: #333;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
        }

        h2 {
            margin-top: 0;
            color: #263b55;
        }

        .info-card {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            color: #16803c;
            font-weight: bold;
        }

        .error {
            color: #c62828;
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 5px;
            background: #dcfce7;
            color: #166534;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 11px;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #263b55;
            color: white;
        }

        tr:nth-child(even) {
            background: #f8fafc;
        }

        .raw {
            font-family: Consolas, monospace;
            font-size: 13px;
        }

        .refresh {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 10px 16px;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .notice {
            background: #fff7ed;
            border-left: 4px solid #f59e0b;
            padding: 12px;
            margin-top: 20px;
        }

        .footer {
            color: #777;
            font-size: 12px;
            margin-top: 25px;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>ZKTeco K30 - Biometric Attendance Test</h2>

    <div class="info-card">

        <strong>Device Information</strong><br><br>

        IP Address: <?= htmlspecialchars($deviceIp) ?><br>

        Port: <?= htmlspecialchars((string)$devicePort) ?><br>

        Employee No: <?= htmlspecialchars($targetEmployeeNo) ?><br>

        Employee Name: <?= htmlspecialchars($targetEmployeeName) ?>

    </div>

    <a href="biometric_test.php" class="refresh">
        Refresh Attendance
    </a>

    <?php

    try {

        // ======================================
        // CONNECT TO K30
        // ======================================

        $zk = new ZKTeco(
            $deviceIp,
            $devicePort,
            $commKey
        );

        echo '<p>Connecting to biometric device...</p>';

        if (!$zk->connect()) {
            throw new RuntimeException(
                'Unable to connect or authenticate with K30.'
            );
        }

        echo '<p class="success">
            Connected successfully!
        </p>';

        // ======================================
        // RETRIEVE ATTENDANCE
        // ======================================

        echo '<p>Retrieving biometric attendance...</p>';

        $attendance = $zk->getAttendance();

        if (!is_array($attendance)) {
            throw new RuntimeException(
                'Attendance retrieval did not return an array.'
            );
        }

        // ======================================
        // FILTER EMPLOYEE 1091
        // ======================================

        foreach ($attendance as $record) {

            if (!is_array($record)) {
                continue;
            }

            // Based on the retrieved raw record structure:
            // [0] = UID
            // [1] = Employee ID
            // [2] = Raw State
            // [3] = Punch Date/Time
            // [4] = Raw Punch Type

            $employeeId = $record[1] ?? null;

            if ((string)$employeeId === $targetEmployeeNo) {

                $employeeRecords[] = $record;
            }
        }

        // ======================================
        // SORT BY PUNCH DATE/TIME
        // ======================================

        usort($employeeRecords, function ($a, $b) {

            return strcmp(
                (string)($b[3] ?? ''),
                (string)($a[3] ?? '')
            );

        });

        // Display latest 30 records
        $latestRecords = array_slice(
            $employeeRecords,
            0,
            30
        );

        ?>

        <h3>Attendance Summary</h3>

        <p>
            Total device records:
            <strong><?= number_format(count($attendance)) ?></strong>
        </p>

        <p>
            Records belonging to employee 1091:
            <strong><?= number_format(count($employeeRecords)) ?></strong>
        </p>

        <p>
            Displaying latest:
            <strong><?= count($latestRecords) ?></strong>
            records
        </p>

        <span class="badge">
            Employee matched: 1091
        </span>

        <h3>Latest Biometric Punches</h3>

        <?php if (empty($latestRecords)): ?>

            <p class="error">
                No attendance records found for employee 1091.
            </p>

        <?php else: ?>

            <table>

                <thead>
                    <tr>
                        <th>#</th>
                        <th>UID</th>
                        <th>Employee No.</th>
                        <th>Raw State</th>
                        <th>Punch Date/Time</th>
                        <th>Raw Type</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($latestRecords as $index => $record): ?>

                    <tr>

                        <td><?= $index + 1 ?></td>

                        <td class="raw">
                            <?= htmlspecialchars((string)($record[0] ?? '')) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars((string)($record[1] ?? '')) ?>
                        </td>

                        <td class="raw">
                            <?= htmlspecialchars((string)($record[2] ?? '')) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars((string)($record[3] ?? '')) ?>
                        </td>

                        <td class="raw">
                            <?= htmlspecialchars((string)($record[4] ?? '')) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

        <div class="notice">

            <strong>Important:</strong>

            We are displaying raw biometric values only.
            We have not yet classified the punch types as
            Time In, lunch Out, Out Back, or final Time Out.

        </div>

        <?php

    } catch (Throwable $e) {

        echo '<p class="error">Error: '
            . htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            )
            . '</p>';

    } finally {

        // ======================================
        // DISCONNECT DEVICE
        // ======================================

        if ($zk !== null) {

            try {
                $zk->disconnect();
            } catch (Throwable $e) {
                // Ignore cleanup errors.
            }

        }

    }

    ?>

    <div class="footer">
        MIS Helpdesk | ZKTeco K30 Integration Test<br>
        Read-only mode - No database status updates performed.
    </div>

</div>

</body>
</html>
