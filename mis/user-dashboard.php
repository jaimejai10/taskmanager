<?php
// MIS User Dashboard
// Save as: C:\xampp\htdocs\taskmanager\MIS\user-dashboard.php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../DB_connection.php'; // Must define PDO variable: $conn

if (!isset($conn) || !($conn instanceof PDO)) {
    exit('DB_connection.php must provide a PDO connection named $conn.');
}
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'MIS User';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function h($v)
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
function reply($ok, $message, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}
function columns(PDO $db, string $table): array
{
    return array_column($db->query("SHOW COLUMNS FROM `$table`")->fetchAll(), 'Field');
}
function pick(array $cols, array $names): ?string
{
    foreach ($names as $name) if (in_array($name, $cols, true)) return $name;
    return null;
}
function attachmentUrl($storedPath): ?string
{
    $path = trim((string)($storedPath ?? ''));
    if ($path === '' || str_contains($path, '\\') || str_starts_with($path, '/') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)) return null;
    $parts = explode('/', $path);
    if (in_array('..', $parts, true) || in_array('.', $parts, true)) return null;
    $parts = array_values(array_filter($parts, static fn($part) => $part !== ''));
    if (!$parts) return null;
    return '../' . implode('/', array_map('rawurlencode', $parts));
}

try {
    $tc = columns($conn, 'tasks');
    $uc = columns($conn, 'users');
    $cc = columns($conn, 'companies');

    $assignCol = pick($tc, ['assigned_to_id', 'assigned_id']);
    $uidCol = pick($uc, ['user_id', 'id']);
    $unameCol = pick($uc, ['fullname', 'full_name', 'name', 'username']);
    $roleCol = pick($uc, ['role']);
    $companyFk = pick($tc, ['company_id', 'comp_id']);
    $companyId = pick($cc, ['comp_id', 'company_id', 'id']);
    $companyName = pick($cc, ['comp_name', 'company_name', 'name']);

    foreach (['task_id', 'status'] as $required) {
        if (!in_array($required, $tc, true)) throw new RuntimeException("tasks.$required column is required.");
    }
    if (!$assignCol || !$uidCol || !$unameCol) {
        throw new RuntimeException('Cannot detect assignment/user columns. Expected tasks.assigned_to_id or assigned_id, plus users ID and name columns.');
    }

    $role = '';
    if ($roleCol) {
        $roleCheck = $conn->prepare("SELECT `$roleCol` FROM users WHERE `$uidCol` = ?");
        $roleCheck->execute([$userId]);
        $role = strtolower((string)$roleCheck->fetchColumn());
        if (!$role) throw new RuntimeException('Logged-in user was not found in users table.');
        if (!in_array($role, ['misuser', 'mishead', 'admin', 'administrator'], true)) {
            http_response_code(403);
            exit('Access denied.');
        }
    }

    $fields = [
        'report_id' => pick($tc, ['report_id', 'service_report_no']),
        'title' => pick($tc, ['title', 'task_title']),
        'description' => pick($tc, ['description', 'problem_details']),
        'created_at' => pick($tc, ['created_at', 'date_requested', 'request_date']),
        'due_date' => pick($tc, ['due_date']),
        'requested_by' => pick($tc, ['requested_by']),
        'requester_no' => pick($tc, ['requester_no']),
        'diagnosis' => pick($tc, ['diagnosis']),
        'action_taken' => pick($tc, ['action_taken']),
        'recommendation' => pick($tc, ['recommendation']),
        'remarks' => pick($tc, ['remarks']),
        'attachment' => pick($tc, ['attachment_path', 'attachment'])
    ];
    $hasCompany = $companyFk && $companyId && $companyName;
    $joinCompany = $hasCompany ? "LEFT JOIN companies c ON c.`$companyId` = t.`$companyFk`" : '';
    $companySelect = $hasCompany ? "c.`$companyName`" : "''";
    $select = "SELECT t.task_id, t.status, t.`$assignCol` AS assigned_user_id,
        u.`$unameCol` AS assigned_name, $companySelect AS company_name";
    foreach ($fields as $alias => $column) {
        $select .= $column ? ", t.`$column` AS `$alias`" : ", NULL AS `$alias`";
    }
    $from = " FROM tasks t LEFT JOIN users u ON u.`$uidCol` = t.`$assignCol` $joinCompany";

    // Handle updates and reassignment through POST.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if (!$taskId) reply(false, 'Invalid task.', 400);

        $q = $conn->prepare("SELECT task_id, status, `$assignCol` AS assigned_user_id FROM tasks WHERE task_id = ?");
        $q->execute([$taskId]);
        $task = $q->fetch();
        if (!$task) reply(false, 'Task not found.', 404);

        if (($_POST['action'] ?? '') === 'accept_task') {
            // Any Pending task can be claimed, even if it is currently assigned
            // to another technician. The conditional UPDATE ensures only one
            // technician can accept it while its status is still Pending.
            $accept = $conn->prepare("UPDATE tasks SET `$assignCol` = ?, status = 'In Progress'
                WHERE task_id = ? AND LOWER(TRIM(status)) = 'pending'");
            $accept->execute([$userId, $taskId]);
            if ($accept->rowCount() !== 1) {
                reply(false, 'This task is no longer pending. Another technician may already have accepted it. Refresh the dashboard.', 409);
            }
            reply(true, 'Task accepted and assigned to you. Status changed to In Progress.');
        }

        if (($_POST['action'] ?? '') === 'update_task') {
            // Server-side authorization: must be assigned to the logged-in user.
            if ((int)$task['assigned_user_id'] !== $userId) {
                reply(false, 'You can update only tasks assigned to you.', 403);
            }
            $current = strtolower(trim((string)$task['status']));
            $status = trim($_POST['status'] ?? '');
            $transitions = [
                'in progress' => ['Complete', 'Incomplete', 'On Hold'],
                'incomplete'  => ['In Progress'],
                'on hold'     => ['In Progress']
            ];
            if (!in_array($status, $transitions[$current] ?? [], true)) {
                reply(false, "That status change is not allowed for the task current status.", 422);
            }

            // Only save fields relevant to the selected status. This preserves
            // previous notes when a task is resumed to In Progress.
            $values = [];
            foreach (['diagnosis', 'action_taken', 'recommendation', 'remarks'] as $key) {
                $values[$key] = trim($_POST[$key] ?? '');
            }

            $fieldsToSave = [];
            if ($status === 'Complete') {
                $fieldsToSave = ['diagnosis', 'action_taken'];
            } elseif ($status === 'Incomplete') {
                $fieldsToSave = ['diagnosis', 'action_taken', 'recommendation'];
            } elseif ($status === 'On Hold') {
                // Reuse the existing recommendation column for the hold reason;
                // this avoids requiring a separate remarks database column.
                $fieldsToSave = ['diagnosis', 'recommendation'];
            }

            $requiredMessages = [
                'diagnosis' => 'Please provide the Diagnosis.',
                'action_taken' => 'Please provide the Action Taken.',
                'recommendation' => ($status === 'On Hold')
                    ? 'Please provide the reason why this task is On Hold.'
                    : 'Please provide the reason why this task is Incomplete.',
                'remarks' => 'Please provide the Remarks.'
            ];

            foreach ($fieldsToSave as $key) {
                if (!$fields[$key]) {
                    reply(false, "The database column for " . str_replace('_', ' ', $key) . " is missing. Please check your tasks table.", 500);
                }
                if ($values[$key] === '') {
                    reply(false, $requiredMessages[$key], 422);
                }
            }

            $set = ['status = ?'];
            $params = [$status];

            foreach ($fieldsToSave as $key) {
                $set[] = "`{$fields[$key]}` = ?";
                $params[] = $values[$key];
            }

            $params[] = $taskId;
            $params[] = $userId;
            $up = $conn->prepare(
                "UPDATE tasks SET " . implode(', ', $set) .
                    " WHERE task_id = ? AND `$assignCol` = ?"
            );
            $up->execute($params);

            // Notify the requester only after a successful status update.
            // Acceptance intentionally has no SMS, and requester_no is the only
            // permitted destination (no company mobile fallback).
            if ($up->rowCount() === 1 && in_array($status, ['Complete', 'Incomplete', 'On Hold'], true)) {
                $requesterNumber = '';
                $notificationDetails = [
                    'report_no' => $taskId,
                    'task_title' => 'MIS Service Request'
                ];

                $notifySelect = [];
                $notifySelect[] = $fields['requester_no']
                    ? "t.`{$fields['requester_no']}` AS requester_no"
                    : "NULL AS requester_no";
                $notifySelect[] = $fields['report_id']
                    ? "t.`{$fields['report_id']}` AS report_no"
                    : "t.task_id AS report_no";
                $notifySelect[] = $fields['title']
                    ? "t.`{$fields['title']}` AS task_title"
                    : "'' AS task_title";

                $notifyQuery = $conn->prepare(
                    "SELECT " . implode(', ', $notifySelect) . " FROM tasks t WHERE t.task_id = ?"
                );
                $notifyQuery->execute([$taskId]);
                $notificationDetails = $notifyQuery->fetch() ?: $notificationDetails;
                $requesterNumber = trim((string)($notificationDetails['requester_no'] ?? ''));

                if ($requesterNumber !== '') {
                    try {
                        require_once __DIR__ . '/../includes/sms.php';

                        if (function_exists('send_sms')) {
                            $phone = preg_replace('/[^0-9+]/', '', $requesterNumber);

                            if (str_starts_with($phone, '+63')) {
                                $phone = '63' . substr($phone, 3);
                            } elseif (str_starts_with($phone, '09')) {
                                $phone = '63' . substr($phone, 1);
                            } elseif (str_starts_with($phone, '9') && strlen($phone) === 10) {
                                $phone = '63' . $phone;
                            }

                            if (preg_match('/^639[0-9]{9}$/', $phone)) {
                                $reportNo = (string)($notificationDetails['report_no'] ?? $taskId);
                                $taskTitle = trim((string)($notificationDetails['task_title'] ?? ''));
                                if ($taskTitle === '') {
                                    $taskTitle = 'MIS Service Request';
                                }

                                if ($status === 'Complete') {
                                    $message = "MIS HELPDESK: Your request (SR No: {$reportNo} – {$taskTitle}) has been successfully completed. Our MIS team has resolved the reported issue. Thank you for your patience.";
                                } elseif ($status === 'Incomplete') {
                                    $reason = trim((string)($values['recommendation'] ?? ''));
                                    $message = "MIS HELPDESK: Your request (SR No: {$reportNo} – {$taskTitle}) is marked as INCOMPLETE. Reason: {$reason}. Please coordinate with the MIS team for the next steps. Thank you.";
                                } else {
                                    $reason = trim((string)($values['recommendation'] ?? ''));
                                    $message = "MIS HELPDESK: Your request (SR No: {$reportNo} – {$taskTitle}) is temporarily ON HOLD. Reason: {$reason}. We will provide an update once work resumes. Thank you for your understanding.";
                                }

                                if (send_sms($phone, $message) === false) {
                                    error_log("MIS status SMS failed for task ID {$taskId} (status: {$status}).");
                                }
                            } else {
                                error_log("MIS status SMS skipped: invalid requester_no for task ID {$taskId}.");
                            }
                        } else {
                            error_log('MIS status SMS skipped: send_sms() function is unavailable.');
                        }
                    } catch (Throwable $smsError) {
                        // Notification failure must never undo the saved task update.
                        error_log('MIS status SMS error: ' . $smsError->getMessage());
                    }
                }
            }

            reply(true, 'Task updated successfully.');
        }

        if (($_POST['action'] ?? '') === 'delete_task') {
            $submittedToken = (string)($_POST['csrf_token'] ?? '');
            if (!hash_equals($csrfToken, $submittedToken)) {
                reply(false, 'Security token expired. Refresh the page and try again.', 403);
            }

            // MIS Head/Admin may delete any task. MIS Users may delete only
            // tasks currently assigned to their own account.
            $canDeleteAny = in_array($role, ['mishead', 'admin', 'administrator'], true);
            $isAssignedToMe = (int)$task['assigned_user_id'] === $userId;
            if (!$canDeleteAny && !$isAssignedToMe) {
                reply(false, 'You can delete only tasks assigned to you. Contact MIS Head/Admin to delete other tasks.', 403);
            }

            $delete = $conn->prepare('DELETE FROM tasks WHERE task_id = ?');
            $delete->execute([$taskId]);
            if ($delete->rowCount() !== 1) {
                reply(false, 'The task could not be deleted. It may have already been removed.', 409);
            }
            reply(true, 'Task deleted successfully.');
        }

        if (($_POST['action'] ?? '') === 'reassign_task') {
            $target = (int)($_POST['assigned_user_id'] ?? 0);
            if (!$target) reply(false, 'Choose an MIS user.', 422);
            $sql = "SELECT `$uidCol` FROM users WHERE `$uidCol` = ?";
            if ($roleCol) $sql .= " AND LOWER(`$roleCol`) IN ('misuser','mishead')";
            $check = $conn->prepare($sql);
            $check->execute([$target]);
            if (!$check->fetchColumn()) reply(false, 'Selected account is not an MIS user.', 422);
            $oldAssignedId = (int)($task['assigned_user_id'] ?? 0);

            // Reassigning to the same technician is not a transfer.
            if ($oldAssignedId === $target) {
                reply(true, 'This task is already assigned to the selected MIS user. No changes were made.');
            }

            // Get requester contact and technician names before updating assignment.
            $detailSelect = [];
            $detailSelect[] = $fields['report_id']
                ? "t.`{$fields['report_id']}` AS report_no"
                : "t.task_id AS report_no";
            $detailSelect[] = $fields['title']
                ? "t.`{$fields['title']}` AS task_title"
                : "'' AS task_title";
            $detailSelect[] = $fields['requester_no']
                ? "t.`{$fields['requester_no']}` AS requester_no"
                : "NULL AS requester_no";
            $detailSelect[] = "old_u.`$unameCol` AS previous_assigned_name";
            $detailSelect[] = "new_u.`$unameCol` AS new_assigned_name";

            $detailSql = "SELECT " . implode(', ', $detailSelect) . "
                FROM tasks t
                LEFT JOIN users old_u ON old_u.`$uidCol` = t.`$assignCol`
                LEFT JOIN users new_u ON new_u.`$uidCol` = ?
                WHERE t.task_id = ?";
            $detailQuery = $conn->prepare($detailSql);
            $detailQuery->execute([$target, $taskId]);
            $taskDetails = $detailQuery->fetch();

            // Update only if the assignment has not changed since it was read.
            // This prevents a stale reassignment request from overwriting a newer one.
            if ($task['assigned_user_id'] === null) {
                $up = $conn->prepare(
                    "UPDATE tasks SET `$assignCol` = ? WHERE task_id = ? AND `$assignCol` IS NULL"
                );
                $up->execute([$target, $taskId]);
            } else {
                $up = $conn->prepare(
                    "UPDATE tasks SET `$assignCol` = ? WHERE task_id = ? AND `$assignCol` = ?"
                );
                $up->execute([$target, $taskId, $task['assigned_user_id']]);
            }

            if ($up->rowCount() !== 1) {
                reply(false, 'The task assignment changed before it could be saved. Refresh the dashboard and try again.', 409);
            }

            // Notify the requester only after a confirmed transfer.
            // Accepting a task intentionally has no SMS notification.
            // SMS uses requester_no only; company mobile numbers are never used as fallback.
            $requesterNumber = trim((string)($taskDetails['requester_no'] ?? ''));
            if ($requesterNumber !== '') {
                try {
                    require_once __DIR__ . '/../includes/sms.php';

                    if (function_exists('send_sms')) {
                        $phone = preg_replace('/[^0-9+]/', '', $requesterNumber);

                        if (str_starts_with($phone, '+63')) {
                            $phone = '63' . substr($phone, 3);
                        } elseif (str_starts_with($phone, '09')) {
                            $phone = '63' . substr($phone, 1);
                        } elseif (str_starts_with($phone, '9') && strlen($phone) === 10) {
                            $phone = '63' . $phone;
                        }

                        if (preg_match('/^639[0-9]{9}$/', $phone)) {
                            $message = "MIS HELPDESK: Your request (SR No: "
                                . ($taskDetails['report_no'] ?? $taskId)
                                . " – " . ($taskDetails['task_title'] ?? 'MIS Service Request')
                                . ") has been reassigned from "
                                . ($taskDetails['previous_assigned_name'] ?: 'MIS Team')
                                . " to "
                                . ($taskDetails['new_assigned_name'] ?: 'MIS Team')
                                . " for continued assistance. Thank you.";

                            if (send_sms($phone, $message) === false) {
                                error_log("MIS reassignment SMS failed for task ID {$taskId}.");
                            }
                        } else {
                            error_log("MIS reassignment SMS skipped: invalid requester_no for task ID {$taskId}.");
                        }
                    } else {
                        error_log('MIS reassignment SMS skipped: send_sms() function is unavailable.');
                    }
                } catch (Throwable $smsError) {
                    // A notification failure must never undo the saved reassignment.
                    error_log('MIS reassignment SMS error: ' . $smsError->getMessage());
                }
            }

            reply(true, 'Task reassigned successfully.');
        }
        reply(false, 'Unknown action.', 400);
    }

    // Build one shared filter set for the paginated grid and CSV export.
    $where = [];
    $filterParams = [];
    $searchTerm = trim((string)($_GET['search'] ?? ''));
    $statusFilter = trim((string)($_GET['status'] ?? ''));
    $companyFilter = trim((string)($_GET['company'] ?? ''));
    $dateFrom = trim((string)($_GET['from'] ?? ''));
    $dateTo = trim((string)($_GET['to'] ?? ''));
    $mineFilter = trim((string)($_GET['mine_filter'] ?? ''));

    if ($searchTerm !== '') {
        $searchParts = [];
        foreach (['report_id' => $fields['report_id'], 'title' => $fields['title'], 'description' => $fields['description']] as $alias => $column) {
            if ($column) {
                $searchParts[] = "t.`$column` LIKE ?";
                $filterParams[] = '%' . $searchTerm . '%';
            }
        }
        if ($hasCompany) {
            $searchParts[] = "c.`$companyName` LIKE ?";
            $filterParams[] = '%' . $searchTerm . '%';
        }
        if ($searchParts) $where[] = '(' . implode(' OR ', $searchParts) . ')';
    }
    if ($statusFilter !== '') {
        $where[] = 'LOWER(TRIM(t.status)) = LOWER(TRIM(?))';
        $filterParams[] = $statusFilter;
    }
    if ($hasCompany && $companyFilter !== '') {
        $where[] = "c.`$companyName` = ?";
        $filterParams[] = $companyFilter;
    }
    if ($fields['created_at'] && $dateFrom !== '') {
        $where[] = "DATE(t.`{$fields['created_at']}`) >= ?";
        $filterParams[] = $dateFrom;
    }
    if ($fields['created_at'] && $dateTo !== '') {
        $where[] = "DATE(t.`{$fields['created_at']}`) <= ?";
        $filterParams[] = $dateTo;
    }

    // Keep the dashboard metric cards accurate across the full task history.
    $dueMetric = $fields['due_date'] ? "SUM(CASE WHEN t.`{$fields['due_date']}` IS NOT NULL AND t.`{$fields['due_date']}` < CURDATE() AND LOWER(TRIM(t.status)) NOT IN ('complete','completed','cancelled') THEN 1 ELSE 0 END)" : '0';
    $metricSql = "SELECT COUNT(*) AS total,
        SUM(CASE WHEN LOWER(TRIM(t.status)) IN ('complete','completed') THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN LOWER(TRIM(t.status)) = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN LOWER(TRIM(t.status)) IN ('in progress','in_progress') THEN 1 ELSE 0 END) AS progress,
        SUM(CASE WHEN LOWER(TRIM(t.status)) = 'on hold' THEN 1 ELSE 0 END) AS on_hold,
        $dueMetric AS overdue
        FROM tasks t WHERE t.`$assignCol` = ?";
    $metricStmt = $conn->prepare($metricSql);
    $metricStmt->execute([$userId]);
    $metricRow = $metricStmt->fetch() ?: [];
    $counts = [
        'total' => (int)($metricRow['total'] ?? 0),
        'completed' => (int)($metricRow['completed'] ?? 0),
        'pending' => (int)($metricRow['pending'] ?? 0),
        'progress' => (int)($metricRow['progress'] ?? 0),
        'on_hold' => (int)($metricRow['on_hold'] ?? 0),
        'overdue' => (int)($metricRow['overdue'] ?? 0)
    ];

    // Optional task-card shortcuts are also applied by MySQL, not just the visible page.
    if ($mineFilter !== '') {
        // Every status card uses the same scope: tasks assigned to this user.
        $where[] = "t.`$assignCol` = ?";
        $filterParams[] = $userId;
        if ($mineFilter === 'completed') $where[] = "LOWER(TRIM(t.status)) IN ('complete','completed')";
        elseif ($mineFilter === 'pending') $where[] = "LOWER(TRIM(t.status)) = 'pending'";
        elseif ($mineFilter === 'progress') $where[] = "LOWER(TRIM(t.status)) IN ('in progress','in_progress')";
        elseif ($mineFilter === 'on_hold') $where[] = "LOWER(TRIM(t.status)) = 'on hold'";
        elseif ($mineFilter === 'overdue' && $fields['due_date']) $where[] = "t.`{$fields['due_date']}` IS NOT NULL AND t.`{$fields['due_date']}` < CURDATE() AND LOWER(TRIM(t.status)) NOT IN ('complete','completed','cancelled')";
        elseif (!in_array($mineFilter, ['all', 'completed', 'pending', 'progress', 'on_hold', 'overdue'], true)) array_pop($where);
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    // CSV export includes all records matching the active filters, not only the current page.
    if (($_GET['export'] ?? '') === 'excel') {
        $q = $conn->prepare($select . $from . $whereSql . ' ORDER BY t.task_id DESC');
        $q->execute($filterParams);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="MIS_All_Tasks_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Report No.', 'Date Requested', 'Company', 'Title', 'Description', 'Requested By', 'Assigned To', 'Status', 'Due Date', 'Diagnosis', 'Action Taken', 'Recommendation', 'Remarks', 'Attachment']);
        while ($r = $q->fetch()) {
            fputcsv($out, [$r['report_id'], $r['created_at'], $r['company_name'], $r['title'], $r['description'], $r['requested_by'], $r['assigned_name'], $r['status'], $r['due_date'], $r['diagnosis'], $r['action_taken'], $r['recommendation'], $r['remarks'], $r['attachment']]);
        }
        fclose($out);
        exit;
    }

    // Server-side pagination: only the requested page is retrieved from MySQL.
    $perPageOptions = [10, 25, 50, 100];
    $perPage = (int)($_GET['per_page'] ?? 25);
    if (!in_array($perPage, $perPageOptions, true)) $perPage = 25;
    $countStmt = $conn->prepare("SELECT COUNT(*)" . $from . $whereSql);
    $countStmt->execute($filterParams);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRecords / $perPage));
    $currentPageNumber = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
    $offset = ($currentPageNumber - 1) * $perPage;

    // Task queue priority:
    // 1 My Pending, 2 My In Progress, 3 My On Hold,
    // 4 Other Pending, 5 Other In Progress, 6 Other On Hold,
    // 7 My Complete, 8 Other Complete. Unlisted statuses follow afterward.
    $q = $conn->prepare($select . $from . $whereSql . "
        ORDER BY CASE
            WHEN t.`$assignCol` = ? THEN
                CASE
                    WHEN LOWER(TRIM(t.status)) = 'pending' THEN 0
                    WHEN LOWER(TRIM(t.status)) IN ('in progress', 'in_progress') THEN 1
                    WHEN LOWER(TRIM(t.status)) = 'on hold' THEN 2
                    WHEN LOWER(TRIM(t.status)) IN ('complete', 'completed') THEN 6
                    ELSE 8
                END
            ELSE
                CASE
                    WHEN LOWER(TRIM(t.status)) = 'pending' THEN 3
                    WHEN LOWER(TRIM(t.status)) IN ('in progress', 'in_progress') THEN 4
                    WHEN LOWER(TRIM(t.status)) = 'on hold' THEN 5
                    WHEN LOWER(TRIM(t.status)) IN ('complete', 'completed') THEN 7
                    ELSE 9
                END
        END,
        t.task_id DESC
        LIMIT $perPage OFFSET $offset");
    $q->execute(array_merge($filterParams, [$userId]));
    $tasks = $q->fetchAll();

    $userSql = "SELECT `$uidCol` AS user_id, `$unameCol` AS full_name FROM users";
    if ($roleCol) $userSql .= " WHERE LOWER(`$roleCol`) IN ('misuser','mishead')";
    $userSql .= " ORDER BY `$unameCol`";
    $misUsers = $conn->query($userSql)->fetchAll();
    $companies = ($hasCompany) ? $conn->query("SELECT DISTINCT `$companyName` AS company_name FROM companies ORDER BY `$companyName`")->fetchAll() : [];
} catch (Throwable $e) {
    error_log('MIS user dashboard: ' . $e->getMessage());
    http_response_code(500);
    exit('Dashboard setup error: ' . h($e->getMessage()) . '<br>Check table/column names in your database.');
}

$layoutRole = 'user';
$currentPage = 'dashboard';
$roleLabel = 'MIS USER';
$portalLabel = 'User Portal';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MIS User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin-dashboard.css">
    <style>
        /* User dashboard content styles layered on top of the shared MIS layout */
        .dashboard-content {
            padding: 22px 20px 30px
        }

        .user-welcome {
            display: flex;
            justify-content: space-between;
            align-items: end;
            margin-bottom: 20px;
            gap: 12px
        }

        .user-welcome h2 {
            font-size: 23px;
            font-weight: 800;
            margin: 0;
            color: var(--ink)
        }

        .user-welcome p {
            color: var(--muted);
            font-size: 13px;
            margin: 5px 0 0
        }

        .user-cards {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 20px
        }

        .user-metric {
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
            box-shadow: 0 3px 12px #1d355710;
            transition: transform .2s;
            cursor: pointer;
            color: var(--ink)
        }

        .user-metric:hover {
            transform: translateY(-3px)
        }

        .user-metric .ico {
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            border-radius: 11px;
            display: grid;
            place-items: center;
            font-size: 20px
        }

        .user-metric strong {
            display: block;
            font-size: 25px;
            line-height: 1.1
        }

        .user-metric span {
            font-size: 12px;
            color: var(--muted);
            font-weight: 700
        }

        .user-metric.blue .ico {
            background: #e8f1ff;
            color: #2563eb
        }

        .user-metric.green .ico {
            background: #e4f8ed;
            color: #159455
        }

        .user-metric.amber .ico {
            background: #fff2df;
            color: #d97706
        }

        .user-metric.purple .ico {
            background: #f1eaff;
            color: #7c3aed
        }

        .user-metric.red .ico {
            background: #ffe8e8;
            color: #dc2626
        }

        .user-metric.teal .ico {
            background: #e2f7f5;
            color: #0f766e
        }

        .user-task-panel {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 12px #1d35570a
        }

        .user-panel-head {
            padding: 17px 18px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px
        }

        .user-panel-head h3 {
            font-size: 16px;
            font-weight: 800;
            margin: 0
        }

        .user-panel-head p {
            font-size: 12px;
            color: var(--muted);
            margin: 4px 0 0
        }

        .user-filters {
            padding: 13px 18px;
            display: flex;
            gap: 9px;
            flex-wrap: wrap
        }

        .user-filters .form-control,
        .user-filters .form-select {
            max-width: 190px;
            font-size: 12px
        }

        .user-task-panel .table-responsive {
            overflow: auto
        }

        .user-task-panel table {
            min-width: 1360px;
            width: 1360px;
            table-layout: fixed
        }

        .user-task-panel thead th {
            background: #f4f7fc;
            color: #64748b;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .5px;
            white-space: nowrap;
            padding: 11px 9px
        }

        .user-task-panel tbody td {
            font-size: 12px;
            vertical-align: middle;
            padding: 10px 9px
        }

        .user-task-panel tbody tr:hover {
            background: #f9fbff
        }

        .user-task-panel .mine {
            background: #f4f8ff
        }

        .user-task-panel th:nth-child(1),
        .user-task-panel td:nth-child(1) {
            width: 105px
        }

        .user-task-panel th:nth-child(2),
        .user-task-panel td:nth-child(2) {
            width: 125px
        }

        .user-task-panel th:nth-child(3),
        .user-task-panel td:nth-child(3) {
            width: 210px
        }

        .user-task-panel th:nth-child(4),
        .user-task-panel td:nth-child(4) {
            width: 135px
        }

        .user-task-panel th:nth-child(5),
        .user-task-panel td:nth-child(5) {
            width: 155px
        }

        .user-task-panel th:nth-child(6),
        .user-task-panel td:nth-child(6) {
            width: 155px
        }

        .user-task-panel th:nth-child(7),
        .user-task-panel td:nth-child(7) {
            width: 110px
        }

        .user-task-panel th:nth-child(8),
        .user-task-panel td:nth-child(8) {
            width: 105px
        }

        .user-task-panel th:nth-child(9),
        .user-task-panel td:nth-child(9) {
            width: 260px
        }

        .user-task-panel tbody td {
            overflow-wrap: anywhere
        }

        .user-task-panel tbody td:nth-child(5),
        .user-task-panel tbody td:nth-child(6) {
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
            line-height: 1.4
        }

        .user-task-panel thead th:nth-child(5),
        .user-task-panel thead th:nth-child(6) {
            white-space: normal;
            line-height: 1.25
        }

        .task-issue-cell {
            max-width: 220px;
            white-space: normal;
            overflow-wrap: anywhere
        }

        .taskname {
            font-weight: 750;
            line-height: 1.35
        }

        .task-description {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            line-height: 1.35;
            margin-top: 3px
        }

        .attachment-preview-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            max-width: 100%;
            border: 0;
            background: transparent;
            padding: 0;
            color: #0d6efd;
            font-size: 12px;
            text-align: left;
            text-decoration: underline
        }

        .attachment-preview-btn span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap
        }

        .taskname {
            font-weight: 750
        }

        .mine-tag {
            font-size: 9px;
            color: #1d4ed8;
            background: #e3efff;
            border-radius: 5px;
            padding: 3px 5px;
            margin-left: 4px
        }

        .pill {
            font-size: 10px;
            font-weight: 800;
            border-radius: 20px;
            padding: 5px 9px;
            background: #eef2f7;
            color: #475569
        }

        .pill.pending {
            background: #fff3dc;
            color: #a16207
        }

        .pill.in-progress,
        .pill.assigned {
            background: #e8f1ff;
            color: #1d4ed8
        }

        .pill.completed,
        .pill.complete {
            background: #e5f8ed;
            color: #15803d
        }

        .pill.incomplete {
            background: #fff0e6;
            color: #c2410c
        }

        .pill.on-hold {
            background: #f1eaff;
            color: #6d28d9
        }

        .pill.cancelled {
            background: #ffe8e8;
            color: #b91c1c
        }

        .actions {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            min-width: max-content
        }

        .actions .btn {
            flex: 0 0 auto;
            white-space: nowrap
        }

        .user-task-panel .delete-btn {
            width: 30px;
            height: 30px;
            min-width: 30px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 30px
        }

        .user-task-panel .delete-btn i {
            font-size: 14px;
            line-height: 1
        }

        .user-task-panel .btn-sm {
            font-size: 11px;
            font-weight: 700;
            border-radius: 7px
        }

        .user-task-panel .pagination {
            --bs-pagination-font-size: 12px
        }

        .user-task-panel .page-link {
            color: #1d4ed8
        }

        .user-task-panel .page-item.active .page-link {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff
        }

        .modal-content {
            border: 0;
            border-radius: 14px;
            overflow: hidden
        }

        .modal-header {
            background: linear-gradient(110deg, var(--navy), var(--navy2));
            color: #fff
        }

        .modal-header .btn-close {
            filter: invert(1)
        }

        .modal-title {
            font-weight: 800;
            font-size: 16px
        }

        .form-label {
            font-size: 12px;
            font-weight: 700;
            color: #475569
        }

        .form-control,
        .form-select {
            font-size: 13px;
            border-radius: 8px;
            padding: 9px 10px;
            border-color: #dce4ee
        }

        /* Polished task-update modal */
        #updateModal .modal-dialog { max-width: 820px; }
        #updateModal .modal-content, #takeTaskModal .modal-content { border: 0; border-radius: 18px; overflow: hidden; box-shadow: 0 18px 55px #0f172a24; }
        #updateModal .modal-header, #takeTaskModal .modal-header { padding: 20px 24px; }
        #updateModal .modal-body { padding: 24px; }
        #updateModal .modal-footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e8edf4; }
        #updateModal .task-meta-card { padding: 15px 17px; border: 1px solid #e5eaf2; background: #f8fafc; border-radius: 12px; height: 100%; }
        #updateModal .task-meta-label { display:block; color:#64748b; font-size:10px; font-weight:800; letter-spacing:.07em; margin-bottom:5px; }
        #updateModal .form-label { margin-bottom:7px; }
        #updateModal .form-control, #updateModal .form-select { border-radius:10px; padding:11px 12px; }
        #updateModal textarea.form-control { min-height:105px; resize:vertical; }
        #updateModal .update-section-title { font-size:12px; font-weight:800; color:#334155; text-transform:uppercase; letter-spacing:.06em; margin:3px 0 12px; }
        #takeTaskModal .modal-body { padding:24px; }
        #takeTaskModal .take-task-warning { display:flex; gap:13px; padding:15px; border:1px solid #fde3b2; background:#fff8e8; border-radius:12px; }
        #takeTaskModal .take-task-warning .bi { font-size:22px; color:#d97706; }

        @media(max-width:1250px) {
            .user-cards {
                grid-template-columns: repeat(3, minmax(0, 1fr))
            }
        }

        @media(max-width:700px) {
            .dashboard-content {
                padding: 14px 10px
            }

            .user-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px
            }

            .user-metric {
                padding: 10px;
                gap: 8px
            }

            .user-metric strong {
                font-size: 21px
            }

            .user-welcome {
                display: block
            }

            .user-welcome>small {
                display: block;
                margin-top: 8px
            }

            .user-panel-head {
                padding: 13px
            }

            .user-filters {
                padding: 12px
            }

            .user-filters .form-control,
            .user-filters .form-select {
                max-width: none;
                flex: 1 1 140px
            }
        }
    </style>
</head>

<body>
    <div class="app-shell">
        <?php require __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-area">
            <?php require __DIR__ . '/../includes/header.php'; ?>
            <section class="dashboard-content">
                <div class="user-welcome">
                    <div>
                        <p>Manage your assigned work and view the MIS team's task queue.</p>
                    </div><small class="text-secondary">Updated <?= date('M d, Y') ?></small>
                </div>
                <section class="user-cards">
                    <button class="user-metric blue" data-card="all">
                        <div class="ico"><i class="bi bi-clipboard-data"></i></div>
                        <div><strong><?= $counts['total'] ?></strong><span>My Assigned Tasks</span></div>
                    </button>
                    <button class="user-metric green" data-card="completed">
                        <div class="ico"><i class="bi bi-check-circle"></i></div>
                        <div><strong><?= $counts['completed'] ?></strong><span>Completed</span></div>
                    </button>
                    <button class="user-metric amber" data-card="pending">
                        <div class="ico"><i class="bi bi-hourglass-split"></i></div>
                        <div><strong><?= $counts['pending'] ?></strong><span>Pending</span></div>
                    </button>
                    <button class="user-metric purple" data-card="progress">
                        <div class="ico"><i class="bi bi-gear"></i></div>
                        <div><strong><?= $counts['progress'] ?></strong><span>In Progress</span></div>
                    </button>
                    <button class="user-metric teal" data-card="on_hold">
                        <div class="ico"><i class="bi bi-pause-circle"></i></div>
                        <div><strong><?= $counts['on_hold'] ?></strong><span>On Hold</span></div>
                    </button>
                    <button class="user-metric red" data-card="overdue">
                        <div class="ico"><i class="bi bi-exclamation-triangle"></i></div>
                        <div><strong><?= $counts['overdue'] ?></strong><span>Overdue</span></div>
                    </button>
                </section>
                <section class="user-task-panel" id="taskGrid">
                    <div class="user-panel-head">
                        <div>
                            <h3><i class="bi bi-kanban me-2 text-primary"></i>All Tasks</h3>
                            <p>Your assigned tasks appear first. Accept any Pending task to assign it to yourself before updating it.</p>
                        </div><span class="badge text-bg-light"><?= number_format($totalRecords) ?> records</span>
                    </div>
                    <form class="user-filters" id="filterForm" method="get" action="">
                        <input type="hidden" name="page" value="1">
                        <input type="hidden" name="per_page" value="<?= h($perPage) ?>">
                        <?php if ($mineFilter !== ''): ?><input type="hidden" name="mine_filter" value="<?= h($mineFilter) ?>"><?php endif; ?>
                        <input class="form-control" name="search" id="search" value="<?= h($searchTerm) ?>" placeholder="Search report, task, company...">
                        <select class="form-select" name="status" id="filterStatus">
                            <option value="">All statuses</option><?php foreach (['Pending', 'In Progress', 'Complete', 'Incomplete', 'On Hold', 'Cancelled'] as $s): ?><option value="<?= h($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?>
                        </select>
                        <select class="form-select" name="company" id="filterCompany">
                            <option value="">All companies</option><?php foreach ($companies as $c): ?><option value="<?= h($c['company_name']) ?>" <?= $companyFilter === $c['company_name'] ? 'selected' : '' ?>><?= h($c['company_name']) ?></option><?php endforeach; ?>
                        </select>
                        <input class="form-control" type="date" name="from" id="dateFrom" value="<?= h($dateFrom) ?>" title="Date from">
                        <input class="form-control" type="date" name="to" id="dateTo" value="<?= h($dateTo) ?>" title="Date to">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Apply</button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= h(basename($_SERVER['PHP_SELF'])) ?>">Reset</a>
                        <a class="btn btn-success btn-sm ms-auto" id="export" href="?export=excel"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Report No.</th>
                                    <th>Date Requested</th>
                                    <th>Task / Issue</th>
                                    <th>Attachment</th>
                                    <th>Company</th>
                                    <th>Assigned To</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rows">
                                <?php if (!$tasks): ?><tr>
                                        <td colspan="9" class="text-center text-secondary p-4">No tasks found for the selected filters.</td>
                                    </tr><?php endif; ?>
                                <?php foreach ($tasks as $t): $mine = (int)$t['assigned_user_id'] === $userId;
                                    $sk = strtolower(str_replace(' ', '-', (string)$t['status'])); ?>
                                    <tr class="<?= $mine ? 'mine' : '' ?>" data-mine="<?= $mine ? 1 : 0 ?>" data-status="<?= h(strtolower($t['status'])) ?>" data-company="<?= h($t['company_name']) ?>" data-date="<?= h($t['created_at']) ?>" data-search="<?= h(strtolower(($t['report_id'] ?? '') . ' ' . ($t['title'] ?? '') . ' ' . ($t['company_name'] ?? ''))) ?>">
                                        <td class="fw-bold"><?= h($t['report_id'] ?: $t['task_id']) ?></td>
                                        <td><?= h($t['created_at'] ?: '—') ?></td>
                                        <td class="task-issue-cell">
                                            <div class="taskname"><?= h($t['title'] ?: 'Untitled task') ?><?= $mine ? '<span class="mine-tag">MY TASK</span>' : '' ?></div><small class="text-secondary task-description"><?= h($t['description'] ?: '') ?></small>
                                        </td>
                                        <td><?php $attachmentLink = attachmentUrl($t['attachment'] ?? '');
                                            if ($attachmentLink): ?><button type="button" class="attachment-preview-btn" data-attachment-url="<?= h($attachmentLink) ?>" data-attachment-name="<?= h(basename(str_replace('\\\\', '/', (string)$t['attachment']))) ?>"><i class="bi bi-paperclip"></i><span><?= h(basename(str_replace('\\\\', '/', (string)$t['attachment']))) ?></span></button><?php else: ?><span class="text-secondary">No attachment</span><?php endif; ?></td>
                                        <td><?= h($t['company_name'] ?: '—') ?></td>
                                        <td><?= h($t['assigned_name'] ?: 'Unassigned') ?></td>
                                        <td><?= h($t['due_date'] ?: '—') ?></td>
                                        <td><span class="pill <?= h($sk) ?>"><?= h($t['status']) ?></span></td>
                                        <td>
                                            <div class="actions">
                                                <?php if (strtolower(trim($t['status'])) === 'pending'): ?>
                                                    <button type="button" class="btn btn-success btn-sm accept-btn" data-id="<?= $t['task_id'] ?>" data-title="<?= h($t['title'] ?: 'Task') ?>" data-assigned-user="<?= (int)$t['assigned_user_id'] ?>" data-assigned-name="<?= h($t['assigned_name'] ?: 'Unassigned') ?>"><i class="bi bi-check2-circle"></i> Accept Task</button>
                                                <?php elseif ($mine && in_array(strtolower(trim($t['status'])), ['in progress', 'incomplete', 'on hold'], true)): ?>
                                                    <button class="btn btn-primary btn-sm update-btn" data-task="<?= h(json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG)) ?>"><i class="bi bi-pencil-square"></i> Update</button>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-secondary btn-sm" disabled title="This task is not available for acceptance or updating."><i class="bi bi-lock"></i> Unavailable</button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-secondary btn-sm reassign-btn" data-id="<?= $t['task_id'] ?>" data-title="<?= h($t['title'] ?: 'Task') ?>"><i class="bi bi-arrow-left-right"></i> Reassign</button>
                                                <button type="button" class="btn btn-outline-danger btn-sm delete-btn" data-id="<?= $t['task_id'] ?>" data-title="<?= h($t['title'] ?: 'Untitled task') ?>" aria-label="Delete task" title="<?= (!$mine && !in_array($role, ['mishead', 'admin', 'administrator'], true)) ? 'Only the assigned MIS user or MIS Head/Admin can delete this task.' : 'Delete task' ?>" <?= (!$mine && !in_array($role, ['mishead', 'admin', 'administrator'], true)) ? 'disabled' : '' ?>><i class="bi bi-trash" aria-hidden="true"></i></button>
                                            </div>
                                        </td>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 p-3 border-top">
                        <div class="d-flex align-items-center gap-2 small text-secondary">
                            <label for="perPageSelect" class="mb-0">Rows per page</label>
                            <select id="perPageSelect" class="form-select form-select-sm" style="width:82px">
                                <?php foreach ([10, 25, 50, 100] as $option): ?><option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?>
                            </select>
                            <span>Showing <?= $totalRecords ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalRecords) ?> of <?= number_format($totalRecords) ?></span>
                        </div>
                        <nav aria-label="Task table pages">
                            <ul class="pagination pagination-sm mb-0">
                                <?php $queryBase = $_GET;
                                unset($queryBase['export']);
                                $pageUrl = function ($page) use ($queryBase) {
                                    $queryBase['page'] = $page;
                                    return '?' . http_build_query($queryBase);
                                }; ?>
                                <li class="page-item <?= $currentPageNumber <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= h($pageUrl(max(1, $currentPageNumber - 1))) ?>">Previous</a></li>
                                <?php $startPage = max(1, $currentPageNumber - 2);
                                $endPage = min($totalPages, $currentPageNumber + 2);
                                for ($pNum = $startPage; $pNum <= $endPage; $pNum++): ?>
                                    <li class="page-item <?= $pNum === $currentPageNumber ? 'active' : '' ?>"><a class="page-link" href="<?= h($pageUrl($pNum)) ?>"><?= $pNum ?></a></li>
                                <?php endfor; ?>
                                <li class="page-item <?= $currentPageNumber >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= h($pageUrl(min($totalPages, $currentPageNumber + 1))) ?>">Next</a></li>
                            </ul>
                        </nav>
                    </div>
                </section>
            </section>
        </main>
    </div>

    <?php require __DIR__ . '/update-task-modal.php'; // Update Task modal separated for cleaner dashboard code 
    ?>

    <div class="modal fade" id="attachmentModal" tabindex="-1" aria-labelledby="attachmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="attachmentModalLabel"><i class="bi bi-paperclip me-2"></i>Attachment Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="height:78vh;min-height:420px;background:#f3f6fa">
                    <iframe id="attachmentFrame" title="Attachment preview" style="width:100%;height:100%;border:0" loading="lazy"></iframe>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-outline-primary btn-sm" id="downloadAttachment" download><i class="bi bi-download me-1"></i>Download</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="reassignModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reassign Task</h5><button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="reassignForm">
                    <div class="modal-body"><input type="hidden" name="action" value="reassign_task"><input type="hidden" name="task_id" id="reassignId">
                        <p id="reassignTitle" class="small text-secondary"></p><label class="form-label">Assign To</label><select class="form-select" name="assigned_user_id" required>
                            <option value="">Select MIS User</option><?php foreach ($misUsers as $u): ?><option value="<?= $u['user_id'] ?>" <?= $u['user_id'] == $userId ? 'selected' : '' ?>><?= h($u['full_name']) ?><?= $u['user_id'] == $userId ? ' (Me)' : '' ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Assignment</button></div>
                </form>
            </div>
        </div>
    </div>
    <!-- Request for Leave Form popup: dashboard header/navigation remain visible -->
    <div class="modal fade" id="leaveRequestModal" tabindex="-1" aria-labelledby="leaveRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width:1180px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leaveRequestModalLabel"><i class="bi bi-calendar-plus me-2"></i>Request for Leave Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="height:82vh;min-height:560px;">
                    <iframe src="../leave-request-print-v2.php" title="Request for Leave Form" style="display:block;width:100%;height:100%;border:0;background:#f2f5f9;"></iframe>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="deleteTaskModal" tabindex="-1" aria-labelledby="deleteTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteTaskModalLabel"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Are you sure you want to permanently delete this task?</p>
                    <div class="fw-semibold" id="deleteTaskTitle"></div>
                    <p class="small text-danger mt-3 mb-0">This action cannot be undone. The task record will be removed from the database.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteTask"><i class="bi bi-trash me-1"></i>Yes, Delete</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="toast" class="toast text-bg-dark">
            <div class="toast-body" id="toastText"></div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const updateModal = new bootstrap.Modal('#updateModal'),
            reassignModal = new bootstrap.Modal('#reassignModal'),
            toast = new bootstrap.Toast('#toast');

        const attachmentModal = new bootstrap.Modal('#attachmentModal');
        const deleteTaskModal = new bootstrap.Modal('#deleteTaskModal');
        let selectedDeleteTaskId = null;
        document.querySelectorAll('.attachment-preview-btn').forEach(button => button.addEventListener('click', () => {
            const url = button.dataset.attachmentUrl;
            const name = button.dataset.attachmentName || 'Attachment';
            document.getElementById('attachmentModalLabel').textContent = name;
            document.getElementById('attachmentFrame').src = url;
            const download = document.getElementById('downloadAttachment');
            download.href = url;
            download.setAttribute('download', name);
            attachmentModal.show();
        }));
        document.getElementById('attachmentModal').addEventListener('hidden.bs.modal', () => {
            document.getElementById('attachmentFrame').src = 'about:blank';
        });

        function message(t) {
            document.getElementById('toastText').textContent = t;
            toast.show();
        }
        document.querySelectorAll('.update-btn:not(:disabled)').forEach(b => b.addEventListener('click', () => {
            const t = JSON.parse(b.dataset.task);
            document.getElementById('taskId').value = t.task_id;
            document.getElementById('modalReport').textContent = 'Report No. ' + (t.report_id || t.task_id);
            document.getElementById('modalTitle').textContent = t.title || 'Untitled task';
            document.getElementById('modalCompany').textContent = t.company_name || '—';
            const statusSelect = document.getElementById('modalStatus');
            statusSelect.innerHTML = '';
            const choices = (String(t.status || '').toLowerCase() === 'in progress') ? ['Complete', 'Incomplete', 'On Hold'] : ['In Progress'];
            choices.forEach(v => {
                const o = document.createElement('option');
                o.value = v;
                o.textContent = v;
                statusSelect.appendChild(o);
            });
            document.getElementById('modalDiagnosis').value = t.diagnosis || '';
            document.getElementById('modalAction').value = t.action_taken || '';
            document.getElementById('modalRecommendation').value = t.recommendation || '';
            syncUpdateFields();
            updateModal.show();
        }));

        function syncUpdateFields() {
            const status = document.getElementById('modalStatus').value;
            const isComplete = status === 'Complete';
            const isIncomplete = status === 'Incomplete';
            const isOnHold = status === 'On Hold';
            const needsDiagnosis = isComplete || isIncomplete || isOnHold;
            const needsAction = isComplete || isIncomplete;
            const needsReason = isIncomplete || isOnHold;

            document.getElementById('diagnosisGroup').classList.toggle('d-none', !needsDiagnosis);
            document.getElementById('actionGroup').classList.toggle('d-none', !needsAction);
            document.getElementById('recommendationGroup').classList.toggle('d-none', !needsReason);

            document.getElementById('modalDiagnosis').required = needsDiagnosis;
            document.getElementById('modalAction').required = needsAction;
            document.getElementById('modalRecommendation').required = needsReason;

            document.getElementById('diagnosisRequired').classList.toggle('d-none', !needsDiagnosis);
            document.getElementById('actionRequired').classList.toggle('d-none', !needsAction);

            const reasonLabel = document.getElementById('recommendationLabel');
            const reasonHelp = document.getElementById('recommendationHelp');
            const reasonInput = document.getElementById('modalRecommendation');
            if (isOnHold) {
                reasonLabel.textContent = 'Reason for On Hold';
                reasonInput.placeholder = 'Explain why work is temporarily on hold...';
                reasonHelp.textContent = 'This is saved in the Recommendation field.';
            } else {
                reasonLabel.textContent = 'Reason for Incomplete';
                reasonInput.placeholder = 'Explain why this task cannot be completed yet and what is needed next...';
                reasonHelp.textContent = 'This is saved in the Recommendation field.';
            }

            const help = document.getElementById('updateHelp');
            const messages = {
                'In Progress': 'The task will return to In Progress. No additional details are required. Existing notes will be preserved.',
                'Complete': 'Provide the diagnosis and describe the action taken.',
                'Incomplete': 'Provide the diagnosis, action taken, and the reason the task cannot be completed yet.',
                'On Hold': 'Provide the diagnosis and explain why the task is temporarily on hold.'
            };
            help.innerHTML = '<i class="bi bi-info-circle text-primary me-1"></i>' + (
                messages[status] || 'Choose a status to see the information required for that update.'
            );
        }
        document.getElementById('modalStatus').addEventListener('change', syncUpdateFields);
        document.querySelectorAll('.reassign-btn').forEach(b => b.addEventListener('click', () => {
            document.getElementById('reassignId').value = b.dataset.id;
            document.getElementById('reassignTitle').textContent = 'Reassign: ' + b.dataset.title;
            reassignModal.show();
        }));
        document.querySelectorAll('.delete-btn:not(:disabled)').forEach(button => button.addEventListener('click', () => {
            selectedDeleteTaskId = button.dataset.id;
            document.getElementById('deleteTaskTitle').textContent = button.dataset.title || 'Selected task';
            deleteTaskModal.show();
        }));

        document.getElementById('confirmDeleteTask').addEventListener('click', async () => {
            if (!selectedDeleteTaskId) return;
            const confirmButton = document.getElementById('confirmDeleteTask');
            confirmButton.disabled = true;
            confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Deleting...';
            const fd = new FormData();
            fd.append('action', 'delete_task');
            fd.append('task_id', selectedDeleteTaskId);
            fd.append('csrf_token', <?= json_encode($csrfToken) ?>);
            try {
                const response = await fetch(location.pathname + location.search, { method: 'POST', body: fd });
                const result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.message || 'Unable to delete task.');
                deleteTaskModal.hide();
                message(result.message);
                setTimeout(() => location.reload(), 500);
            } catch (error) {
                alert(error.message);
            } finally {
                confirmButton.disabled = false;
                confirmButton.innerHTML = '<i class="bi bi-trash me-1"></i>Yes, Delete';
            }
        });

        async function submitTaskAcceptance(button) {
            if (!button) return;
            button.disabled = true;
            const fd = new FormData();
            fd.append('action', 'accept_task');
            fd.append('task_id', button.dataset.id);
            try {
                const r = await fetch(location.pathname + location.search, { method: 'POST', body: fd });
                const j = await r.json();
                if (!r.ok || !j.ok) throw Error(j.message || 'Unable to accept task.');
                message(j.message);
                setTimeout(() => location.reload(), 450);
            } catch (x) {
                alert(x.message);
                button.disabled = false;
            }
        }

        // Accept Task performs only the acceptance/assignment action.
        // No confirmation or task-update modal is opened; the user can update
        // the task status later after performing the actual work.
        document.querySelectorAll('.accept-btn').forEach(button => button.addEventListener('click', () => {
            submitTaskAcceptance(button);
        }));

        async function send(form) {
            const r = await fetch(location.pathname, {
                method: 'POST',
                body: new FormData(form)
            });
            const j = await r.json();
            if (!r.ok || !j.ok) throw Error(j.message || 'Unable to save.');
            return j;
        }
        document.getElementById('updateForm').addEventListener('submit', async e => {
            e.preventDefault();
            try {
                const r = await send(e.currentTarget);
                message(r.message);
                setTimeout(() => location.reload(), 600);
            } catch (x) {
                alert(x.message);
            }
        });
        document.getElementById('reassignForm').addEventListener('submit', async e => {
            e.preventDefault();
            try {
                const r = await send(e.currentTarget);
                message(r.message);
                setTimeout(() => location.reload(), 600);
            } catch (x) {
                alert(x.message);
            }
        });
        // Changing page size reloads only the selected number of rows from MySQL.
        document.getElementById('perPageSelect').addEventListener('change', e => {
            const url = new URL(location.href);
            url.searchParams.set('per_page', e.target.value);
            url.searchParams.set('page', '1');
            location.href = url.toString();
        });
        // Dashboard metric cards apply their filters on the server across every matching record.
        document.querySelectorAll('[data-card]').forEach(button => button.addEventListener('click', () => {
            const url = new URL(location.href);
            const key = button.dataset.card;
            if (key === 'all') url.searchParams.set('mine_filter', 'all');
            else url.searchParams.set('mine_filter', key);
            url.searchParams.set('page', '1');
            location.href = url.toString();
        }));
        document.querySelectorAll('[data-view]').forEach(link => link.addEventListener('click', () => {
            const url = new URL(location.href);
            url.searchParams.set('mine_filter', 'all');
            url.searchParams.set('page', '1');
            location.href = url.toString();
        }));

        function exportUrl() {
            const params = new URLSearchParams({
                export: 'excel'
            });
            document.querySelectorAll('#filterForm [name]').forEach(field => {
                if (field.name !== 'page' && field.name !== 'per_page' && field.value) params.set(field.name, field.value);
            });
            const mineFilter = new URLSearchParams(location.search).get('mine_filter');
            if (mineFilter) params.set('mine_filter', mineFilter);
            document.getElementById('export').href = location.pathname + '?' + params.toString();
        }
        document.getElementById('filterForm').addEventListener('input', exportUrl);
        document.getElementById('filterForm').addEventListener('change', exportUrl);
        exportUrl();
    </script>
    <?php require __DIR__ . '/../includes/footer.php'; ?>