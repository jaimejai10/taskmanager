<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['user_id'])) {

if (isset($_POST['report_id']) && isset($_POST['title']) && isset($_POST['description']) && isset($_POST['company_name']) && isset($_POST['assigned_to']) && $_SESSION['role'] == 'admin' && isset($_POST['due_date'])) {
	include "../DB_connection.php";

    function validate_input($data) {
	  $data = trim($data);
	  $data = stripslashes($data);
	  $data = htmlspecialchars($data);
	  return $data;
	}

	$report_id = validate_input($_POST['report_id']);
	$title = validate_input($_POST['title']);
	$description = validate_input($_POST['description']);
	$company_name = validate_input($_POST['company_name']);
	$assigned_to = validate_input($_POST['assigned_to']);
	$due_date = validate_input($_POST['due_date']);

	if (empty($report_id)) {
		$em = "Service ID is required";
	    header("Location: ../create_task.php?error=$em");
	    exit();
	}else if (empty($title)) {
		$em = "Title is required";
	    header("Location: ../create_task.php?error=$em");
	    exit();
	}else if (empty($description)) {
		$em = "Description is required";
	    header("Location: ../create_task.php?error=$em");
	    exit();
	}else if (empty($company_name)) {
		$em = "Company Name is required";
	    header("Location: ../create_task.php?error=$em");
	    exit();
	}else if ($assigned_to == 0) {
		$em = "Select User";
	    header("Location: ../create_task.php?error=$em");
	    exit();
	}else {
    
       include "Model/Task.php";
       include "Model/Notification.php";

       $data = array($report_id, $title, $description, $company_name, $assigned_to, $due_date);
       insert_task($conn, $data);

       $notif_data = array("'$title' has been assigned to you. Please review and start working on it", $assigned_to, 'New Task Assigned');
       insert_notification($conn, $notif_data);


       $em = "Task created successfully";
	    header("Location: ../create_task.php?success=$em");
	    exit();

    
	}
}else {
   $em = "Unknown error occurred";
   header("Location: ../create_task.php?error=$em");
   exit();
}

}else{ 
   $em = "First login";
   header("Location: ../create_task.php?error=$em");
   exit();
}