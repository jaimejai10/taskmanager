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

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function reply($ok, $message, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}
function columns(PDO $db, string $table): array {
    return array_column($db->query("SHOW COLUMNS FROM `$table`")->fetchAll(), 'Field');
}
function pick(array $cols, array $names): ?string {
    foreach ($names as $name) if (in_array($name, $cols, true)) return $name;
    return null;
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

    if ($roleCol) {
        $roleCheck = $conn->prepare("SELECT `$roleCol` FROM users WHERE `$uidCol` = ?");
        $roleCheck->execute([$userId]);
        $role = strtolower((string)$roleCheck->fetchColumn());
        if (!$role) throw new RuntimeException('Logged-in user was not found in users table.');
        if (!in_array($role, ['misuser', 'mishead', 'admin', 'administrator'], true)) {
            http_response_code(403); exit('Access denied.');
        }
    }

    $fields = [
        'report_id' => pick($tc, ['report_id', 'service_report_no']),
        'title' => pick($tc, ['title', 'task_title']),
        'description' => pick($tc, ['description', 'problem_details']),
        'created_at' => pick($tc, ['created_at', 'date_requested', 'request_date']),
        'due_date' => pick($tc, ['due_date']),
        'requested_by' => pick($tc, ['requested_by']),
        'diagnosis' => pick($tc, ['diagnosis']),
        'action_taken' => pick($tc, ['action_taken']),
        'recommendation' => pick($tc, ['recommendation']),
        'remarks' => pick($tc, ['remarks'])
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

        $q = $conn->prepare("SELECT task_id, `$assignCol` AS assigned_user_id FROM tasks WHERE task_id = ?");
        $q->execute([$taskId]);
        $task = $q->fetch();
        if (!$task) reply(false, 'Task not found.', 404);

        if (($_POST['action'] ?? '') === 'update_task') {
            // Server-side authorization: must be assigned to the logged-in user.
            if ((int)$task['assigned_user_id'] !== $userId) {
                reply(false, 'You can update only tasks assigned to you. Reassign this task to yourself first.', 403);
            }
            $status = trim($_POST['status'] ?? '');
            $allowed = ['Pending', 'Assigned', 'In Progress', 'On Hold', 'Completed', 'Cancelled'];
            if (!in_array($status, $allowed, true)) reply(false, 'Select a valid status.', 422);

            $set = ['status = ?']; $params = [$status];
            foreach (['diagnosis', 'action_taken', 'recommendation', 'remarks'] as $key) {
                if ($fields[$key]) {
                    $set[] = "`{$fields[$key]}` = ?";
                    $params[] = trim($_POST[$key] ?? '');
                }
            }
            $params[] = $taskId;
            $up = $conn->prepare("UPDATE tasks SET " . implode(', ', $set) . " WHERE task_id = ?");
            $up->execute($params);
            reply(true, 'Task updated successfully.');
        }

        if (($_POST['action'] ?? '') === 'reassign_task') {
            $target = (int)($_POST['assigned_user_id'] ?? 0);
            if (!$target) reply(false, 'Choose an MIS user.', 422);
            $sql = "SELECT `$uidCol` FROM users WHERE `$uidCol` = ?";
            if ($roleCol) $sql .= " AND LOWER(`$roleCol`) IN ('misuser','mishead')";
            $check = $conn->prepare($sql); $check->execute([$target]);
            if (!$check->fetchColumn()) reply(false, 'Selected account is not an MIS user.', 422);
            $up = $conn->prepare("UPDATE tasks SET `$assignCol` = ? WHERE task_id = ?");
            $up->execute([$target, $taskId]);
            reply(true, 'Task reassigned successfully.');
        }
        reply(false, 'Unknown action.', 400);
    }

    // CSV export: Excel opens CSV files natively.
    if (($_GET['export'] ?? '') === 'excel') {
        $where = []; $params = [];
        if ($hasCompany && !empty($_GET['company'])) {
            $where[] = "c.`$companyName` = ?"; $params[] = $_GET['company'];
        }
        if ($fields['created_at'] && !empty($_GET['from'])) {
            $where[] = "DATE(t.`{$fields['created_at']}`) >= ?"; $params[] = $_GET['from'];
        }
        if ($fields['created_at'] && !empty($_GET['to'])) {
            $where[] = "DATE(t.`{$fields['created_at']}`) <= ?"; $params[] = $_GET['to'];
        }
        if (!empty($_GET['status'])) { $where[] = "t.status = ?"; $params[] = $_GET['status']; }
        $q = $conn->prepare($select . $from . ($where ? ' WHERE '.implode(' AND ', $where) : '') . ' ORDER BY t.task_id DESC');
        $q->execute($params);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="MIS_All_Tasks_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Report No.','Date Requested','Company','Title','Description','Requested By','Assigned To','Status','Due Date','Diagnosis','Action Taken','Recommendation','Remarks']);
        while ($r = $q->fetch()) {
            fputcsv($out, [$r['report_id'],$r['created_at'],$r['company_name'],$r['title'],$r['description'],$r['requested_by'],$r['assigned_name'],$r['status'],$r['due_date'],$r['diagnosis'],$r['action_taken'],$r['recommendation'],$r['remarks']]);
        }
        fclose($out); exit;
    }

    // Task list: current user's tasks first, then unassigned, then other users' tasks.
    $orderDue = $fields['due_date'] ? "CASE WHEN t.`{$fields['due_date']}` < NOW() AND LOWER(t.status) NOT IN ('completed','cancelled') THEN 0 ELSE 1 END, t.`{$fields['due_date']}` ASC," : '';
    $q = $conn->prepare($select . $from . "
        ORDER BY CASE WHEN t.`$assignCol` = ? THEN 0 WHEN t.`$assignCol` IS NULL OR t.`$assignCol` = 0 THEN 1 ELSE 2 END,
        $orderDue t.task_id DESC LIMIT 500");
    $q->execute([$userId]); $tasks = $q->fetchAll();

    $userSql = "SELECT `$uidCol` AS user_id, `$unameCol` AS full_name FROM users";
    if ($roleCol) $userSql .= " WHERE LOWER(`$roleCol`) IN ('misuser','mishead')";
    $userSql .= " ORDER BY `$unameCol`";
    $misUsers = $conn->query($userSql)->fetchAll();
    $companies = ($hasCompany) ? $conn->query("SELECT DISTINCT `$companyName` AS company_name FROM companies ORDER BY `$companyName`")->fetchAll() : [];

} catch (Throwable $e) {
    error_log('MIS user dashboard: '.$e->getMessage());
    http_response_code(500);
    exit('Dashboard setup error: '.h($e->getMessage()).'<br>Check table/column names in your database.');
}

$counts = ['total'=>0,'completed'=>0,'pending'=>0,'progress'=>0,'overdue'=>0];
foreach ($tasks as $t) {
    if ((int)$t['assigned_user_id'] !== $userId) continue;
    $counts['total']++;
    $s = strtolower(trim($t['status']));
    if ($s === 'completed') $counts['completed']++;
    if (in_array($s,['pending','assigned'],true)) $counts['pending']++;
    if (in_array($s,['in progress','in_progress'],true)) $counts['progress']++;
    if ($t['due_date'] && strtotime($t['due_date']) < time() && !in_array($s,['completed','cancelled'],true)) $counts['overdue']++;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>MIS User Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--navy:#102b52;--navy2:#1b477d;--bg:#f3f6fa;--line:#e6edf5;--muted:#718096}
body{margin:0;background:var(--bg);font-family:Segoe UI,Arial,sans-serif;color:#1e293b}
.topbar{background:linear-gradient(110deg,var(--navy),var(--navy2));color:#fff;padding:17px 28px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 5px 18px #102b5220}
.brand{display:flex;gap:12px;align-items:center}.brand-icon{width:43px;height:43px;border-radius:12px;background:#ffffff20;display:grid;place-items:center;font-size:22px}.brand h1{font-size:18px;font-weight:800;letter-spacing:1px;margin:0}.brand small{font-size:10px;letter-spacing:2px;color:#cbd8e8}
.account{display:flex;align-items:center;gap:12px}.bell{width:40px;height:40px;border:1px solid #ffffff50;background:#ffffff15;color:white;border-radius:10px}.avatar{width:34px;height:34px;border-radius:50%;background:#dbeafe;color:#173d70;display:grid;place-items:center;font-weight:bold}
.navline{background:#fff;padding:0 28px;border-bottom:1px solid var(--line);display:flex;gap:8px;overflow:auto}.navline a{padding:15px 13px;text-decoration:none;color:#64748b;font-size:13px;font-weight:700;white-space:nowrap;border-bottom:3px solid transparent}.navline a.active,.navline a:hover{color:#1d4f88;border-color:#3b82f6}
main{max-width:1600px;margin:auto;padding:25px 28px}.welcome{display:flex;justify-content:space-between;align-items:end;margin-bottom:20px}.welcome h2{font-size:23px;font-weight:800;margin:0}.welcome p{color:var(--muted);font-size:13px;margin:5px 0 0}
.cards{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:22px}.metric{border:0;background:#fff;border-radius:14px;padding:17px;display:flex;align-items:center;gap:13px;text-align:left;box-shadow:0 4px 16px #1d355710;transition:.2s}.metric:hover{transform:translateY(-3px)}.metric .ico{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;font-size:20px}.metric strong{display:block;font-size:25px;line-height:1.1}.metric span{font-size:12px;color:var(--muted);font-weight:700}.blue .ico{background:#e8f1ff;color:#2563eb}.green .ico{background:#e4f8ed;color:#159455}.orange .ico{background:#fff2df;color:#d97706}.purple .ico{background:#f1eaff;color:#7c3aed}.red .ico{background:#ffe8e8;color:#dc2626}
.panel{background:#fff;border:1px solid var(--line);border-radius:15px;overflow:hidden;box-shadow:0 4px 16px #1d35570a}.panel-head{padding:18px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center}.panel-head h3{font-size:15px;font-weight:800;margin:0}.panel-head p{font-size:12px;color:var(--muted);margin:4px 0 0}.filters{padding:14px 20px;display:flex;gap:9px;flex-wrap:wrap}.filters .form-control,.filters .form-select{max-width:190px;font-size:12px}.table-responsive{overflow:auto}table{min-width:980px}thead th{background:#f7f9fc!important;color:#64748b!important;font-size:10px;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}tbody td{font-size:12px;vertical-align:middle}.mine{background:#f4f8ff}.taskname{font-weight:750}.mine-tag{font-size:9px;color:#1d4ed8;background:#e3efff;border-radius:5px;padding:3px 5px;margin-left:4px}.pill{font-size:10px;font-weight:800;border-radius:20px;padding:5px 9px;background:#eef2f7;color:#475569}.pending{background:#fff3dc;color:#a16207}.in-progress,.assigned{background:#e8f1ff;color:#1d4ed8}.completed{background:#e5f8ed;color:#15803d}.on-hold{background:#f1eaff;color:#6d28d9}.cancelled{background:#ffe8e8;color:#b91c1c}.actions{display:flex;gap:5px;white-space:nowrap}.btn-sm{font-size:11px;font-weight:700;border-radius:8px}.modal-content{border:0;border-radius:16px;overflow:hidden}.modal-header{background:linear-gradient(110deg,var(--navy),var(--navy2));color:#fff}.modal-header .btn-close{filter:invert(1)}.modal-title{font-weight:800;font-size:16px}.form-label{font-size:12px;font-weight:700;color:#475569}.form-control,.form-select{font-size:13px;border-radius:9px;padding:10px;border-color:#dce4ee}
@media(max-width:1100px){.cards{grid-template-columns:repeat(3,1fr)}}@media(max-width:700px){.topbar{padding:14px}.navline{padding:0 8px}main{padding:18px 12px}.cards{grid-template-columns:repeat(2,1fr);gap:8px}.metric{padding:12px}.metric strong{font-size:21px}.welcome{display:block}.account .uname{display:none}}
</style>
</head>
<body>
<header class="topbar">
 <div class="brand"><div class="brand-icon"><i class="bi bi-headset"></i></div><div><h1>MIS HELPDESK</h1><small>IT SUPPORT OPERATIONS · MIS USER</small></div></div>
 <div class="account"><button class="bell" data-bs-toggle="modal" data-bs-target="#notifications" title="Notifications"><i class="bi bi-bell"></i></button><div class="avatar"><?=h(mb_substr($userName,0,1))?></div><span class="uname small fw-semibold"><?=h($userName)?></span><a class="btn btn-sm btn-outline-light" href="../logout.php"><i class="bi bi-box-arrow-right"></i></a></div>
</header>
<nav class="navline"><a class="active" href="user-dashboard.php"><i class="bi bi-grid me-1"></i>Dashboard</a><a href="#taskGrid" data-view="mine"><i class="bi bi-person-check me-1"></i>My Tasks</a><a href="#taskGrid" data-view="all"><i class="bi bi-list-task me-1"></i>All Tasks</a><a href="leave-request.php" data-bs-toggle="modal" data-bs-target="#leaveRequestModal"><i class="bi bi-calendar-plus me-1"></i>File A Leave</a></nav>
<main>
 <div class="welcome"><div><h2>Welcome back, <?=h($userName)?>!</h2><p>Manage your assigned work and view the MIS team's task queue.</p></div><small class="text-secondary">Updated <?=date('M d, Y')?></small></div>
 <section class="cards">
  <button class="metric blue" data-card="all"><div class="ico"><i class="bi bi-clipboard-data"></i></div><div><strong><?=$counts['total']?></strong><span>My Assigned Tasks</span></div></button>
  <button class="metric green" data-card="completed"><div class="ico"><i class="bi bi-check-circle"></i></div><div><strong><?=$counts['completed']?></strong><span>Completed</span></div></button>
  <button class="metric orange" data-card="pending"><div class="ico"><i class="bi bi-hourglass-split"></i></div><div><strong><?=$counts['pending']?></strong><span>Pending</span></div></button>
  <button class="metric purple" data-card="progress"><div class="ico"><i class="bi bi-gear"></i></div><div><strong><?=$counts['progress']?></strong><span>In Progress</span></div></button>
  <button class="metric red" data-card="overdue"><div class="ico"><i class="bi bi-exclamation-triangle"></i></div><div><strong><?=$counts['overdue']?></strong><span>Overdue</span></div></button>
 </section>
 <section class="panel" id="taskGrid">
  <div class="panel-head"><div><h3><i class="bi bi-kanban me-2 text-primary"></i>All Tasks</h3><p>Your tasks are displayed first. Reassign a task to yourself before updating it.</p></div><span class="badge text-bg-light"><?=count($tasks)?> records</span></div>
  <div class="filters"><input class="form-control" id="search" placeholder="Search report, title, company..."><select class="form-select" id="filterStatus"><option value="">All statuses</option><?php foreach(['Pending','Assigned','In Progress','On Hold','Completed','Cancelled'] as $s):?><option><?=h($s)?></option><?php endforeach;?></select><select class="form-select" id="filterCompany"><option value="">All companies</option><?php foreach($companies as $c):?><option><?=h($c['company_name'])?></option><?php endforeach;?></select><input class="form-control" type="date" id="dateFrom" title="Date from"><input class="form-control" type="date" id="dateTo" title="Date to"><button class="btn btn-outline-secondary btn-sm" id="reset">Reset</button><a class="btn btn-success btn-sm ms-auto" id="export" href="?export=excel"><i class="bi bi-file-earmark-excel"></i> Export Excel</a></div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Report No.</th><th>Date Requested</th><th>Task / Issue</th><th>Company</th><th>Assigned To</th><th>Due Date</th><th>Status</th><th>Actions</th></tr></thead><tbody id="rows">
  <?php if(!$tasks):?><tr><td colspan="8" class="text-center text-secondary p-4">No tasks found.</td></tr><?php endif;?>
  <?php foreach($tasks as $t): $mine=(int)$t['assigned_user_id']===$userId; $sk=strtolower(str_replace(' ','-',(string)$t['status']));?>
  <tr class="<?=$mine?'mine':''?>" data-mine="<?=$mine?1:0?>" data-status="<?=h(strtolower($t['status']))?>" data-company="<?=h($t['company_name'])?>" data-date="<?=h($t['created_at'])?>" data-search="<?=h(strtolower(($t['report_id']??'').' '.($t['title']??'').' '.($t['company_name']??'')))?>">
   <td class="fw-bold"><?=h($t['report_id'] ?: $t['task_id'])?></td><td><?=h($t['created_at'] ?: '—')?></td>
   <td><div class="taskname"><?=h($t['title'] ?: 'Untitled task')?><?=$mine?'<span class="mine-tag">MY TASK</span>':''?></div><small class="text-secondary"><?=h($t['description'] ?: '')?></small></td>
   <td><?=h($t['company_name'] ?: '—')?></td><td><?=h($t['assigned_name'] ?: 'Unassigned')?></td><td><?=h($t['due_date'] ?: '—')?></td>
   <td><span class="pill <?=h($sk)?>"><?=h($t['status'])?></span></td><td><div class="actions">
    <button class="btn btn-primary btn-sm update-btn" <?=$mine?'':'disabled title="Reassign this task to yourself before updating."'?> data-task="<?=h(json_encode($t, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_HEX_TAG))?>"><i class="bi bi-pencil-square"></i> Update</button>
    <button class="btn btn-outline-secondary btn-sm reassign-btn" data-id="<?=$t['task_id']?>" data-title="<?=h($t['title'] ?: 'Task')?>"><i class="bi bi-arrow-left-right"></i> Reassign</button>
   </div></td>
  </tr><?php endforeach;?>
  </tbody></table></div>
 </section>
</main>

<div class="modal fade" id="updateModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
 <div class="modal-header"><div><h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Update Task</h5><small id="modalReport" class="text-white-50"></small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
 <form id="updateForm"><div class="modal-body"><input type="hidden" name="action" value="update_task"><input type="hidden" name="task_id" id="taskId">
  <div class="row mb-3"><div class="col-md-7"><small class="text-secondary">TASK TITLE</small><div class="fw-bold" id="modalTitle"></div></div><div class="col-md-5"><small class="text-secondary">COMPANY</small><div class="fw-semibold" id="modalCompany"></div></div></div>
  <div class="mb-3"><label class="form-label">Update Status <span class="text-danger">*</span></label><select class="form-select" name="status" id="modalStatus" required><option>Pending</option><option>Assigned</option><option>In Progress</option><option>On Hold</option><option>Completed</option><option>Cancelled</option></select></div>
  <div class="row g-3"><div class="col-md-6"><label class="form-label">Diagnosis <span class="text-secondary fw-normal">Optional</span></label><textarea class="form-control" name="diagnosis" id="modalDiagnosis" rows="3"></textarea></div><div class="col-md-6"><label class="form-label">Action Taken <span class="text-secondary fw-normal">Optional</span></label><textarea class="form-control" name="action_taken" id="modalAction" rows="3"></textarea></div><div class="col-md-6"><label class="form-label">Recommendation <span class="text-secondary fw-normal">Optional</span></label><textarea class="form-control" name="recommendation" id="modalRecommendation" rows="3"></textarea></div><div class="col-md-6"><label class="form-label">Remarks <span class="text-secondary fw-normal">Optional</span></label><textarea class="form-control" name="remarks" id="modalRemarks" rows="3"></textarea></div></div>
  <div class="alert alert-light border small mt-3 mb-0"><i class="bi bi-info-circle text-primary me-1"></i> Only Update Status is required. All other fields are optional.</div>
 </div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Update Task</button></div></form>
</div></div></div>
<div class="modal fade" id="reassignModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Reassign Task</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><form id="reassignForm"><div class="modal-body"><input type="hidden" name="action" value="reassign_task"><input type="hidden" name="task_id" id="reassignId"><p id="reassignTitle" class="small text-secondary"></p><label class="form-label">Assign To</label><select class="form-select" name="assigned_user_id" required><option value="">Select MIS User</option><?php foreach($misUsers as $u):?><option value="<?=$u['user_id']?>" <?=$u['user_id']==$userId?'selected':''?>><?=h($u['full_name'])?><?=$u['user_id']==$userId?' (Me)':''?></option><?php endforeach;?></select></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Assignment</button></div></form></div></div></div>
<div class="modal fade" id="notifications" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Notifications</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body text-secondary small">Notification feed can be connected to your existing notifications table in the next step.</div></div></div></div>
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
<div class="toast-container position-fixed bottom-0 end-0 p-3"><div id="toast" class="toast text-bg-dark"><div class="toast-body" id="toastText"></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const updateModal=new bootstrap.Modal('#updateModal'),reassignModal=new bootstrap.Modal('#reassignModal'),toast=new bootstrap.Toast('#toast');
function message(t){document.getElementById('toastText').textContent=t;toast.show();}
document.querySelectorAll('.update-btn:not(:disabled)').forEach(b=>b.addEventListener('click',()=>{
 const t=JSON.parse(b.dataset.task);document.getElementById('taskId').value=t.task_id;document.getElementById('modalReport').textContent='Report No. '+(t.report_id||t.task_id);document.getElementById('modalTitle').textContent=t.title||'Untitled task';document.getElementById('modalCompany').textContent=t.company_name||'—';document.getElementById('modalStatus').value=t.status||'Pending';document.getElementById('modalDiagnosis').value=t.diagnosis||'';document.getElementById('modalAction').value=t.action_taken||'';document.getElementById('modalRecommendation').value=t.recommendation||'';document.getElementById('modalRemarks').value=t.remarks||'';updateModal.show();
}));
document.querySelectorAll('.reassign-btn').forEach(b=>b.addEventListener('click',()=>{document.getElementById('reassignId').value=b.dataset.id;document.getElementById('reassignTitle').textContent='Reassign: '+b.dataset.title;reassignModal.show();}));
async function send(form){const r=await fetch(location.pathname,{method:'POST',body:new FormData(form)});const j=await r.json();if(!r.ok||!j.ok)throw Error(j.message||'Unable to save.');return j;}
document.getElementById('updateForm').addEventListener('submit',async e=>{e.preventDefault();try{const r=await send(e.currentTarget);message(r.message);setTimeout(()=>location.reload(),600);}catch(x){alert(x.message);}});
document.getElementById('reassignForm').addEventListener('submit',async e=>{e.preventDefault();try{const r=await send(e.currentTarget);message(r.message);setTimeout(()=>location.reload(),600);}catch(x){alert(x.message);}});
function filterRows(){
 const q=document.getElementById('search').value.toLowerCase(),s=document.getElementById('filterStatus').value.toLowerCase(),c=document.getElementById('filterCompany').value,from=document.getElementById('dateFrom').value,to=document.getElementById('dateTo').value;
 document.querySelectorAll('#rows tr[data-search]').forEach(r=>{const d=(r.dataset.date||'').slice(0,10);r.hidden=!(r.dataset.search.includes(q)&&(!s||r.dataset.status===s)&&(!c||r.dataset.company===c)&&(!from||d>=from)&&(!to||d<=to));});
}
['search','filterStatus','filterCompany','dateFrom','dateTo'].forEach(id=>document.getElementById(id).addEventListener('input',filterRows));
document.getElementById('reset').addEventListener('click',()=>{['search','filterStatus','filterCompany','dateFrom','dateTo'].forEach(id=>document.getElementById(id).value='');filterRows();});
function exportUrl(){const p=new URLSearchParams({export:'excel'});[['company','filterCompany'],['status','filterStatus'],['from','dateFrom'],['to','dateTo']].forEach(([k,id])=>{const v=document.getElementById(id).value;if(v)p.set(k,v);});document.getElementById('export').href='?'+p.toString();}
['filterCompany','filterStatus','dateFrom','dateTo'].forEach(id=>document.getElementById(id).addEventListener('change',exportUrl));
document.querySelectorAll('[data-card]').forEach(b=>b.addEventListener('click',()=>{const k=b.dataset.card;document.querySelectorAll('#rows tr[data-search]').forEach(r=>{const own=r.dataset.mine==='1',s=r.dataset.status;r.hidden=k==='all'?!own:k==='completed'?!(own&&s==='completed'):k==='pending'?!(own&&['pending','assigned'].includes(s)):k==='progress'?!(own&&s==='in progress'):!(own&&r.querySelector('.text-danger'));});document.getElementById('taskGrid').scrollIntoView({behavior:'smooth'});}));
document.querySelectorAll('[data-view]').forEach(a=>a.addEventListener('click',()=>{document.querySelectorAll('#rows tr[data-search]').forEach(r=>r.hidden=a.dataset.view==='mine'&&r.dataset.mine!=='1');}));
</script>
</body></html>
