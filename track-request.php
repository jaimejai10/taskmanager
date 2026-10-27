
<?php
session_start();
require_once __DIR__ . "/DB_connection.php";

$request = null;
$errorMessage = "";
$reportNo = trim($_GET['report_id'] ?? '');

if ($reportNo !== '') {
    if (!ctype_digit($reportNo)) {
        $errorMessage = "Please enter a valid Service Report No.";
    } else {
        try {
            $sql = "
                SELECT
                    t.*,
                    c.comp_name,
                    u.full_name AS assignee_name
                FROM tasks AS t
                LEFT JOIN companies AS c
                    ON c.comp_id = t.company_id
                LEFT JOIN users AS u
                    ON u.user_id = t.assigned_id
                WHERE t.report_id = :report_id
                LIMIT 1
            ";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                'report_id' => $reportNo
            ]);

            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                $errorMessage = "No request was found for Service Report No. " . $reportNo . ".";
            }
        } catch (PDOException $e) {
            error_log("Tracking query failed: " . $e->getMessage());
            $errorMessage = "Unable to retrieve your request right now. Please try again later.";
        }
    }
}

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$status = trim((string)($request['status'] ?? 'Pending'));

$timelineSteps = [
    'Pending',
    'Assigned',
    'In Progress',
    'On Hold',
    'Completed',
    'Cancelled'
];

$activeIndex = array_search($status, $timelineSteps, true);

// Unknown statuses are displayed without falsely marking a timeline step.
if ($activeIndex === false) {
    $activeIndex = -1;
}

$submitted = 'Not available';
if ($request) {
    foreach (['created_at', 'submitted_at', 'date_created'] as $dateColumn) {
        if (!empty($request[$dateColumn])) {
            $timestamp = strtotime($request[$dateColumn]);
            if ($timestamp !== false) {
                $submitted = date('M d, Y · h:i A', $timestamp);
            }
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Request | MIS</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .track-result[hidden],
        .track-message[hidden] {
            display: none !important;
        }

        .track-message {
            padding: 13px 15px;
            border-radius: 9px;
            margin-top: 15px;
            font-size: 13px;
            line-height: 1.5;
        }

        .track-message.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .track-message.success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <div class="logo">🖥️</div>
        <div class="header-content">
            <h1>MIS Request</h1>
            <p>IT Helpdesk Tracking</p>
        </div>

        <div class="nav-menu">
            <button class="nav-toggle" type="button"
                    aria-label="Open navigation menu"
                    aria-expanded="false"
                    aria-controls="main-navigation">
                <span></span><span></span><span></span>
            </button>

            <nav class="nav-dropdown" id="main-navigation">
                <a href="index.php">📝 New Request</a>
                <a href="track-request.php">🔎 Track Request</a>
                <a href="login.php">🔐 Login</a>
            </nav>
        </div>
    </div>
</header>

<main class="container">
    <section class="intro">
        <h2>🔎 Track Your Request</h2>
        <p>Enter your Service Report No. to check the status and details of your IT request.</p>

        <form class="track-search" id="trackingForm"
              method="GET" action="track-request.php">
            <div class="field">
                <label for="report_id">Service Report No.</label>
                <input
                    type="text"
                    id="report_id"
                    name="report_id"
                    placeholder="Enter Service Report No. (e.g. 101012)"
                    inputmode="numeric"
                    pattern="[0-9]+"
                    maxlength="12"
                    value="<?= h($reportNo) ?>"
                    required>
            </div>

            <button class="primary-btn" type="submit">
                🔎 Track Request
            </button>
        </form>

        <?php if ($errorMessage !== ''): ?>
            <div class="track-message error" role="alert">
                <?= h($errorMessage) ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="section track-result" id="trackResult"
             <?= $request ? '' : 'hidden' ?>>

        <?php if ($request): ?>
            <div class="request-number">
                <div>
                    <small>Service Report No.</small>
                    <strong><?= h($request['report_id']) ?></strong>
                </div>
                <span class="status-badge">
                    ● <?= h($status) ?>
                </span>
            </div>

            <div class="section-body">
                <div class="detail-grid">
                    <div>
                        <h3 class="section-title">📄 Request Information</h3>

                        <div class="detail-item">
                            <span class="detail-label">Category</span>
                            <div class="detail-value"><?= h($request['category'] ?? 'Not specified') ?></div>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Request Title</span>
                            <div class="detail-value"><?= h($request['title'] ?? 'Not specified') ?></div>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Description</span>
                            <div class="detail-value"><?= nl2br(h($request['description'] ?? 'Not specified')) ?></div>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Priority</span>
                            <div class="detail-value"><?= h($request['priority'] ?? 'Not specified') ?></div>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Due Date</span>
                            <div class="detail-value">
                                <?= !empty($request['due_date']) ? h($request['due_date']) : 'Not specified' ?>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="section-title">👤 Requester Information</h3>

                        <div class="detail-item">
                            <span class="detail-label">Company</span>
                            <div class="detail-value"><?= h($request['comp_name'] ?? 'Not specified') ?></div>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Requested By</span>
                            <div class="detail-value"><?= h($request['requested_by'] ?? 'Not specified') ?></div>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Assigned To</span>
                            <div class="detail-value">
                                <?= h($request['assignee_name'] ?? 'Not yet assigned') ?>
                            </div>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Submitted On</span>
                            <div class="detail-value"><?= h($submitted) ?></div>
                        </div>
                    </div>
                </div>

                <hr style="border:0;border-top:1px solid var(--border);margin:24px 0">

                <h3 class="section-title">📊 Request Status Timeline</h3>

                <div class="timeline">
                    <?php foreach ($timelineSteps as $index => $step): ?>
                        <?php
                            $stepClass = '';
                            $symbol = '·';

                            if ($activeIndex >= 0 && $index < $activeIndex) {
                                $stepClass = 'complete';
                                $symbol = '✓';
                            } elseif ($index === $activeIndex) {
                                $stepClass = 'active';
                                $symbol = '●';
                            }
                        ?>
                        <div class="timeline-step <?= h($stepClass) ?>">
                            <div class="timeline-circle"><?= h($symbol) ?></div>
                            <?= h($step) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <div class="help-panel">
        <strong>ⓘ Need assistance?</strong><br>
        If you cannot find your request, please contact the MIS team.
    </div>

    <footer class="footer">
        MIS IT Helpdesk · For technical assistance, contact the MIS team.
    </footer>
</main>

<script>
(function () {
    const menu = document.querySelector('.nav-menu');

    if (menu) {
        const toggle = menu.querySelector('.nav-toggle');
        const dropdown = menu.querySelector('.nav-dropdown');

        toggle.addEventListener('click', function () {
            const isOpen = dropdown.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(isOpen));
            toggle.setAttribute(
                'aria-label',
                isOpen ? 'Close navigation menu' : 'Open navigation menu'
            );
        });

        document.addEventListener('click', function (event) {
            if (!menu.contains(event.target)) {
                dropdown.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Open navigation menu');
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                dropdown.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Open navigation menu');
            }
        });
    }
})();
</script>

</body>
</html>
