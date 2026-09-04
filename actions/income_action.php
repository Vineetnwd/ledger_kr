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
        $category     = mysqli_real_escape_string($conn, trim($_POST['category']));
        $source_id    = (int) $_POST['source_id'];
        $amount       = (float) $_POST['amount'];
        $income_date  = mysqli_real_escape_string($conn, $_POST['income_date']);
        $description  = mysqli_real_escape_string($conn, trim($_POST['description']));

        if (empty($category) || $source_id < 1 || $amount <= 0) {
            $_SESSION['error'] = "Please fill all required fields correctly.";
        } else {
            $q = mysqli_query($conn, "INSERT INTO incomes (user_id, category, source_id, amount, description, income_date, created_by) VALUES ($user_id, '$category', $source_id, $amount, '$description', '$income_date', $user_id)");
            $_SESSION[$q ? 'success' : 'error'] = $q ? "Income added successfully!" : "Error: " . mysqli_error($conn);
        }
    } elseif ($action == 'delete') {
        $income_id = (int) $_POST['income_id'];
        $q = mysqli_query($conn, "UPDATE incomes SET status=0, updated_by=$user_id WHERE id=$income_id AND user_id=$user_id");
        $_SESSION[$q ? 'success' : 'error'] = $q ? "Income transaction cancelled." : "Failed to cancel income transaction.";
    }
}

header("Location: " . BASE_URL . "/incomes.php");
exit();
?>
