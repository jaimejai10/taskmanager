<?php 
include "DB_connection.php";
include "app/Model/User.php";
include "app/Model/Company.php";

$users = get_all_users($conn);
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
<html>
<head>
	<title>MIS</title>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="css/style.css">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body class="requestform-body">

	<section class="section-1">
		<div class="header-bar">
			<h2 class="title">MIS Job Order Form</h2>
			<a href="login.php" class="admin-icon-btn">🔐</a>
		</div>

		<form class="form-1" method="POST" action="action/create_task.php">

			<?php if (isset($_GET['error'])) { ?>
				<div class="danger"><?= $_GET['error'] ?></div>
			<?php } ?>

			<?php if (isset($_GET['success'])) { ?>
				<div class="success"><?= $_GET['success'] ?></div>
			<?php } ?>

			<div class="input-holder">
				<label>Service Report No.</label>
				<input name="report_id" type="text" class="input-1" value="<?= $next_report_id ?>" readonly>
			</div>
			
			<div class="input-holder">
				<label>Category</label>
				<select name="category" class="input-1" id="category" onchange="showHint()">
					<option value="" disabled selected>Select Category</option>
					<option value="Network">Network</option>
					<option value="Printer">Printer</option>
					<option value="Hardware">Hardware</option>
					<option value="Software">Software</option>
					<option value="Email">Email</option>
					<option value="Internet">Internet</option>
					<option value="Account">Account</option>
					<option value="CCTV">CCTV</option>
					<option value="Maintenance">Maintenance</option>
					<option value="Device">Device</option>

					<!-- Advanced / Enterprise Level -->
					<option value="Server">Server</option>
					<option value="Cloud / System">Cloud / System</option>
					<option value="Security">Security</option>
					<option value="Backup / Data">Backup / Data</option>
					<option value="User Management">User Management</option>
					<option value="Others">Others</option>
				</select>
			</div>

			<div class="input-holder">
				<label>Request Title</label>
				<input name="title" type="text" class="input-1" placeholder="Request title" required>
			</div>

			<div class="input-holder">
				<label>Description / Problem Details (optional)</label>
				<textarea name="description" id="description" class="input-1" placeholder="Enter Description" ></textarea>
				<small id="description-error" style="color: red; display:none;">
					Description is required when Category is Others.
				</small>
			</div>


			<div class="input-holder">
				<label>Due (optional)</label>
				<input name="due_date" type="date" class="input-1" min="<?= date('Y-m-d') ?>">
			</div>

			<div class="input-holder">
				<label>Company</label>
				<select name="company_name" class="input-1" required>
					<option value="">Select Company</option>
					<?php foreach ($companies as $company) { ?>
						<option value="<?=$company['comp_name']?>"><?=$company['comp_name']?></option>
					<?php } ?>
				</select>
			</div>

			<div class="input-holder">
				<label>Requested By</label>
				<input name="requested_by" type="text" class="input-1" placeholder="Requested By" required>
			</div>

			<div class="input-holder">
				<label>Requester No.</label>
				<input id="requester_no" name="requester_no" type="text" class="input-1" placeholder="09XXXXXXXXX" required>

				<small id="phone-hint" style="color:red; display:none;">
					Please enter a valid Philippine mobile number (09XXXXXXXXX)
				</small>
			</div>

			<div class="input-holder">
				<label>Assign To</label>
				<select name="assigned_to" class="input-1" required>
					<option value="">Select User</option>
					<?php foreach ($users as $user) { ?>
						<option value="<?=$user['user_id']?>"><?=$user['full_name']?></option>
					<?php } ?>
				</select>
			</div>

			<button type="submit" class="edit-btn">Done</button>

		</form>
	</section>

</body>
</html>

<!-- Validation for others -->
<script>
document.addEventListener("DOMContentLoaded", function () {

    const form = document.querySelector("form");
    const category = document.getElementById("category");
    const description = document.getElementById("description");
    const error = document.getElementById("description-error");

    form.addEventListener("submit", function (e) {

        if (category.value === "Others" && description.value.trim() === "") {

            e.preventDefault();

            error.style.display = "block";
            description.focus();

        } else {
            error.style.display = "none";
        }

    });

});

//clear Page Link
window.onload = function () {
    const url = new URL(window.location);

    if (url.searchParams.has("success")) {
        url.searchParams.delete("success");
        window.history.replaceState({}, document.title, url.pathname);
    }
};


document.querySelector("form").addEventListener("submit", function(e) {

    let phone = document.getElementById("requester_no").value.trim();
    let hint = document.getElementById("phone-hint");

    // PH number rule: 09 + 9 digits
    let pattern = /^09\d{9}$/;

    if (!pattern.test(phone)) {

        e.preventDefault(); // stop submit

        hint.style.display = "block";

        document.getElementById("requester_no").focus();
    } else {
        hint.style.display = "none";
    }

});
</script>