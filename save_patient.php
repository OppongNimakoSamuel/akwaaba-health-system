<?php
session_start();
require 'db.php';
$pdo = getDB();

$data = json_decode(file_get_contents("php://input"), true);

$patient_id = $data['patient_id'];
$full_name = $data['full_name'];
$phone = $data['phone'];
$dob = $data['dob'];
$gender = $data['gender'];
$status = $data['status'];
$email = $data['email'];
$region = $data['region'];
$address = $data['address'];
$nhis_number = $data['nhis_number'];
$nhis_verified = $data['nhis_verified'] ? 1 : 0;

$stmt = $conn->prepare("
    INSERT INTO patients 
    (patient_id, full_name, phone, dob, gender, status, email, region, address, nhis_number, nhis_verified, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$stmt->bind_param(
    "ssssssssssi",
    $patient_id,
    $full_name,
    $phone,
    $dob,
    $gender,
    $status,
    $email,
    $region,
    $address,
    $nhis_number,
    $nhis_verified
);

echo $stmt->execute() ? "success" : "error";
?>