<?php
session_start();
include "db_connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_POST['email'];
    $phone = $_POST['phone'] ?? null;
    $region = $_POST['region'];
    $city = $_POST['city'];
    $company_address = $_POST['company_address'];
    $business_proof = null;

    if (isset($_FILES['business_proof']) && $_FILES['business_proof']['error'] == 0) {
        $business_proof = 'uploads/' . basename($_FILES['business_proof']['name']);
        move_uploaded_file($_FILES['business_proof']['tmp_name'], $business_proof);
    }

    $stmt = $conn->prepare("INSERT INTO clients (username, password, email, phone, region, city, company_address, business_proof) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $username, $password, $email, $phone, $region, $city, $company_address, $business_proof);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Registration successful. Please wait for admin approval.";
        header("Location: /admin/public/pages/client_registration.php");
    } else {
        $_SESSION['error'] = "Registration failed. Please try again.";
        header("Location: /admin/public/pages/client_registration.php");
    }

    $stmt->close();
    $conn->close();
}
?>