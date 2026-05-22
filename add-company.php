<?php 
session_start();

if (isset($_SESSION['role']) && isset($_SESSION['user_id'])) {

    if (isset($_POST['comp_name']) && isset($_POST['comp_address']) && isset($_POST['tin']) && isset($_POST['business_type']) && $_SESSION['role'] == 'admin') {
        
        include "../DB_connection.php";

        // Function to sanitize input
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

            include "Model/Company.php";

            $data = array($comp_name, $comp_address, $tin, $business_type);
            insert_company($conn, $data);

            $sm = "Company added successfully";
            header("Location: ../add-company.php?success=$sm");
            exit();
        }

    } else {
        $em = "Unknown error occurred";
        header("Location: ../add-company.php?error=$em");
        exit();
    }

} else { 
    $em = "First login";
    header("Location: ../add-company.php?error=$em");
    exit();
}