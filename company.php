<?php 
session_start();

if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {

    include "DB_connection.php";
    include "app/Model/Company.php";

    $companies = get_all_companies($conn);

?>

<!DOCTYPE html>
<html>
<head>
	<title>Manage Companies</title>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="css/style.css">

</head>
<body>
	<input type="checkbox" id="checkbox">
	<?php include "inc/header.php" ?>
	<div class="body">
		<?php include "inc/nav.php" ?>
		<section class="section-1">
			<h4 class="title">Manage Companies<a href="add-company.php">Add Company</a></h4>
			<?php if (isset($_GET['success'])) {?>
      	  	<div class="success" role="alert">
			  <?php echo stripcslashes($_GET['success']); ?>
			</div>
		<?php } ?>
			<?php if ($companies != 0) { ?>
			<table class="main-table">
				<tr>
					<th>ID No.</th>
					<th>Company Name</th>
					<th>Address</th>
					<th>TIN</th>
					<th>Business Type</th>
				</tr>
				<?php $i=0; foreach ($companies as $company) { ?>
				<tr>
					<td><?=++$i?></td>
					<td><?=$company['comp_name']?></td>
					<td><?=$company['comp_address']?></td>
					<td><?=$company['tin']?></td>
					<td><?=$company['business_type']?></td>
				</tr>
			   <?php	} ?>
			</table>
		<?php }else { ?>
			<h3>Empty</h3>
		<?php  }?>
			
		</section>
	</div>

<script type="text/javascript">
	var active = document.querySelector("#navList li:nth-child(2)");
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