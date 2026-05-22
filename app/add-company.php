<?php
session_start();

if (!isset($_SESSION['role']) || !isset($_SESSION['user_id'])) {
    $em = "First login";
    header("Location: ../add-company.php?error=$em");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Prevent direct access to this script
    header("Location: ../add-company.php");
    exit();
}

// Only process POST here
if (isset($_POST['comp_name'], $_POST['comp_address'], $_POST['tin'], $_POST['business_type']) && $_SESSION['role'] == 'admin') {

    include "../DB_connection.php";
    include "Model/Company.php";

    function validate_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    $comp_name = validate_input($_POST['comp_name']);
    $comp_address = validate_input($_POST['comp_address']);
    $tin = validate_input($_POST['tin']);
    $business_type = validate_input($_POST['business_type']);

    // Validation
    if (empty($comp_name)) {
        $em = "Company name is required";
        header("Location: ../add-company.php?error=$em");
        exit();
    } else if (empty($comp_address)) {
        $em = "Company address is required";
        header("Location: ../add-company.php?error=$em");
        exit();
    } else if (empty($tin)) {
        $em = "TIN is required";
        header("Location: ../add-company.php?error=$em");
        exit();
    } else if (empty($business_type)) {
        $em = "Business type is required";
        header("Location: ../add-company.php?error=$em");
        exit();
    } else {
        $data = [$comp_name, $comp_address, $tin, $business_type];
        insert_company($conn, $data);

        $sm = "Company added successfully";
        header("Location: ../add-company.php?success=$sm");
        exit();
    }

} else {
    // If POST is missing required fields
    $em = "All fields are required";
    header("Location: ../add-company.php?error=$em");
    exit();
}