<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Track Request | MIS</title>
<link rel="stylesheet" href="css/style.css">
<style>
.track-result[hidden], .track-message[hidden] { display: none !important; }
.track-message { padding: 13px 15px; border-radius: 9px; margin-top: 15px; font-size: 13px; line-height: 1.5; }
.track-message.error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
.track-message.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
</style>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <div class="logo">🖥️</div>
        <div class="header-content"><h1>MIS Request</h1><p>IT Helpdesk Tracking</p></div>
        <div class="nav-menu">
            <button class="nav-toggle" type="button" aria-label="Open navigation menu"
                    aria-expanded="false" aria-controls="main-navigation">
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

        <form class="track-search" id="trackingForm">
            <div class="field">
                <label for="report_id">Service Report No.</label>
                <input type="text" id="report_id" name="report_id"
                       placeholder="Enter Service Report No. (e.g. 101012)"
                       inputmode="numeric" pattern="[0-9]+" maxlength="12" required>
            </div>
            <button class="primary-btn" type="submit">🔎 Track Request</button>
        </form>

        <div class="hint">Try sample report numbers: <strong>101012</strong>, <strong>101013</strong>, or <strong>101014</strong>.</div>
        <div id="trackMessage" class="track-message" role="status" hidden></div>
    </section>

    <section class="section track-result" id="trackResult" hidden>
        <div class="request-number">
            <div><small>Service Report No.</small><strong id="resultReportNo">101012</strong></div>
            <span class="status-badge" id="resultStatus">● In Progress</span>
        </div>

        <div class="section-body">
            <div class="detail-grid">
                <div>
                    <h3 class="section-title">📄 Request Information</h3>
                    <div class="detail-item"><span class="detail-label">Category</span><div class="detail-value" id="resultCategory"></div></div>
                    <div class="detail-item"><span class="detail-label">Request Title</span><div class="detail-value" id="resultTitle"></div></div>
                    <div class="detail-item"><span class="detail-label">Description</span><div class="detail-value" id="resultDescription"></div></div>
                    <div class="detail-item"><span class="detail-label">Priority</span><div class="detail-value" id="resultPriority"></div></div>
                    <div class="detail-item"><span class="detail-label">Due Date</span><div class="detail-value" id="resultDueDate"></div></div>
                </div>
                <div>
                    <h3 class="section-title">👤 Requester Information</h3>
                    <div class="detail-item"><span class="detail-label">Company</span><div class="detail-value" id="resultCompany"></div></div>
                    <div class="detail-item"><span class="detail-label">Requested By</span><div class="detail-value" id="resultRequester"></div></div>
                    <div class="detail-item"><span class="detail-label">Assigned To</span><div class="detail-value" id="resultAssignee"></div></div>
                    <div class="detail-item"><span class="detail-label">Submitted On</span><div class="detail-value" id="resultSubmitted"></div></div>
                </div>
            </div>

            <hr style="border:0;border-top:1px solid var(--border);margin:24px 0">
            <h3 class="section-title">📊 Request Status Timeline</h3>
            <div class="timeline" id="resultTimeline"></div>
            <p class="hint" style="margin-top:20px">Demo data only. These results are for visual testing and are not retrieved from your database.</p>
        </div>
    </section>

    <div class="help-panel"><strong>ⓘ Need assistance?</strong><br>If you cannot find your request, please contact the MIS team.</div>
    <footer class="footer">MIS IT Helpdesk · For technical assistance, contact the MIS team.</footer>
</main>

<script>
(function () {
    // GUI demo records only. We will replace this object with a database query in the next step.
    const sampleRequests = {
        "101012": {
            reportNo: "101012",
            status: "In Progress",
            category: "Printer",
            title: "Printer not printing",
            description: "Printer displays a paper jam error even though there is no paper jam. Unable to print documents.",
            priority: "Medium",
            dueDate: "May 30, 2026",
            company: "Mabuhayone Corporation",
            requester: "Jan Ced",
            assignee: "MIS Technician - Jaime Segador",
            submitted: "May 28, 2026 · 10:24 AM",
            timeline: ["Submitted", "Assigned", "In Progress", "On Hold", "Completed", "Cancelled"],
            activeIndex: 2
        },
        "101013": {
            reportNo: "101013",
            status: "Completed",
            category: "Network",
            title: "No internet connection",
            description: "Office computer could not access the internet. Network cable and connection were checked.",
            priority: "High",
            dueDate: "May 29, 2026",
            company: "ECA Resources, Inc./ECA Building",
            requester: "Mary Grace Villas",
            assignee: "MIS Technician - Ferdinand Jereme",
            submitted: "May 27, 2026 · 2:15 PM",
            timeline: ["Submitted", "Assigned", "In Progress", "On Hold", "Completed", "Cancelled"],
            activeIndex: 4
        },
        "101014": {
            reportNo: "101014",
            status: "Pending",
            category: "Software",
            title: "Request to install application",
            description: "Requesting installation of an approved business application on the office computer.",
            priority: "Low",
            dueDate: "Not specified",
            company: "Sample Company",
            requester: "Julius Mendez",
            assignee: "Not yet assigned",
            submitted: "June 2, 2026 · 9:05 AM",
            timeline: ["Submitted", "Assigned", "In Progress", "On Hold", "Completed", "Cancelled"],
            activeIndex: 0
        }
    };

    const form = document.getElementById('trackingForm');
    const result = document.getElementById('trackResult');
    const message = document.getElementById('trackMessage');

    function setText(id, value) {
        document.getElementById(id).textContent = value || 'Not specified';
    }

    function showRequest(data) {
        setText('resultReportNo', data.reportNo);
        setText('resultStatus', '● ' + data.status);
        setText('resultCategory', data.category);
        setText('resultTitle', data.title);
        setText('resultDescription', data.description);
        setText('resultPriority', data.priority);
        setText('resultDueDate', data.dueDate);
        setText('resultCompany', data.company);
        setText('resultRequester', data.requester);
        setText('resultAssignee', data.assignee);
        setText('resultSubmitted', data.submitted);

        const timeline = document.getElementById('resultTimeline');
        timeline.replaceChildren();

        data.timeline.forEach(function (label, index) {
            const step = document.createElement('div');
            step.className = 'timeline-step';

            if (index < data.activeIndex) step.classList.add('complete');
            if (index === data.activeIndex) step.classList.add('active');

            const circle = document.createElement('div');
            circle.className = 'timeline-circle';
            circle.textContent = index < data.activeIndex ? '✓' : (index === data.activeIndex ? '●' : '·');

            step.appendChild(circle);
            step.appendChild(document.createTextNode(label));
            timeline.appendChild(step);
        });

        result.hidden = false;
        message.hidden = true;
        message.className = 'track-message';
        result.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const reportNo = document.getElementById('report_id').value.trim();
        const data = sampleRequests[reportNo];

        if (!data) {
            result.hidden = true;
            message.textContent = 'No demo request found for that number. Try 101012, 101013, or 101014.';
            message.className = 'track-message error';
            message.hidden = false;
            return;
        }

        showRequest(data);
    });

    // Hamburger navigation
    const menu = document.querySelector('.nav-menu');
    if (menu) {
        const toggle = menu.querySelector('.nav-toggle');
        const dropdown = menu.querySelector('.nav-dropdown');

        toggle.addEventListener('click', function () {
            const isOpen = dropdown.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(isOpen));
            toggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
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
