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
	<title>Create Task</title>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="css/style.css">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body class="requestform-body">

	<section class="section-1">
		<h4 class="title">Create Task</h4>

		<form class="form-1" method="POST" action="app/add-task.php">

			<?php if (isset($_GET['error'])) { ?>
				<div class="danger"><?= $_GET['error'] ?></div>
			<?php } ?>

			<?php if (isset($_GET['success'])) { ?>
				<div class="success"><?= $_GET['success'] ?></div>
			<?php } ?>

			<div class="input-holder">
				<label>Service Report No.</label>
				<input type="text" class="input-1" value="<?= $next_report_id ?>" readonly>
			</div>

			<div class="input-holder">
				<label>Title</label>
				<input type="text" name="title" class="input-1">
			</div>

			<div class="input-holder">
				<label>Description</label>
				<textarea name="description" class="input-1"></textarea>
			</div>

			<div class="input-holder">
				<label>Due Date</label>
				<input type="date" name="due_date" class="input-1">
			</div>

			<div class="input-holder">
				<label>Company</label>
				<select name="company_name" class="input-1">
					<option value="">Select Company</option>
					<?php foreach ($companies as $company) { ?>
						<option value="<?=$company['comp_name']?>"><?=$company['comp_name']?></option>
					<?php } ?>
				</select>
			</div>

			<div class="input-holder">
				<label>Assign To</label>
				<select name="assigned_to" class="input-1">
					<option value="">Select User</option>
					<?php foreach ($users as $user) { ?>
						<option value="<?=$user['id']?>"><?=$user['full_name']?></option>
					<?php } ?>
				</select>
			</div>

			<button class="edit-btn">Create Task</button>

		</form>
	</section>

</body>
</html>