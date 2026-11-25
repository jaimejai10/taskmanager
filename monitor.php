
<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/DB_connection.php';

if (!isset($conn) || !($conn instanceof PDO)) {
    die('Database connection error: DB_connection.php must define $conn as PDO.');
}

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Resolve the role from the database instead of trusting a client-side role label.
$roleStmt = $conn->prepare('SELECT LOWER(TRIM(role)) FROM users WHERE user_id = ? LIMIT 1');
$roleStmt->execute([(int)$_SESSION['user_id']]);
$currentRole = (string)$roleStmt->fetchColumn();
if ($currentRole === '') {
    http_response_code(403);
    exit('Access denied: user account not found.');
}
$canReviewLeave = ($currentRole === 'mishead');
if (empty($_SESSION['monitor_leave_csrf'])) {
    $_SESSION['monitor_leave_csrf'] = bin2hex(random_bytes(32));
}
$monitorLeaveCsrf = $_SESSION['monitor_leave_csrf'];

function monitorPeriodStart(string $period): string
{
    switch ($period) {
        case 'today':
            return date('Y-m-d 00:00:00');
        case 'week':
            return date('Y-m-d 00:00:00', strtotime('monday this week'));
        case 'month':
            return date('Y-m-01 00:00:00');
        default:
            return date('Y-01-01 00:00:00');
    }
}

function monitorStatus(string $status): string
{
    $status = strtolower(trim($status));

    if ($status === 'new') return 'New';
    if ($status === 'pending') return 'Pending';
    if (in_array($status, ['assigned', 'in progress', 'in_progress', 'ongoing'], true)) {
        return 'Ongoing';
    }
    if (in_array($status, ['completed', 'complete'], true)) return 'Completed';

    return ucfirst($status ?: 'Pending');
}

function monitorStatusClass(string $status): string
{
    return strtolower(monitorStatus($status));
}

function monitorFetch(PDO $conn, string $period, bool $canReviewLeave): array
{
    $start = monitorPeriodStart($period);

    // Summary cards: only tasks created within the selected period.
    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(LOWER(TRIM(status)) = 'completed'), 0) AS completed,
            COALESCE(SUM(LOWER(TRIM(status)) = 'pending'), 0) AS pending,
            COALESCE(SUM(LOWER(TRIM(status)) IN ('assigned','in progress','in_progress')), 0) AS in_progress,
            COALESCE(SUM(
                due_date IS NOT NULL
                AND due_date < NOW()
                AND LOWER(TRIM(status)) NOT IN ('completed', 'complete', 'cancelled', 'canceled')
            ), 0) AS overdue
        FROM tasks
        WHERE created_at >= :start
    ");
    $stmt->execute([':start' => $start]);
    $metrics = $stmt->fetch();

    // Detect optional task columns so this monitoring page remains compatible
    // with installations that do not yet have priority or updated_at.
    $taskColumns = [];
    foreach ($conn->query("SHOW COLUMNS FROM tasks")->fetchAll() as $column) {
        $taskColumns[strtolower($column['Field'])] = true;
    }

    $prioritySelect = isset($taskColumns['priority'])
        ? "t.priority AS task_priority"
        : "NULL AS task_priority";

    // Latest 10 requests, newest first.
    $requests = $conn->query("
        SELECT
            t.task_id,
            t.report_id,
            t.title,
            t.category,
            t.status,
            t.requested_by,
            t.assigned_id,
            t.created_at,
            t.due_date,
            {$prioritySelect},
            COALESCE(NULLIF(c.comp_short_name, ''), c.comp_name) AS company_name,
            assigned_user.full_name AS assigned_to_name
        FROM tasks t
        LEFT JOIN companies c ON c.comp_id = t.company_id
        LEFT JOIN users assigned_user ON assigned_user.user_id = t.assigned_id
        ORDER BY t.created_at DESC, t.task_id DESC
        LIMIT 10;
    ")->fetchAll();

    // Open tasks that are overdue or unassigned.
    $attention = $conn->query("
        SELECT
            t.task_id,
            t.report_id,
            t.title,
            t.status,
            t.due_date,
            t.assigned_id,
            c.comp_name AS company_name,
            CASE
                WHEN t.due_date IS NOT NULL
                     AND t.due_date < NOW()
                     AND (t.assigned_id IS NULL OR t.assigned_id = 0)
                    THEN 'Overdue and unassigned'
                WHEN t.due_date IS NOT NULL AND t.due_date < NOW()
                    THEN 'Overdue'
                ELSE 'Unassigned'
            END AS reason
        FROM tasks t
        LEFT JOIN companies c ON c.comp_id = t.company_id
        WHERE LOWER(TRIM(t.status)) NOT IN ('completed', 'cancelled')
          AND (
              (t.due_date IS NOT NULL AND t.due_date < NOW())
              OR t.assigned_id IS NULL
              OR t.assigned_id = 0
          )
        ORDER BY
            CASE WHEN t.due_date IS NOT NULL AND t.due_date < NOW()
                 THEN 0 ELSE 1 END,
            t.due_date ASC,
            t.task_id DESC
        LIMIT 3
    ")->fetchAll();

    // IT Team Status: approved leave takes priority only during the actual leave dates.
    // This is calculated at display time; users.presence_status is not overwritten,
    // so the employee automatically returns to their normal status after leave ends.
    $team = $conn->query("
        SELECT
            u.user_id,
            u.full_name,
            u.position,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM leave_requests lr
                    WHERE lr.user_id = u.user_id
                      AND LOWER(TRIM(lr.status)) = 'approved'
                      AND CURDATE() BETWEEN lr.start_date AND lr.end_date
                ) THEN 'On Leave'
                ELSE COALESCE(u.presence_status, 'Available')
            END AS presence_status,
            u.updated_at
        FROM users u
        WHERE LOWER(TRIM(u.role)) IN ('mishead', 'misuser')
        ORDER BY
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM leave_requests lr
                    WHERE lr.user_id = u.user_id
                      AND LOWER(TRIM(lr.status)) = 'approved'
                      AND CURDATE() BETWEEN lr.start_date AND lr.end_date
                ) THEN 4
                ELSE CASE COALESCE(u.presence_status, 'Available')
                    WHEN 'Available' THEN 1
                    WHEN 'Busy' THEN 2
                    WHEN 'Away' THEN 3
                    ELSE 4
                END
            END,
            u.full_name ASC
        LIMIT 3
    ")->fetchAll();

    // Leave panel: pending requests plus upcoming approved leave.
    $leave = [];
    $leaveError = null;

    try {
        $leave = $conn->query("
            SELECT
                lr.leave_id,
                lr.user_id,
                lr.leave_type,
                lr.start_date,
                lr.end_date,
                lr.reason,
                lr.status,
                u.full_name
            FROM leave_requests lr
            INNER JOIN users u ON u.user_id = lr.user_id
            WHERE LOWER(TRIM(u.role)) = 'misuser'
              AND (
                    LOWER(lr.status) = 'pending'
                    OR (
                        LOWER(lr.status) = 'approved'
                        AND lr.end_date >= CURDATE()
                    )
              )
            ORDER BY
                CASE WHEN LOWER(lr.status) = 'pending'
                     THEN 0 ELSE 1 END,
                lr.start_date ASC
            LIMIT 8
        ")->fetchAll();
    } catch (PDOException $e) {
        // Don't break the entire dashboard if the leave table isn't ready.
        $leaveError = 'Leave table unavailable';
        error_log('Monitor leave query: ' . $e->getMessage());
    }

    return [
        'ok' => true,
        'metrics' => [
            'total' => (int)($metrics['total'] ?? 0),
            'completed' => (int)($metrics['completed'] ?? 0),
            'pending' => (int)($metrics['pending'] ?? 0),
            'in_progress' => (int)($metrics['in_progress'] ?? 0),
            'overdue' => (int)($metrics['overdue'] ?? 0)
        ],
        'requests' => $requests,
        'attention' => $attention,
        'team' => $team,
        'leave' => $leave,
        'leave_error' => $leaveError,
        'updated_at' => date('Y-m-d H:i:s')
    ];
}

// Same file serves live JSON to the dashboard.
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    try {
        $period = $_GET['period'] ?? 'today';
        if (!in_array($period, ['today', 'week', 'month', 'year'], true)) {
            $period = 'today';
        }

        echo json_encode(
            monitorFetch($conn, $period, $canReviewLeave),
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
    } catch (Throwable $e) {
        error_log('Monitor dashboard: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Unable to load dashboard data. Check the PHP error log.'
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MIS Helpdesk - Live Monitoring</title>
<link rel="stylesheet" href="css/monitor.css">
<style>
/* Overdue summary card accent; all other dashboard styling stays in css/monitor.css. */
.metric-card.red { background: linear-gradient(110deg, #b91c1c, #450a0a); border-color: rgba(255, 92, 101, 0.45); color: #fff; }
.metric-card.red .icon { color: #ffb4b8; }

/* Additional monitoring table fields */
.priority-badge {
    display: inline-block;
    padding: 4px 7px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
    background: rgba(148, 163, 184, .16);
    color: var(--text, #cbd5e1);
}
.priority-critical { background: rgba(239, 68, 68, .20); color: #f87171; }
.priority-high { background: rgba(245, 158, 11, .20); color: #fbbf24; }
.priority-normal { background: rgba(59, 130, 246, .18); color: #60a5fa; }
.priority-low { background: rgba(34, 197, 94, .18); color: #4ade80; }
.age-value { white-space: nowrap; font-weight: 600; }


.leave-actions{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
.leave-action{border:0;border-radius:6px;padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer}
.leave-action:disabled{opacity:.55;cursor:wait}
.leave-action.approve{background:#dcfce7;color:#166534}
.leave-action.reject{background:#fee2e2;color:#991b1b}
.leave-row{align-items:flex-start}
</style>
</head>
<body>
<div class="dashboard">
<header>
    <div class="brand">
        <div class="brand-icon">▣</div>
        <div>
            <h1>MIS HELPDESK <span>LIVE MONITORING</span></h1>
            <p>IT SUPPORT OPERATIONS</p>
        </div>
    </div>
    <div class="header-right">
        <div class="clock">
            <span id="currentDate"></span>
            <strong id="currentTime">--:--:--</strong>
        </div>
        <div>
            <div class="live"><span class="live-dot"></span>LIVE</div>
            <div class="sub">Auto refresh: 10s</div>
        </div>
        <button id="themeToggle" class="theme" type="button">☀️ Light Mode</button>
    </div>
</header>

<section class="summary">
    <div class="summary-head">
        <h2>TASK SUMMARY</h2>
        <select id="periodFilter" class="period" aria-label="Summary period">
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
            <option value="year">This Year</option>
        </select>
    </div>
    <div class="metric-grid">
        <article class="metric-card blue">
            <div class="icon">▤</div>
            <div><div id="totalTasks" class="number">0</div><div class="label">Total Tasks</div></div>
        </article>
        <article class="metric-card green">
            <div class="icon">✓</div>
            <div><div id="completedTasks" class="number">0</div><div class="label">Completed</div></div>
        </article>
        <article class="metric-card orange">
            <div class="icon">◷</div>
            <div><div id="pendingTasks" class="number">0</div><div class="label">Pending</div></div>
        </article>
        <article class="metric-card purple">
            <div class="icon">⚙</div>
            <div><div id="progressTasks" class="number">0</div><div class="label">In Progress</div></div>
        </article>
        <article class="metric-card red">
            <div class="icon">⚠</div>
            <div><div id="overdueTasks" class="number">0</div><div class="label">Overdue</div></div>
        </article>
    </div>
</section>

<main class="content-grid">
    <section class="panel requests-panel">
        <div class="panel-head">
            <div>
                <h2><span class="panel-icon">📢</span>INCOMING &amp; ACTIVE TASKS</h2>
                <p>Latest 10 requests (Newest first)</p>
            </div>
            <span id="requestCount" class="count">0</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>#</th><th>REPORT NO.</th><th>DATE / TIME</th>
                    <th>REQUEST / ISSUE</th><th>COMPANY</th>
                    <th>ASSIGNED TO</th><th>PRIORITY</th><th>DUE DATE</th>
                    <th>AGE / WAITING</th><th>STATUS</th>
                </tr></thead>
                <tbody id="incomingRequests">
                    <tr><td colspan="10" class="empty">Loading tasks...</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel team-panel">
        <div class="panel-head">
            <h2><span class="panel-icon">👥</span>IT TEAM STATUS</h2>
            <span class="count">LIVE</span>
        </div>
        <div id="teamItems" class="rows"><div class="empty">Loading team...</div></div>
    </section>

    <section class="panel leave-panel">
        <div class="panel-head">
            <div><h2><span class="panel-icon">▦</span>INCOMING LEAVE</h2>
            <p>Pending and upcoming approved leave</p></div>
            <span id="leaveCount" class="count">0</span>
        </div>
        <div id="leaveItems" class="rows"><div class="empty">Loading leave...</div></div>
    </section>

    <section class="panel attention-panel">
        <div class="panel-head">
            <div><h2><span class="panel-icon">⚠</span>ATTENTION NEEDED</h2>
            <p>Overdue and unassigned tasks</p></div>
            <span id="attentionCount" class="count">0</span>
        </div>
        <div id="attentionItems" class="rows"><div class="empty">Checking tasks...</div></div>
    </section>
</main>

<section class="network">
    <h2>⌁ NETWORK &amp; INTERNET HEALTH</h2>
    <div class="network-grid">
        <div class="net-item">Office Gateway<div class="net-status">Configure IP</div></div>
        <div class="net-item">MIS Server<div class="net-status">Local server</div></div>
        <div class="net-item">File Server<div class="net-status">Configure IP</div></div>
        <div class="net-item">Internet<div class="net-status">Not yet configured</div></div>
    </div>
</section>

<footer>
    <span>LIVE MONITORING · LEAVE REVIEW RESTRICTED TO MIS HEAD</span>
    <span id="lastUpdated">Connecting to database...</span>
</footer>
</div>

<div id="requestPopup" class="popup" role="status" aria-live="polite">
    <button id="closePopup" class="popup-close" type="button">×</button>
    <strong style="color:var(--blue)">🔔 NEW INCOMING REQUEST</strong>
    <h2 id="popupTitle">New request</h2>
    <p id="popupReport"></p>
    <p id="popupDetails"></p>
    <small id="popupCountdown"></small>
</div>
<button id="soundToggle" class="sound" type="button">🔊 Sound On · activate</button>

<script>
const canReviewLeave = <?= $canReviewLeave ? 'true' : 'false' ?>;
const leaveReviewCsrf = <?= json_encode($monitorLeaveCsrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

"use strict";

const $ = id => document.getElementById(id);
const esc = value => String(value ?? "").replace(/[&<>"']/g, c => ({
    "&":"&amp;", "<":"&lt;", ">":"&gt;", '"':"&quot;", "'":"&#039;"
})[c]);

let firstLoad = true;
let knownTaskIds = new Set();
let soundEnabled = localStorage.getItem("misMonitorSoundEnabled") !== "false";
let soundUnlocked = false;
const notificationAudio = new Audio(new URL("sounds/notification.mp3", document.baseURI).href);
notificationAudio.preload = "auto";
let popupTimeout = null;
let countdownInterval = null;

function updateSoundButton() {
    const button = $("soundToggle");
    if (!button) return;

    if (!soundEnabled) {
        button.textContent = "🔇 Sound Off";
        button.setAttribute("aria-pressed", "false");
    } else {
        button.textContent = soundUnlocked ? "🔊 Sound On" : "🔊 Sound On · click to enable";
        button.setAttribute("aria-pressed", "true");
    }
}

function updateClock() {
    const now = new Date();
    $("currentDate").textContent = now.toLocaleDateString([], {
        weekday:"long", month:"long", day:"numeric", year:"numeric"
    });
    $("currentTime").textContent = now.toLocaleTimeString();
}
updateClock();
setInterval(updateClock, 1000);

$("themeToggle").addEventListener("click", () => {
    document.body.classList.toggle("light");
    $("themeToggle").textContent = document.body.classList.contains("light")
        ? "🌙 Dark Mode" : "☀️ Light Mode";
});

async function unlockNotificationSound() {
    if (soundUnlocked) {
        updateSoundButton();
        return true;
    }

    try {
        // A user gesture is required by browsers before notification audio can play.
        notificationAudio.muted = true;
        notificationAudio.currentTime = 0;
        await notificationAudio.play();
        notificationAudio.pause();
        notificationAudio.currentTime = 0;
        notificationAudio.muted = false;
        soundUnlocked = true;
        updateSoundButton();
        return true;
    } catch (error) {
        notificationAudio.muted = false;
        updateSoundButton();
        console.warn("Notification sound is not unlocked yet:", error);
        return false;
    }
}

function saveSoundPreference() {
    try {
        localStorage.setItem("misMonitorSoundEnabled", soundEnabled ? "true" : "false");
    } catch (error) {
        console.warn("Unable to save sound preference:", error);
    }
}

function toggleSound() {
    // If this is the first click on the sound control, use it to activate audio
    // instead of immediately turning the default ON state OFF.
    if (!soundUnlocked) {
        soundEnabled = true;
        saveSoundPreference();
        unlockNotificationSound();
        return;
    }

    soundEnabled = !soundEnabled;
    saveSoundPreference();
    updateSoundButton();

    if (!soundEnabled) {
        notificationAudio.pause();
        notificationAudio.currentTime = 0;
    }
}

// Unlock audio on the first interaction anywhere except the sound button itself.
// The sound button has its own handler so its first click cannot accidentally mute audio.
function firstInteractionUnlock(event) {
    if (event.target && event.target.closest && event.target.closest("#soundToggle")) return;
    if (!soundUnlocked) unlockNotificationSound();
}
document.addEventListener("pointerdown", firstInteractionUnlock, { once: true });
document.addEventListener("keydown", firstInteractionUnlock, { once: true });

function playSound() {
    if (!soundEnabled) return;

    try {
        notificationAudio.pause();
        notificationAudio.currentTime = 0;
        notificationAudio.play().catch(error => {
            console.warn("Notification sound playback failed. Click Sound On to activate it:", error);
            soundUnlocked = false;
            updateSoundButton();
        });
    } catch (error) {
        console.warn("Notification sound playback failed:", error);
        soundUnlocked = false;
        updateSoundButton();
    }
}

$("soundToggle").addEventListener("click", toggleSound);
updateSoundButton();

function hidePopup() {
    $("requestPopup").classList.remove("show");
    clearTimeout(popupTimeout);
    clearInterval(countdownInterval);
}
$("closePopup").addEventListener("click", hidePopup);

function showPopup(task) {
    hidePopup();
    $("popupTitle").textContent = task.title || "New request";
    $("popupReport").textContent = "Report No: " + (task.report_id || task.task_id || "—");
    $("popupDetails").textContent =
        (task.company_name || "Unknown company") + " · " +
        (task.requested_by || "Requester not specified");

    let seconds = 10;
    $("popupCountdown").textContent = "Closing in " + seconds + "s";
    $("requestPopup").classList.add("show");
    playSound();

    countdownInterval = setInterval(() => {
        seconds--;
        $("popupCountdown").textContent = seconds > 0 ? "Closing in " + seconds + "s" : "";
    }, 1000);
    popupTimeout = setTimeout(hidePopup, 10000);
}

function statusClass(status) {
    const s = String(status || "").toLowerCase();
    if (s === "new") return "new";
    if (s === "completed" || s === "complete") return "completed";
    if (s === "ongoing" || s === "assigned" || s.includes("progress")) return "ongoing";
    return "pending";
}

function formatDate(value) {
    if (!value) return "—";
    const d = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleString([], {
        month:"short", day:"2-digit", hour:"2-digit", minute:"2-digit"
    });
}

function formatShortDate(value) {
    if (!value) return "—";
    const d = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleString([], {
        month: "short", day: "2-digit", year: "numeric"
    });
}

function formatAge(value) {
    if (!value) return "—";
    const created = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(created.getTime())) return "—";

    const minutes = Math.max(0, Math.floor((Date.now() - created.getTime()) / 60000));
    const days = Math.floor(minutes / 1440);
    const hours = Math.floor((minutes % 1440) / 60);
    const mins = minutes % 60;

    if (days > 0) return days + "d " + hours + "h";
    if (hours > 0) return hours + "h " + mins + "m";
    return mins + "m";
}

function priorityClass(priority) {
    const p = String(priority || "").toLowerCase();
    if (p.includes("critical") || p.includes("urgent")) return "priority-critical";
    if (p === "high") return "priority-high";
    if (p === "low") return "priority-low";
    return "priority-normal";
}

function renderRequests(items) {
    $("requestCount").textContent = items.length;
    if (!items.length) {
        $("incomingRequests").innerHTML =
            '<tr><td colspan="10" class="empty">No requests found in the database.</td></tr>';
        return;
    }

    $("incomingRequests").innerHTML = items.map((t, i) => `
        <tr>
            <td>${i + 1}</td>
            <td><span class="report">${esc(t.report_id || t.task_id)}</span></td>
            <td>${esc(formatDate(t.created_at))}</td>
            <td><strong>${esc(t.title || "Untitled task")}</strong>
                <div class="sub">${esc(t.category || "")}</div></td>
            <td>${esc(t.company_name || "—")}</td>
            <td>${esc(t.assigned_to_name || "Unassigned")}</td>
            <td><span class="priority-badge ${priorityClass(t.task_priority)}">${esc(t.task_priority || "—")}</span></td>
            <td>${esc(formatShortDate(t.due_date))}</td>
            <td><span class="age-value">${esc(formatAge(t.created_at))}</span></td>
            <td><span class="badge ${statusClass(t.status)}">${esc(t.display_status || t.status)}</span></td>
        </tr>
    `).join("");
}

function renderTeam(items) {
    if (!items.length) {
        $("teamItems").innerHTML = '<div class="empty">No users found.</div>';
        return;
    }
    $("teamItems").innerHTML = items.map(u => {
        const state = String(u.presence_status || "Available").trim().toLowerCase();
        const cls = state === "on leave"
            ? "status-on-leave"
            : (["available", "busy", "away"].includes(state) ? state : "available");
        return `<div class="data-row">
            <div class="row-main">
                <strong>${esc(u.full_name || "MIS User")}</strong>
                <div class="row-meta">${esc(u.position || "")}</div>
            </div>
            <span class="presence ${cls}">${esc(u.presence_status || "Available")}</span>
        </div>`;
    }).join("");
}

function renderLeave(items, error) {
    $("leaveCount").textContent = items.length;
    if (error) {
        $("leaveItems").innerHTML = '<div class="empty">Leave table not ready. Check that leave_requests exists.</div>';
        return;
    }
    if (!items.length) {
        $("leaveItems").innerHTML = '<div class="empty">No pending or upcoming approved leave.</div>';
        return;
    }

    $("leaveItems").innerHTML = items.map(l => {
        const pending = String(l.status).toLowerCase() === "pending";
        const dates = `${esc(formatShortDate(l.start_date))}${l.end_date && l.end_date !== l.start_date ? " – " + esc(formatShortDate(l.end_date)) : ""}`;
        return `<div class="data-row leave-row">
            <div class="row-main">
                <strong>${esc(l.full_name || "Unknown employee")} · ${esc(l.leave_type)}</strong>
                <div class="row-meta">${dates}</div>
                <div class="row-meta">Reason: ${esc(l.reason || "Not provided")}</div>
                ${canReviewLeave && pending ? `
                    <div class="leave-actions">
                        <button type="button" class="leave-action approve" data-leave-action="approve" data-leave-id="${Number(l.leave_id)}">Approve</button>
                        <button type="button" class="leave-action reject" data-leave-action="reject" data-leave-id="${Number(l.leave_id)}">Reject</button>
                    </div>` : ""}
            </div>
            <span class="badge ${String(l.status).toLowerCase() === "approved" ? "completed" : String(l.status).toLowerCase() === "rejected" ? "cancelled" : "pending"}">${esc(l.status)}</span>
        </div>`;
    }).join("");
}

async function reviewLeave(leaveId, action) {
    if (!canReviewLeave) {
        alert("Only the MIS Head can approve or reject leave requests.");
        return;
    }

    let remarks = "";
    if (action === "reject") {
        remarks = window.prompt("Enter the reason for rejecting this leave request:");
        if (remarks === null) return;
        remarks = remarks.trim();
        if (!remarks) {
            alert("Please provide a rejection reason.");
            return;
        }
    } else {
        remarks = window.prompt("Optional approval remarks:", "") ?? "";
    }

    const buttons = document.querySelectorAll(`[data-leave-id="${leaveId}"]`);
    buttons.forEach(button => button.disabled = true);

    try {
        const response = await fetch("action/review_leave.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                leave_id: leaveId,
                action,
                remarks,
                csrf: leaveReviewCsrf
            })
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || "Unable to update leave request.");
        }
        alert(result.message);
        await refreshDashboard();
    } catch (error) {
        alert(error.message || "Unable to update leave request.");
        buttons.forEach(button => button.disabled = false);
    }
}

$("leaveItems").addEventListener("click", event => {
    const button = event.target.closest("[data-leave-action]");
    if (!button) return;
    reviewLeave(Number(button.dataset.leaveId), button.dataset.leaveAction);
});

function renderAttention(items) {
    $("attentionCount").textContent = items.length;
    if (!items.length) {
        $("attentionItems").innerHTML = '<div class="empty">No overdue or unassigned tasks.</div>';
        return;
    }
    $("attentionItems").innerHTML = items.map(t => `
        <div class="data-row attention-row">
            <div class="row-main">
                <strong>${esc(t.reason)}: ${esc(t.title || "Untitled task")}</strong>
                <div class="row-meta">Report ${esc(t.report_id || t.task_id)} · ${esc(t.company_name || "—")}</div>
            </div>
        </div>
    `).join("");
}

async function refreshDashboard() {
    try {
        const url = new URL(window.location.href);
        url.searchParams.set("ajax", "1");
        url.searchParams.set("period", $("periodFilter").value);
        url.searchParams.set("_", Date.now());

        const response = await fetch(url, { cache: "no-store" });
        const data = await response.json();

        if (!response.ok || !data.ok) {
            throw new Error(data.message || "Unable to load dashboard.");
        }

        $("totalTasks").textContent = data.metrics.total;
        $("completedTasks").textContent = data.metrics.completed;
        $("pendingTasks").textContent = data.metrics.pending;
        $("progressTasks").textContent = data.metrics.in_progress;
        $("overdueTasks").textContent = data.metrics.overdue ?? 0;

        renderRequests(data.requests || []);
        renderTeam(data.team || []);
        renderLeave(data.leave || [], data.leave_error);
        renderAttention(data.attention || []);

        const currentIds = new Set((data.requests || []).map(t => String(t.task_id)));
        if (firstLoad) {
            knownTaskIds = currentIds;
            firstLoad = false;
        } else {
            const newlySeen = (data.requests || []).filter(t =>
                !knownTaskIds.has(String(t.task_id))
            );
            if (newlySeen.length) showPopup(newlySeen[0]);
            currentIds.forEach(id => knownTaskIds.add(id));
        }

        $("lastUpdated").textContent = "Last updated: " + new Date().toLocaleTimeString();
    } catch (error) {
        console.error("Dashboard refresh failed:", error);
        $("lastUpdated").textContent = "Database/API error — check PHP error log";
    }
}

$("periodFilter").addEventListener("change", refreshDashboard);
refreshDashboard();
setInterval(refreshDashboard, 10000);
</script>
</body>
</html>
