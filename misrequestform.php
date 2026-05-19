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

$next_report_id = $row['last_id'] ? $row['last_id'] + 1 : 100;
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
		<div class="btn-container">
			<h4 class="title">MIS Job Order Form</h4>
			<a href="login.php" class="loginportal-btn">Admin Login</a>
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
				<label>Title</label>
				<input name="title" type="text" class="input-1" placeholder="Enter title" required>
			</div>

			<div class="input-holder">
				<label>Description</label>
				<textarea name="description" class="input-1" placeholder="Enter Description" required></textarea>
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
				<label>Assign To</label>
				<select name="assigned_to" class="input-1" required>
					<option value="">Select User</option>
					<?php foreach ($users as $user) { ?>
						<option value="<?=$user['id']?>"><?=$user['full_name']?></option>
					<?php } ?>
				</select>
			</div>

			<button class="edit-btn">Done</button>

		</form>
	</section>

</body>
</html>