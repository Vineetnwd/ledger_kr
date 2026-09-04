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
        $name      = mysqli_real_escape_string($conn, trim($_POST['name']));
        $parent_id_raw = $_POST['parent_id'] ?? '';
        $parent_id = ($parent_id_raw !== '' && (int)$parent_id_raw > 0) ? (int)$parent_id_raw : 'NULL';

        $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == '1';

        if (empty($name)) {
            if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success'=>false, 'error'=>"Category name is required."]); exit; }
            $_SESSION['error'] = "Category name is required.";
        } else {
            // If parent_id is set, verify it belongs to this user
            if ($parent_id !== 'NULL') {
                $chk = mysqli_query($conn, "SELECT id FROM categories WHERE id=$parent_id AND user_id=$user_id AND status=1 AND parent_id IS NULL");
                if (mysqli_num_rows($chk) == 0) {
                    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success'=>false, 'error'=>"Invalid parent category."]); exit; }
                    $_SESSION['error'] = "Invalid parent category.";
                    header("Location: " . BASE_URL . "/categories.php");
                    exit();
                }
            }

            $q = mysqli_query($conn, "INSERT INTO categories (user_id, parent_id, name, created_by) VALUES ($user_id, $parent_id, '$name', $user_id)");
            if ($q) {
                if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success'=>true, 'id'=>mysqli_insert_id($conn), 'name'=>$name]); exit; }
                $type = ($parent_id === 'NULL') ? 'Category' : 'Sub-category';
                $_SESSION['success'] = "$type '$name' added successfully!";
            } else {
                if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success'=>false, 'error'=>mysqli_error($conn)]); exit; }
                $_SESSION['error'] = "Error: " . mysqli_error($conn);
            }
        }

    } elseif ($action == 'edit') {
        $cat_id = (int) $_POST['category_id'];
        $name = mysqli_real_escape_string($conn, trim($_POST['name']));
        if (empty($name)) {
            $_SESSION['error'] = "Category name is required.";
        } else {
            $q = mysqli_query($conn, "UPDATE categories SET name='$name', updated_by=$user_id WHERE id=$cat_id AND user_id=$user_id");
            $_SESSION[$q ? 'success' : 'error'] = $q ? "Category updated successfully." : "Failed to update category.";
        }

    } elseif ($action == 'delete') {
        $cat_id = (int) $_POST['category_id'];

        // Check for direct expenses
        $exp_chk = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM expenses WHERE category_id=$cat_id AND status=1");
        $exp_cnt = (int) mysqli_fetch_assoc($exp_chk)['cnt'];

        // Check for sub-categories
        $sub_chk = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM categories WHERE parent_id=$cat_id AND status=1");
        $sub_cnt = (int) mysqli_fetch_assoc($sub_chk)['cnt'];

        if ($exp_cnt > 0) {
            $_SESSION['error'] = "Cannot delete: $exp_cnt expense(s) are linked to this category.";
        } elseif ($sub_cnt > 0) {
            $_SESSION['error'] = "Cannot delete: $sub_cnt sub-category(ies) exist. Delete them first.";
        } else {
            $q = mysqli_query($conn, "UPDATE categories SET status=0, updated_by=$user_id WHERE id=$cat_id AND user_id=$user_id");
            $_SESSION[$q ? 'success' : 'error'] = $q ? "Deleted successfully." : "Failed to delete.";
        }
    }
}

header("Location: " . BASE_URL . "/categories.php");
exit();
