<?php
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/security.php';
function insertImagetoGallery($img)
{
	$con = db();

	$sql = "INSERT INTO gallery(gallery_image) VALUES('$img')";
	return mysqli_query($con, $sql);
}

function addBranch($data)
{
	$con = db();

	$branch_name = $data['branch_name'];
	$sql = "INSERT INTO branch(branch_name, is_deleted) VALUES('$branch_name', 0)";
	return mysqli_query($con, $sql);
}

function addArea($data)
{
	$con = db();

	$area_name = $data['area_name'];


	$count = checkAreaByName($area_name);

	if ($count == 0) {

		$sql = "INSERT INTO area(area_name, is_deleted) VALUES('$area_name', 0)";
		return mysqli_query($con, $sql);
	} else {
		echo json_encode($count);
	}
}

function addPrice($data)
{
	$con = db();

	$start_area = $data['start_area'];
	$end_area = $data['end_area'];
	$price = $data['price'];

	$count = checkPrice($start_area, $end_area);

	if ($count == 0) {

		$sql = "INSERT INTO price_table(start_area, end_area, price ,is_deleted, date_updated) VALUES('$start_area', '$end_area', '$price', 0 , now())";
		return mysqli_query($con, $sql);
	} else {
		echo json_encode($count);
	}
}

function addRequest($data)
{
	$con = db();

	$customer_id = $data['customer_id'];
	$sender_phone = $data['sender_phone'];
	$weight = $data['weight'];
	$send_location = $data['send_location'];
	$end_location = $data['end_location'];
	$total_fee = $data['total_fee'];
	$res_phone = $data['res_phone'];
	$red_address = $data['red_address'];
	$res_name = $data['res_name'];

	$sql = "INSERT INTO request(customer_id, sender_phone, weight, send_location, end_location, total_fee, res_phone, red_address, is_deleted, date_updated, tracking_status, res_name) 
	VALUES('$customer_id', '$sender_phone', '$weight', '$send_location', '$end_location', '$total_fee', '$res_phone', '$red_address', 0 , now(), 1 , '$res_name')";
	return mysqli_query($con, $sql);
}

function addEmployee($data)
{
	$con = db();

	$name = $data['name'];
	$email = $data['email'];
	$phone = $data['phone'];
	$nic = $data['nic'];
	$address = $data['address'];
	$gender = $data['gender'];
	$password = $data['password'];
	$branch_id = $data['branch_id'];


	$count = checkemployeetByEmail($email);

	if ($count == 0) {

		$sql = "INSERT INTO employee(name, email, phone, nic, address, gender, password ,is_deleted, branch_id) VALUES('$name', '$email', '$phone', '$nic', '$address', '$gender', '$password', 0 , '$branch_id')";
		return mysqli_query($con, $sql);
	} else {
		echo json_encode($count);
	}
}


//contact
function addMessage($data)
{
	$con = db();

	/*
	 * Public contact form. Every field is attacker-controlled and none of it was
	 * validated or bound before. Validation rejects malformed input early; the
	 * prepared statement makes the query safe regardless of what gets through.
	 * Both are kept, because validation alone is a filter and filters can be
	 * evaded, while binding is structural.
	 */
	$name    = trim((string) ($data['name'] ?? ''));
	$email   = trim((string) ($data['email'] ?? ''));
	$subject = trim((string) ($data['subject'] ?? ''));
	$message = trim((string) ($data['message'] ?? ''));

	if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		log_security_event('contact_rejected_invalid_input');
		return false;
	}

	if (strlen($name) > 100 || strlen($subject) > 200 || strlen($message) > 5000) {
		log_security_event('contact_rejected_oversize_field');
		return false;
	}

	return db_query(
		"INSERT INTO contact(name, email, subject, message, date_updated) VALUES(?, ?, ?, ?, now())",
		'ssss',
		[$name, $email, $subject, $message]
	)->affected_rows > 0;
}


function createCustomer($data)
{
	$con = db();

	/*
	 * Public registration.
	 *
	 * Two faults here, not one. The obvious fault is the injection. The less
	 * obvious and more serious fault is that the password was written straight
	 * into the table as typed. Migrating the existing rows to bcrypt did not fix
	 * that, because this function kept producing new plaintext rows behind the
	 * migration. A credential-storage fix is only complete when every write path
	 * hashes, not only the ones that existed when the migration ran.
	 */
	$name     = trim((string) ($data['name'] ?? ''));
	$email    = trim((string) ($data['email'] ?? ''));
	$phone    = trim((string) ($data['phone'] ?? ''));
	$nic      = trim((string) ($data['nic'] ?? ''));
	$address  = trim((string) ($data['address'] ?? ''));
	$gender   = trim((string) ($data['gender'] ?? ''));
	$password = (string) ($data['password'] ?? '');

	if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
		log_security_event('registration_rejected_invalid_input', $email);
		return false;
	}

	if (!in_array($gender, ['Male', 'Female', 'Other', ''], true)) {
		log_security_event('registration_rejected_invalid_gender', $email);
		return false;
	}

	// Reject a duplicate address rather than letting the insert fail obscurely.
	$existing = db_select_one("SELECT customer_id FROM customer WHERE email = ?", 's', [$email]);
	if ($existing !== null) {
		log_security_event('registration_rejected_duplicate_email', $email);
		return false;
	}

	$stmt = db_query(
		"INSERT INTO customer(name, email, phone, nic, address, gender, password, is_deleted) VALUES(?, ?, ?, ?, ?, ?, ?, 0)",
		'sssssss',
		[$name, $email, $phone, $nic, $address, $gender, hash_password($password)]
	);

	log_security_event('registration_success', $email);
	return $stmt->affected_rows > 0;
}
