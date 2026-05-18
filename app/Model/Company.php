<?php 

function get_all_companies($conn){
	$sql = "SELECT * FROM companies";
	$stmt = $conn->prepare($sql);
	$stmt->execute();

	if($stmt->rowCount() > 0){
		$companies = $stmt->fetchAll();
	}else $companies = 0;

	return $companies;
}


function insert_company($conn, $data){
	$sql = "INSERT INTO companies (comp_name, comp_address, tin, business_type) 
	        VALUES(?,?,?,?)";
	$stmt = $conn->prepare($sql);
	$stmt->execute($data);
}


function update_company($conn, $data){
	$sql = "UPDATE companies 
	        SET comp_name=?, comp_address=?, tin=?, business_type=? 
	        WHERE comp_id=?";
	$stmt = $conn->prepare($sql);
	$stmt->execute($data);
}


function delete_company($conn, $id){
	$sql = "DELETE FROM companies WHERE comp_id=?";
	$stmt = $conn->prepare($sql);
	$stmt->execute([$id]);
}


function get_company_by_id($conn, $id){
	$sql = "SELECT * FROM companies WHERE comp_id=?";
	$stmt = $conn->prepare($sql);
	$stmt->execute([$id]);

	if($stmt->rowCount() > 0){
		$companies = $stmt->fetch();
	}else $companies = 0;

	return $companies;
}


function count_companies($conn){
	$sql = "SELECT comp_id FROM companies";
	$stmt = $conn->prepare($sql);
	$stmt->execute([]);

	return $stmt->rowCount();
}