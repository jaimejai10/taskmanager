<?php 
include "../DB_connection.php";

function validate_input($data) {
	$data = trim($data);
	$data = stripslashes($data);
	$data = htmlspecialchars($data);
	return $data;
}

function insert_task($conn, $data_task){
	$sql = "INSERT INTO tasks 
		(report_id, category, title, description, company_name, requested_by, assigned_to, due_date, requester_ipadd, device_info)
	VALUES(?,?,?,?,?,?,?,?,?,?)";

	$stmt = $conn->prepare($sql);
	$stmt->execute($data_task);
}

function insert_notification($conn, $notif_data){
	$sql = "INSERT INTO notifications (message, recipient, type) 
	VALUES(?,?,?)";

	$stmt = $conn->prepare($sql);
	$stmt->execute($notif_data);
}


if (
	isset($_POST['report_id']) &&
	isset($_POST['category']) &&
	isset($_POST['title']) &&
	isset($_POST['description']) &&
	isset($_POST['due_date']) &&
	isset($_POST['company_name']) &&
	isset($_POST['requested_by']) &&
	isset($_POST['assigned_to'])
) {

	$report_id   = validate_input($_POST['report_id']);
	$category    = validate_input($_POST['category']);
	$title       = validate_input($_POST['title']);
	$description = validate_input($_POST['description']);
	$due_date    = validate_input($_POST['due_date']);
	$company_name= validate_input($_POST['company_name']);
	$requested_by= validate_input($_POST['requested_by']);
	$assigned_to = validate_input($_POST['assigned_to']);
	$ip_address = $_SERVER['REMOTE_ADDR'];
	$device_info = $_SERVER['HTTP_USER_AGENT'];

	$data_task = array(
		$report_id,
		$category,
		$title,
		$description,
		$company_name,
		$requested_by,
		$assigned_to,
		$due_date,
		$ip_address,
		$device_info
	);

	insert_task($conn, $data_task);

	$notif_data = array(
		"'$title' has been assigned to you. Please review and start working on it",
		$assigned_to,
		'New Task Assigned'
	);

	insert_notification($conn, $notif_data);

	$em = "Task created successfully";
	header("Location: ../misrequestform.php?success=$em");
	exit();

}else{
	$em = "Please fill all required fields";
	header("Location: ../misrequestform.php?error=$em");
	exit();
}
?>