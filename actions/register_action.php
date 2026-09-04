<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name     = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email    = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    if (empty($name) || empty($email)) {
        $_SESSION['error'] = "Name and email are required.";
        header("Location: " . BASE_URL . "/register.php");
        exit();
    }

    $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['error'] = "An account with this email already exists.";
        header("Location: " . BASE_URL . "/register.php");
        exit();
    }

    $q = mysqli_query($conn, "INSERT INTO users (name, email, password, status) VALUES ('$name', '$email', '$password', 1)");
    if ($q) {
        $_SESSION['success'] = "Account created! Please sign in.";
        header("Location: " . BASE_URL . "/login.php");
    } else {
        $_SESSION['error'] = "Registration failed. Please try again.";
        header("Location: " . BASE_URL . "/register.php");
    }
    exit();
}

header("Location: " . BASE_URL . "/register.php");
exit();
