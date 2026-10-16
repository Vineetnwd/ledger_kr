<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'add') {
        $category_id  = (int) $_POST['category_id'];
        $source_id    = (int) $_POST['source_id'];
        $amount       = (float) $_POST['amount'];
        $expense_date = mysqli_real_escape_string($conn, $_POST['expense_date']);
        $description  = mysqli_real_escape_string($conn, trim($_POST['description']));

        if ($category_id < 1 || $source_id < 1 || $amount <= 0) {
            $_SESSION['error'] = "Please fill all required fields correctly.";
        } else {
            $q = mysqli_query($conn, "INSERT INTO expenses (user_id, category_id, source_id, amount, description, expense_date, created_by) VALUES ($user_id, $category_id, $source_id, $amount, '$description', '$expense_date', $user_id)");
            $_SESSION[$q ? 'success' : 'error'] = $q ? "Expense added successfully!" : "Error: " . mysqli_error($conn);
        }
    } elseif ($action == 'delete') {
        $expense_id = (int) $_POST['expense_id'];
        $q = mysqli_query($conn, "UPDATE expenses SET status=0, updated_by=$user_id WHERE id=$expense_id");
        $_SESSION[$q ? 'success' : 'error'] = $q ? "Expense cancelled." : "Failed to cancel expense.";
    }
}

header("Location: " . BASE_URL . "/expenses.php");
exit();
