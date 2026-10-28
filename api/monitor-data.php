
<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'Please log in first.'
    ]);
    exit;
}

require_once dirname(__DIR__) . '/DB_connection.php';

function respond($data, $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function fetchRows(PDO $conn, $sql, $params = [])
{
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function tableExists(PDO $conn, $table)
{
    $stmt = $conn->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);

    return (bool) $stmt->fetchColumn();
}

function getPeriodStart($period)
{
    $now = new DateTimeImmutable('now');

    switch ($period) {
        case 'week':
            return $now->modify('monday this week')
                       ->setTime(0, 0, 0)
                       ->format('Y-m-d H:i:s');

        case 'month':
            return $now->modify('first day of this month')
                       ->setTime(0, 0, 0)
                       ->format('Y-m-d H:i:s');

        case 'year':
            return $now->setDate(
                (int) $now->format('Y'),
                1,
                1
            )->setTime(0, 0, 0)
             ->format('Y-m-d H:i:s');

        case 'today':
        default:
            return $now->setTime(0, 0, 0)
                       ->format('Y-m-d H:i:s');
    }
}

try {
    /*
     * 1. SUMMARY COUNTS
     * Only these four values use the selected date period.
     */

    $period = $_GET['period'] ?? 'today';

    $allowedPeriods = [
        'today',
        'week',
        'month',
        'year'
    ];

    if (!in_array($period, $allowedPeriods, true)) {
        $period = 'today';
    }

    $periodStart = getPeriodStart($period);

    $summary = fetchRows(
        $conn,
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(
                CASE
                    WHEN LOWER(TRIM(status)) = 'completed'
                    THEN 1 ELSE 0
                END
            ), 0) AS completed,
            COALESCE(SUM(
                CASE
                    WHEN LOWER(TRIM(status)) = 'pending'
                    THEN 1 ELSE 0
                END
            ), 0) AS pending,
            COALESCE(SUM(
                CASE
                    WHEN LOWER(TRIM(status))
                         IN ('in progress', 'ongoing')
                    THEN 1 ELSE 0
                END
            ), 0) AS in_progress
         FROM tasks
         WHERE created_at >= ?",
        [$periodStart]
    );

    $metrics = [
        'total'       => (int) ($summary[0]['total'] ?? 0),
        'completed'   => (int) ($summary[0]['completed'] ?? 0),
        'pending'     => (int) ($summary[0]['pending'] ?? 0),
        'in_progress' => (int) ($summary[0]['in_progress'] ?? 0)
    ];


    /*
     * 2. LATEST 10 REQUESTS
     * Independent of the summary date filter.
     */

    $requests = fetchRows(
        $conn,
        "SELECT
            t.task_id,
            t.report_id,
            t.title,
            t.category,
            t.status,
            t.requested_by,
            t.created_at,
            c.comp_name AS company_name
         FROM tasks t
         LEFT JOIN companies c
            ON c.comp_id = t.company_id
         ORDER BY t.created_at DESC, t.task_id DESC
         LIMIT 10"
    );

    foreach ($requests as &$request) {
        $status = strtolower(
            trim($request['status'] ?? '')
        );

        if ($status === 'new') {
            $displayStatus = 'New';
        } elseif ($status === 'pending') {
            $displayStatus = 'Pending';
        } elseif (in_array(
            $status,
            ['assigned', 'in progress', 'ongoing'],
            true
        )) {
            $displayStatus = 'Ongoing';
        } elseif ($status === 'completed') {
            $displayStatus = 'Completed';
        } else {
            $displayStatus = ucfirst($status ?: 'Pending');
        }

        $request['display_status'] = $displayStatus;
    }

    unset($request);


    /*
     * 3. ATTENTION NEEDED
     * Overdue tasks and unassigned open tasks.
     */

    $attention = [];

    $overdueTasks = fetchRows(
        $conn,
        "SELECT report_id, title, due_date
         FROM tasks
         WHERE due_date IS NOT NULL
           AND due_date < NOW()
           AND LOWER(TRIM(status))
               NOT IN ('completed', 'cancelled')
         ORDER BY due_date ASC
         LIMIT 5"
    );

    foreach ($overdueTasks as $task) {
        $attention[] = [
            'type' => 'Overdue',
            'title' => 'Overdue: ' .
                ($task['title'] ?: 'Untitled task'),
            'detail' =>
                (!empty($task['report_id'])
                    ? 'Report ' . $task['report_id'] . ' · '
                    : '') .
                'Due ' . $task['due_date']
        ];
    }

    $unassignedTasks = fetchRows(
        $conn,
        "SELECT report_id, title, created_at
         FROM tasks
         WHERE (assigned_id IS NULL OR assigned_id = 0)
           AND LOWER(TRIM(status))
               NOT IN ('completed', 'cancelled')
         ORDER BY created_at ASC
         LIMIT 5"
    );

    foreach ($unassignedTasks as $task) {
        $attention[] = [
            'type' => 'Unassigned',
            'title' => 'Unassigned: ' .
                ($task['title'] ?: 'Untitled task'),
            'detail' =>
                (!empty($task['report_id'])
                    ? 'Report ' . $task['report_id'] . ' · '
                    : '') .
                'Received ' . $task['created_at']
        ];
    }

    // Show no more than 8 attention items.
    $attention = array_slice($attention, 0, 8);


    /*
     * 4. INCOMING LEAVE
     *
     * This checks for an existing leave_requests table.
     * It does not create a table.
     */

    $leave = [];

    if (tableExists($conn, 'leave_requests')) {
        $leave = fetchRows(
            $conn,
            "SELECT
                u.full_name,
                l.status,
                l.start_date,
                l.end_date
             FROM leave_requests l
             LEFT JOIN users u
                ON u.user_id = l.user_id
             WHERE LOWER(l.status) = 'pending'
                OR (
                    LOWER(l.status) = 'approved'
                    AND l.end_date >= CURDATE()
                )
             ORDER BY
                CASE
                    WHEN LOWER(l.status) = 'pending'
                    THEN 0 ELSE 1
                END,
                l.start_date ASC
             LIMIT 6"
        );
    }


    /*
     * 5. NETWORK STATUS
     *
     * Network checks will use fixed office IP addresses
     * from monitor-config.php, which we will configure next.
     */

    $network = [];
    $networkSummary = 'Network configuration pending';
    $internetResponse = 'Internet connectivity not checked yet';

    $configFile = dirname(__DIR__) . '/monitor-config.php';

    if (file_exists($configFile)) {
        require $configFile;

        if (isset($MONITOR_NETWORK_TARGETS)
            && is_array($MONITOR_NETWORK_TARGETS)) {

            foreach ($MONITOR_NETWORK_TARGETS as $target) {
                $address = $target['address'] ?? '';
                $name = $target['name'] ?? 'Network device';
                $status = 'Unknown';

                if (
                    filter_var($address, FILTER_VALIDATE_IP)
                    && function_exists('exec')
                ) {
                    if (PHP_OS_FAMILY === 'Windows') {
                        $command = 'ping -n 1 -w 900 ' .
                            escapeshellarg($address);
                    } else {
                        $command = '/bin/ping -c 1 -W 1 ' .
                            escapeshellarg($address);
                    }

                    $output = [];
                    $exitCode = 1;

                    @exec(
                        $command . ' 2>&1',
                        $output,
                        $exitCode
                    );

                    $status = ($exitCode === 0)
                        ? 'Online'
                        : 'Offline';
                }

                $network[] = [
                    'name' => $name,
                    'address' => $address,
                    'status' => $status
                ];
            }

            $onlineCount = 0;

            foreach ($network as $device) {
                if ($device['status'] === 'Online') {
                    $onlineCount++;
                }
            }

            $networkSummary = count($network)
                ? $onlineCount . ' / ' .
                  count($network) . ' devices online'
                : 'No network targets configured';
        }

        /*
         * Connectivity check only; not a bandwidth test.
         */

        if (
            isset($MONITOR_INTERNET_CHECK_URL)
            && filter_var(
                $MONITOR_INTERNET_CHECK_URL,
                FILTER_VALIDATE_URL
            )
        ) {
            $url = $MONITOR_INTERNET_CHECK_URL;

            if (in_array(
                parse_url($url, PHP_URL_SCHEME),
                ['http', 'https'],
                true
            )) {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 2,
                        'ignore_errors' => true
                    ],
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true
                    ]
                ]);

                $startTime = microtime(true);

                $headers = @get_headers(
                    $url,
                    false,
                    $context
                );

                $milliseconds = (int) round(
                    (microtime(true) - $startTime) * 1000
                );

                $internetResponse = ($headers !== false)
                    ? 'Internet online · Response ' .
                      $milliseconds . ' ms'
                    : 'Internet offline or check failed';
            }
        }
    }


    /*
     * 6. RETURN DATA TO THE MONITORING PAGE
     */

    respond([
        'ok' => true,
        'period' => $period,
        'metrics' => $metrics,
        'requests' => $requests,
        'attention' => $attention,
        'leave' => $leave,
        'network' => $network,
        'network_summary' => $networkSummary,
        'internet_response' => $internetResponse,
        'updated_at' => date('Y-m-d H:i:s')
    ]);

} catch (Throwable $e) {
    error_log(
        'MIS monitoring endpoint error: ' .
        $e->getMessage()
    );

    respond([
        'ok' => false,
        'error' => 'Unable to load monitoring data. ' .
                   'Check the database connection and schema.'
    ], 500);
}
