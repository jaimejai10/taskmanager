<?php 
include "../DB_connection.php";

include "../includes/sms.php";


//fetch companies details start
$company_id = $_POST['company_id'];

$sql = "SELECT comp_name, mobile_no, messenger_link 
        FROM companies 
        WHERE comp_id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);

$company = $stmt->fetch(PDO::FETCH_ASSOC);

$company_name = $company['comp_name'] ?? 'Unknown Company';
$mobile_no = $company['mobile_no'] ?? 'N/A';
$messenger_link = $company['messenger_link'] ?? '#';


//fetch users details start
$assigned_to_id = $_POST['assigned_to_id'];

$sql = "SELECT mobile_no, full_name 
        FROM users 
        WHERE user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$assigned_to_id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

$assigned_name = $user['full_name'] ?? 'Unknown User';
$assigned_mobile = $user['mobile_no'] ?? '';

//validation of cp no of requester and company no
$final_number = !empty($requester_no) ? $requester_no : $mobile_no;


function validate_input($data) {
	$data = trim($data);
	$data = stripslashes($data);
	$data = htmlspecialchars($data);
	return $data;
}

function insert_task($conn, $data_task){
	$sql = "INSERT INTO tasks 
		(report_id, category, title, description, company_id, requested_by, requester_no, assigned_id, due_date, requester_ipadd, device_info)
	VALUES(?,?,?,?,?,?,?,?,?,?,?)";

	$stmt = $conn->prepare($sql);
	$stmt->execute($data_task);
}

function insert_notification($conn, $notif_data){
	$sql = "INSERT INTO notifications (message, recipient, type) 
	VALUES(?,?,?)";

	$stmt = $conn->prepare($sql);
	$stmt->execute($notif_data);
}


if (isset($_POST['report_id']) && isset($_POST['category']) && isset($_POST['title']) && isset($_POST['description']) && isset($_POST['due_date']) && isset($_POST['company_id']) && isset($_POST['requested_by']) && isset($_POST['requester_no']) && isset($_POST['assigned_to_id'])) {

	$report_id   = validate_input($_POST['report_id']);
	$category    = validate_input($_POST['category']);
	$title       = validate_input($_POST['title']);
	$description = validate_input($_POST['description']);
	$due_date    = validate_input($_POST['due_date']);
	$company_id= validate_input($_POST['company_id']);
	$requested_by= validate_input($_POST['requested_by']);
	$requester_no= validate_input($_POST['requester_no']);
	$assigned_to_id = validate_input($_POST['assigned_to_id']);
	$ip_address = $_SERVER['REMOTE_ADDR'];
	$device_info = $_SERVER['HTTP_USER_AGENT'];
	
	$message = "GOOD DAY {$assigned_name},\n\n"
			. "You have been assigned a new task.\n\n"
			. "Company: {$company_name}\n"
			. "Report ID: #{$report_id}\n"
			. "Title: {$title}\n"
			. "Description: {$description}\n\n"
			. "CP No.: {$final_number}\n"
			. "Messenger: {$messenger_link}\n\n"
			. "— MIS Support Team";

	$data_task = array($report_id, $category, $title, $description, $company_id, $requested_by, $requester_no, $assigned_to_id, $due_date, $ip_address, $device_info);

	insert_task($conn, $data_task);

	$notif_data = array(
		"'$title' has been assigned to you. Please review and start working on it",
		$assigned_to_id,
		'New Task Assigned'
	);

	insert_notification($conn, $notif_data);

	send_sms($assigned_mobile, $message);

	$em = "Task created successfully";
	header("Location: ../misrequestform.php?success=$em");
	exit();

}else{
	$em = "Please fill all required fields";
	header("Location: ../misrequestform.php?error=$em");
	exit();
}
?>
