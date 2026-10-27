<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Retrieve the one-time success message
$taskSuccess = $_SESSION['task_success'] ?? null;
unset($_SESSION['task_success']);

// Generate a new CSRF token for the next submission
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include "DB_connection.php";
include "app/Model/Company.php";
include "app/Model/User.php";

$companies = get_all_companies($conn);
$users = get_all_users($conn);

$sql = "SELECT MAX(report_id) AS last_id FROM tasks";
$stmt = $conn->prepare($sql);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$next_id = $row['last_id'] ? $row['last_id'] + 1 : 1;
$next_report_id = str_pad($next_id, 6, "0", STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MIS Request Form</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="topbar"><div class="topbar-inner">
    <div class="logo">🖥️</div>
    <div class="header-content"><h1>MIS Request</h1><p>IT Helpdesk Request Form</p></div>
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
</div></header>

<main class="container">
    <div class="intro">
        <h2>📄 Submit an IT Request</h2>
        <p>Fill out the details below and our MIS team will assist you.</p>
    </div>

    <div class="request-number">
        <div><small>Service Report No.</small><strong><?= htmlspecialchars((string)$next_report_id, ENT_QUOTES, 'UTF-8') ?></strong></div>
        <span class="badge">✓ System Generated</span>
    </div>

        <form action="action/create_task.php"method="POST" enctype="multipart/form-data" id="requestForm">

        <!-- Hidden OUTPUT START -->
        <input type="hidden" name="report_id" value="<?php echo htmlspecialchars($next_report_id); ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <!-- Hidden OUTPUT END -->

        <section class="section">
            <div class="section-title"><span class="section-icon">📄</span>1. Request Information</div>
            <div class="section-body">
                <div class="field"><label for="category">Category <span class="required">*</span></label>
                    <select name="category" id="category" class="form-control" required>
                        <option value="">Select Category</option>
                        <option value="Network">Network</option>
                        <option value="Internet">Internet</option>
                        <option value="Wi-Fi">Wi-Fi</option>
                        <option value="Printer">Printer</option>
                        <option value="Hardware">Hardware</option>
                        <option value="Software">Software</option>
                        <option value="System/Application">System/Application</option>
                        <option value="Installation Request">Installation Request</option>
                        <option value="Email">Email</option>
                        <option value="Account">Account</option>
                        <option value="Password Reset">Password Reset</option>
                        <option value="CCTV">CCTV</option>
                        <option value="Access Control">Access Control</option>
                        <option value="Data Backup/Recovery">Data Backup/Recovery</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Preventive Maintenance">Preventive Maintenance</option>
                        <option value="Others">Others</option>
                    </select>
                </div>
                <div class="field"><label for="title">Request Title <span class="required">*</span></label><input type="text" name="title" id="title" placeholder="e.g. Printer not printing" maxlength="150" required></div>
                <div class="field"><label for="description">Description / Problem Details <span class="required">*</span></label>
                    <textarea name="description" id="description" maxlength="1000" placeholder="Please describe your issue in detail..." required></textarea>
                    <div class="counter"><span id="charCount">0</span>/1000</div>
                    <div class="hint" id="descriptionHint">Include any error messages or troubleshooting already attempted.</div>
                </div>
                <div class="field"><label for="priority">Priority <span class="required">*</span></label>
                    <select name="priority" id="priority" required><option value="Low">🟢 Low</option>
                        <option value="Medium" selected>🟠 Medium</option>
                        <option value="High">🔴 High</option>
                        <option value="Urgent">🚨 Urgent</option>
                    </select>
                    <div class="hint">Choose Urgent only for critical business interruptions.</div>
                </div>
                <div class="field"><label for="due_date">Due Date <span class="optional">(Optional)</span></label><input type="date" name="due_date" id="due_date"></div>
            </div>
        </section>

        <section class="section">
            <div class="section-title"><span class="section-icon">👤</span>2. Requester Information</div>
            <div class="section-body">
                <div class="field"><label for="company_id">Company <span class="required">*</span></label>
                    <select name="company_id" id="company_id" required><option value="">Select Company</option>
                    <?php foreach ($companies as $company) { ?>
                        <option value="<?= htmlspecialchars((string)$company['comp_id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($company['comp_name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php } ?>
                    </select><div class="hint">Select your affiliated company only.</div>
                </div>
                <div class="field"><label for="requested_by">Requested By <span class="required">*</span></label><input type="text" name="requested_by" id="requested_by" placeholder="Your full name" maxlength="100" required></div>
                <div class="field"><label for="requester_no">Mobile Number <span class="required">*</span></label><input type="tel" name="requester_no" id="requester_no" placeholder="09XXXXXXXXX" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" required><div class="hint">Enter your 11-digit Philippine mobile number.</div></div>
            </div>
        </section>

        <section class="section">
            <div class="section-title"><span class="section-icon">⚙️</span>3. Assignment</div>
            <div class="section-body">
                <div class="field"><label for="assigned_to_id">Assign To <span class="optional">(Optional)</span></label>
                    <select name="assigned_to_id" id="assigned_to_id" class="form-control">
                        <option value="">Select Assign To</option>

                        <?php foreach ($users as $user) { ?>
                            <option value="<?= htmlspecialchars($user['user_id']) ?>">
                                <?= htmlspecialchars($user['full_name']) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="field"><label for="attachment">Attachment <span class="optional">(Optional)</span></label>
                    <div class="upload-box"><div class="upload-icon">☁️</div><strong>Attach a photo or document</strong><p class="file-hint">JPG, PNG or PDF · Maximum 5 MB per file</p><input type="file" name="attachment" id="attachment" accept=".jpg,.jpeg,.png,.pdf"><div class="file-list" id="fileName"></div></div>
                </div>
            </div>
        </section>

        <div class="submit-area"><button type="submit" class="submit-btn">➤ Submit Request</button><p class="privacy">🔒 Your information will be used to process your IT request.</p></div>
    </form>
    <footer class="footer">MIS IT Helpdesk · For technical assistance, contact the MIS team.</footer>
</main>
<script>
const description=document.getElementById('description'),charCount=document.getElementById('charCount'),category=document.getElementById('category'),descriptionHint=document.getElementById('descriptionHint'),requesterNo=document.getElementById('requester_no'),attachment=document.getElementById('attachment');
description.addEventListener('input',function(){charCount.textContent=this.value.length;});
category.addEventListener('change',function(){descriptionHint.textContent=this.value==='Others'?'Please specify the type of request or issue in your description.':'Include any error messages or troubleshooting already attempted.';});
requesterNo.addEventListener('input',function(){this.value=this.value.replace(/\D/g,'').slice(0,11);});
attachment.addEventListener('change',function(){const file=this.files[0],fileName=document.getElementById('fileName');if(!file){fileName.textContent='';return;}const allowed=['image/jpeg','image/png','application/pdf'];if(!allowed.includes(file.type)){alert('Please select a JPG, PNG, or PDF file.');this.value='';fileName.textContent='';return;}if(file.size>5*1024*1024){alert('The maximum attachment size is 5 MB.');this.value='';fileName.textContent='';return;}fileName.textContent='✓ '+file.name;});
document.getElementById('requestForm').addEventListener('submit',function(e){if(!this.checkValidity()){e.preventDefault();this.reportValidity();return;}if(category.value==='Others'&&!description.value.trim()){e.preventDefault();alert('Please specify the issue in the description.');description.focus();}});
</script>

<script>
(function () {
    const menu = document.querySelector('.nav-menu');
    if (!menu) return;
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
})();
</script>


<?php if (is_array($taskSuccess) && isset($taskSuccess['report_id'])): ?>
<div id="successModal" class="success-modal">
    <div class="success-modal-content">
        <div class="success-icon">&#10003;</div>

        <h2>Successfully Saved!</h2>

        <p>
            Your MIS service request has been submitted successfully.
        </p>

        <label for="reportNumber">Service Report No.</label>

        <div class="report-number-box">
            <input
                type="text"
                id="reportNumber"
                value="<?= htmlspecialchars(
                    $taskSuccess['report_id'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                readonly
            >

            <button type="button" id="copyReportNumber">
                Copy
            </button>
        </div>

        <p class="tracking-note">
            Please save this number. You will need it to track your request.
        </p>

        <div id="copyMessage" role="status"></div>

        <button
            type="button"
            class="success-close-button"
            id="closeSuccessModal"
        >
            Done
        </button>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('successModal');
    const reportNumber = document.getElementById('reportNumber');
    const copyButton = document.getElementById('copyReportNumber');
    const copyMessage = document.getElementById('copyMessage');
    const closeButton = document.getElementById('closeSuccessModal');

    copyButton.addEventListener('click', async function () {
        try {
            await navigator.clipboard.writeText(reportNumber.value);
            copyMessage.textContent = 'Report number copied!';
        } catch (error) {
            reportNumber.focus();
            reportNumber.select();

            const copied = document.execCommand('copy');
            copyMessage.textContent = copied
                ? 'Report number copied!'
                : 'Please copy the selected report number manually.';
        }
    });

    closeButton.addEventListener('click', function () {
        modal.remove();
    });
})();
</script>
<?php endif; ?>


</body></html>
