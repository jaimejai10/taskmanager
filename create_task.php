<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['user_id']) && $_SESSION['role'] == "admin") {
    include "DB_connection.php";
    include "app/Model/User.php";
	include "app/Model/Company.php";

    $users = get_all_users($conn);
	$companies = get_all_companies($conn);


include "DB_connection.php"; 

// Get the next report_id
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

</head>
<body>
	<input type="checkbox" id="checkbox">
	<?php include "inc/header.php" ?>
	<div class="body">
		<?php include "inc/nav.php" ?>
		<section class="section-1">
			<h4 class="title">Create Task </h4>
		   <form class="form-1"
			      method="POST"
			      action="app/add-task.php">
			      <?php if (isset($_GET['error'])) {?>
      	  	<div class="danger" role="alert">
			  <?php echo stripcslashes($_GET['error']); ?>
			</div>
      	  <?php } ?>

      	  <?php if (isset($_GET['success'])) {?>
      	  	<div class="success" role="alert">
			  <?php echo stripcslashes($_GET['success']); ?>
			</div>
      	  <?php } ?>
				<div class="input-holder">
					<label>Service Report No. *</label>
					<input type="text" name="report_id" class="input-1" value="<?= $next_report_id ?>" readonly><br>
				</div>
				<div class="input-holder">
					<lable>Title *</lable>
					<input type="text" name="title" class="input-1" placeholder="Title"><br>
				</div>
				<div class="input-holder">
					<lable>Description *</lable>
					<textarea type="text" name="description" class="input-1" placeholder="Description"></textarea><br>
				</div>
				<div class="input-holder">
					<lable>Due Date *</lable>
					<input type="date" name="due_date" class="input-1" placeholder="Due Date"><br>
				</div>
				<div class="input-holder">
					<lable>Company *</lable>
					<select name="company_name" class="input-1">
						<option value="0">Select Company</option>
						<?php if ($companies !=0) { 
							foreach ($companies as $company) {
						?>
                  <option value="<?=$company['comp_name']?>"><?=$company['comp_name']?></option>
						<?php } } ?>
					</select><br>
				</div>
				<div class="input-holder">
					<lable>Assigned to *</lable>
					<select name="assigned_to" class="input-1">
						<option value="0">Select employee</option>
						<?php if ($users !=0) { 
							foreach ($users as $user) {
						?>
                  <option value="<?=$user['user_id']?>"><?=$user['full_name']?></option>
						<?php } } ?>
					</select><br>
				</div>
				<button class="edit-btn">Create Task</button>
			</form>
			
		</section>
	</div>

<script type="text/javascript">
	var active = document.querySelector("#navList li:nth-child(4)");
	active.classList.add("active");
</script>
</body>
</html>
<?php }else{ 
   $em = "First login";
   header("Location: login.php?error=$em");
   exit();
}
 ?>