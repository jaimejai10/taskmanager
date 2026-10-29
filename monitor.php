
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

function monitorFetch(PDO $conn, string $period): array
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

    // Latest 10 requests, newest first.
    $requests = $conn->query("
        SELECT
            t.task_id,
            t.report_id,
            t.title,
            t.category,
            t.status,
            t.requested_by,
            t.created_at,
            c.comp_name AS company_name
        FROM tasks t
        LEFT JOIN companies c ON c.comp_id = t.company_id
        ORDER BY t.created_at DESC, t.task_id DESC
        LIMIT 10
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

    // IT Team Status: show only MIS staff and their position.
        $team = $conn->query("
            SELECT
                user_id,
                full_name,
                position,
                presence_status,
                updated_at
            FROM users
            WHERE role IN ('MISHead', 'MISUser')
            ORDER BY
                CASE COALESCE(presence_status, 'Available')
                    WHEN 'Available' THEN 1
                    WHEN 'Busy' THEN 2
                    WHEN 'Away' THEN 3
                    ELSE 4
                END,
                full_name ASC
            LIMIT 8
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
            LEFT JOIN users u ON u.user_id = lr.user_id
            WHERE
                LOWER(lr.status) = 'pending'
                OR (
                    LOWER(lr.status) = 'approved'
                    AND lr.end_date >= CURDATE()
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
            monitorFetch($conn, $period),
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
                    <th>REQUEST / ISSUE</th><th>COMPANY</th><th>STATUS</th>
                </tr></thead>
                <tbody id="incomingRequests">
                    <tr><td colspan="6" class="empty">Loading tasks...</td></tr>
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
    <span>MONITORING MODE · READ ONLY</span>
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
<button id="soundToggle" class="sound" type="button">🔇 Enable Sound</button>

<script>
"use strict";

const $ = id => document.getElementById(id);
const esc = value => String(value ?? "").replace(/[&<>"']/g, c => ({
    "&":"&amp;", "<":"&lt;", ">":"&gt;", '"':"&quot;", "'":"&#039;"
})[c]);

let firstLoad = true;
let knownTaskIds = new Set();
let soundEnabled = false;
const notificationAudio = new Audio("sounds/notification.mp3");
notificationAudio.preload = "auto";
let popupTimeout = null;
let countdownInterval = null;

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

async function toggleSound() {
    if (soundEnabled) {
        soundEnabled = false;
        notificationAudio.pause();
        notificationAudio.currentTime = 0;
    } else {
        // Browser audio must be started by a user action. This button also tests the sound.
        try {
            notificationAudio.currentTime = 0;
            await notificationAudio.play();
            soundEnabled = true;
        } catch (e) {
            soundEnabled = false;
            alert("Could not play the notification sound. Check that sounds/notification.mp3 exists and that your browser supports the file.");
        }
    }
    $("soundToggle").textContent = soundEnabled ? "🔊 Sound On" : "🔇 Enable Sound";
}

function playSound() {
    if (!soundEnabled) return;
    try {
        notificationAudio.pause();
        notificationAudio.currentTime = 0;
        notificationAudio.play().catch(error => {
            console.error("Notification sound playback failed:", error);
        });
    } catch (error) {
        console.error("Notification sound playback failed:", error);
    }
}
$("soundToggle").addEventListener("click", toggleSound);

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

function renderRequests(items) {
    $("requestCount").textContent = items.length;
    if (!items.length) {
        $("incomingRequests").innerHTML =
            '<tr><td colspan="6" class="empty">No requests found in the database.</td></tr>';
        return;
    }

    $("incomingRequests").innerHTML = items.map((t, i) => `
        <tr>
            <td>${i + 1}</td>
            <td><span class="report">${esc(t.report_id || t.task_id)}</span></td>
            <td>${esc(formatDate(t.created_at))}</td>
            <td><strong>${esc(t.title || "Untitled task")}</strong>
                <div class="sub">${esc(t.category || "")}</div></td>
            <td>${esc(t.company_name || t.requested_by || "—")}</td>
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
        const state = String(u.presence_status || "Available").toLowerCase();
        const cls = ["available", "busy", "away"].includes(state) ? state : "available";
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
        $("leaveItems").innerHTML = '<div class="empty">No pending or upcoming leave.</div>';
        return;
    }
    $("leaveItems").innerHTML = items.map(l => `
        <div class="data-row">
            <div class="row-main">
                <strong>${esc(l.full_name || "Unknown employee")} · ${esc(l.leave_type)}</strong>
                <div class="row-meta">${esc(l.start_date)}${l.end_date && l.end_date !== l.start_date ? " – " + esc(l.end_date) : ""}</div>
            </div>
            <span class="badge ${String(l.status).toLowerCase() === "approved" ? "completed" : "pending"}">${esc(l.status)}</span>
        </div>
    `).join("");
}

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
