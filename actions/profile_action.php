<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = mysqli_real_escape_string($conn, trim($_POST['name']));
        $email = mysqli_real_escape_string($conn, trim($_POST['email']));

        if (empty($name) || empty($email)) {
            $_SESSION['error'] = "Name and Email cannot be empty.";
        } else {
            // Check if email is already taken by another user
            $check_email = mysqli_query($conn, "SELECT id FROM users WHERE email='$email' AND id != $user_id");
            if (mysqli_num_rows($check_email) > 0) {
                $_SESSION['error'] = "Email address is already in use by another account.";
            } else {
                $q = mysqli_query($conn, "UPDATE users SET name='$name', email='$email', updated_by=$user_id WHERE id=$user_id");
                if ($q) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['success'] = "Profile updated successfully!";
                } else {
                    $_SESSION['error'] = "Failed to update profile: " . mysqli_error($conn);
                }
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['error'] = "Please fill in all password fields.";
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error'] = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $_SESSION['error'] = "New password must be at least 6 characters long.";
        } else {
            // Fetch current password hash
            $user_q = mysqli_query($conn, "SELECT password FROM users WHERE id=$user_id");
            $user = mysqli_fetch_assoc($user_q);

            if ($user && password_verify($current_password, $user['password'])) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $q = mysqli_query($conn, "UPDATE users SET password='$new_hash', updated_by=$user_id WHERE id=$user_id");
                if ($q) {
                    $_SESSION['success'] = "Password changed successfully!";
                } else {
                    $_SESSION['error'] = "Failed to update password in database.";
                }
            } else {
                $_SESSION['error'] = "Current password is incorrect.";
            }
        }
    }
}

header("Location: " . BASE_URL . "/profile.php");
exit();
?>