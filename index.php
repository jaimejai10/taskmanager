
<?php   
include "DB_connection.php";
include "app/Model/Company.php";

$companies = get_all_companies($conn);

// Get next report_id
$sql = "SELECT MAX(report_id) AS last_id FROM tasks";
$stmt = $conn->prepare($sql);
$stmt->execute();

$row = $stmt->fetch(PDO::FETCH_ASSOC);

// start from 1 if empty
$next_id = $row['last_id'] ? $row['last_id'] + 1 : 1;

// keep 6 digits
$next_report_id = str_pad($next_id, 6, "0", STR_PAD_LEFT);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MIS Request Form</title>

</head>

<body>

<header class="topbar">
    <div class="topbar-inner">
        <div class="logo">🖥️</div>
        <div>
            <h1>MIS Request</h1>
            <p>IT Helpdesk Request Form</p>
        </div>
    </div>
<link rel="stylesheet" href="css/style.css">
</header>

<main class="container">

    <div class="intro">
        <h2>📄 Submit an IT Request</h2>
        <p>Fill out the details below and our MIS team will assist you.</p>
    </div>

    <div class="request-number">
        <div>
            <small>Service Report No.</small>
            <strong>
                <?= htmlspecialchars((string)$next_report_id, ENT_QUOTES, 'UTF-8') ?>
            </strong>
        </div>
        <span class="badge">✓ System Generated</span>
    </div>

    <!-- Connect this form to your PHP submission handler -->
    <form action="submit.php" method="POST"
          enctype="multipart/form-data" id="requestForm">

        <section class="section">
            <div class="section-title">
                <span class="section-icon">📄</span>
                1. Request Information
            </div>

            <div class="section-body">

                <div class="field">
                    <label for="category">Category <span class="required">*</span></label>
                    <select name="category" id="category" required>
                        <option value="">Select Category</option>
                        <option>Network</option>
                        <option>Printer</option>
                        <option>Hardware</option>
                        <option>Software</option>
                        <option>Email</option>
                        <option>Internet</option>
                        <option>Account</option>
                        <option>CCTV</option>
                        <option>Maintenance</option>
                        <option>Device</option>
                        <option>Others</option>
                    </select>
                </div>

                <div class="field">
                    <label for="title">Request Title <span class="required">*</span></label>
                    <input type="text" name="title" id="title"
                           placeholder="e.g. Printer not printing"
                           maxlength="150" required>
                </div>

                <div class="field">
                    <label for="description">
                        Description / Problem Details
                        <span class="required">*</span>
                    </label>
                    <textarea name="description" id="description"
                              maxlength="1000"
                              placeholder="Please describe your issue in detail..."
                              required></textarea>
                    <div class="counter"><span id="charCount">0</span>/1000</div>
                    <div class="hint" id="descriptionHint">
                        Include any error messages or troubleshooting already attempted.
                    </div>
                </div>

                <div class="field">
                    <label for="priority">Priority <span class="required">*</span></label>
                    <select name="priority" id="priority" required>
                        <option value="Low">🟢 Low</option>
                        <option value="Medium" selected>🟠 Medium</option>
                        <option value="High">🔴 High</option>
                        <option value="Urgent">🚨 Urgent</option>
                    </select>
                    <div class="hint">Choose Urgent only for critical business interruptions.</div>
                </div>

                <div class="field">
                    <label for="due_date">
                        Due Date <span class="optional">(Optional)</span>
                    </label>
                    <input type="date" name="due_date" id="due_date">
                </div>

            </div>
        </section>

        <section class="section">
            <div class="section-title">
                <span class="section-icon">👤</span>
                2. Requester Information
            </div>

            <div class="section-body">

                <div class="field">
                    <label for="company_id">Company <span class="required">*</span></label>
                    <select name="company_id" id="company_id" required>
                        <option value="">Select Company</option>
                        <?php foreach ($companies as $company) { ?>
						    <option value="<?=$company['comp_id']?>">
							    <?=$company['comp_name']?>
						    </option>
					    <?php } ?>
                    </select>
                    <div class="hint">Company options should be loaded from your database.</div>
                </div>

                <div class="field">
                    <label for="requested_by">Requested By <span class="required">*</span></label>
                    <input type="text" name="requested_by" id="requested_by"
                           placeholder="Your full name" maxlength="100" required>
                </div>

                <div class="field">
                    <label for="requester_no">Mobile Number <span class="required">*</span></label>
                    <input type="tel" name="requester_no" id="requester_no"
                           placeholder="09XXXXXXXXX"
                           inputmode="numeric"
                           pattern="09[0-9]{9}"
                           maxlength="11" required>
                    <div class="hint">Enter your 11-digit Philippine mobile number.</div>
                </div>

            </div>
        </section>

        <section class="section">
            <div class="section-title">
                <span class="section-icon">⚙️</span>
                3. Assignment
            </div>

            <div class="section-body">

                <div class="field">
                    <label for="assigned_id">Assign To <span class="optional">(Optional)</span></label>
                    <select name="assigned_id" id="assigned_id">
                        <option value="">Assign by MIS team</option>
                        <!-- Populate active technicians from your users table -->
                    </select>
                </div>

                <div class="field">
                    <label for="attachment">
                        Attachment <span class="optional">(Optional)</span>
                    </label>

                    <div class="upload-box">
                        <div class="upload-icon">☁️</div>
                        <strong>Attach a photo or document</strong>
                        <p class="file-hint">JPG, PNG or PDF · Maximum 5 MB per file</p>
                        <input type="file" name="attachment" id="attachment"
                               accept=".jpg,.jpeg,.png,.pdf">
                        <div class="file-list" id="fileName"></div>
                    </div>
                </div>

            </div>
        </section>

        <div class="submit-area">
            <button type="submit" class="submit-btn">
                ➤ Submit Request
            </button>
            <p class="privacy">
                🔒 Your information will be used to process your IT request.
            </p>
        </div>

    </form>

    <div class="footer">
        MIS IT Helpdesk · For technical assistance, contact the MIS team.
    </div>

</main>

<script>
const description = document.getElementById('description');
const charCount = document.getElementById('charCount');
const category = document.getElementById('category');
const descriptionHint = document.getElementById('descriptionHint');
const requesterNo = document.getElementById('requester_no');
const attachment = document.getElementById('attachment');

description.addEventListener('input', function () {
    charCount.textContent = this.value.length;
});

category.addEventListener('change', function () {
    if (this.value === 'Others') {
        descriptionHint.textContent =
            'Please specify the type of request or issue in your description.';
    } else {
        descriptionHint.textContent =
            'Include any error messages or troubleshooting already attempted.';
    }
});

requesterNo.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 11);
});

attachment.addEventListener('change', function () {
    const file = this.files[0];
    const fileName = document.getElementById('fileName');

    if (!file) {
        fileName.textContent = '';
        return;
    }

    const allowedTypes = [
        'image/jpeg',
        'image/png',
        'application/pdf'
    ];

    if (!allowedTypes.includes(file.type)) {
        alert('Please select a JPG, PNG, or PDF file.');
        this.value = '';
        fileName.textContent = '';
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert('The maximum attachment size is 5 MB.');
        this.value = '';
        fileName.textContent = '';
        return;
    }

    fileName.textContent = '✓ ' + file.name;
});

document.getElementById('requestForm').addEventListener('submit', function (e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        this.reportValidity();
        return;
    }

    if (category.value === 'Others' && !description.value.trim()) {
        e.preventDefault();
        alert('Please specify the issue in the description.');
        description.focus();
    }
});
</script>

</body>
</html>
