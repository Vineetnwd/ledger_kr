<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$start_date = mysqli_real_escape_string($conn, $_GET['start_date'] ?? date('Y-m-01'));
$end_date   = mysqli_real_escape_string($conn, $_GET['end_date']   ?? date('Y-m-t'));

$filter_cat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$filter_src = isset($_GET['src']) ? (int)$_GET['src'] : 0;

$extra_sql = "";
if ($filter_cat > 0) {
    $is_parent_q = mysqli_query($conn, "SELECT id FROM categories WHERE parent_id=$filter_cat AND user_id=$user_id");
    if (mysqli_num_rows($is_parent_q) > 0) {
        $sub_ids = [];
        while ($sub = mysqli_fetch_assoc($is_parent_q)) $sub_ids[] = $sub['id'];
        $sub_ids_str = implode(',', $sub_ids);
        $extra_sql .= " AND e.category_id IN ($filter_cat, $sub_ids_str)";
    } else {
        $extra_sql .= " AND e.category_id = $filter_cat";
    }
}
if ($filter_src > 0) {
    $extra_sql .= " AND e.source_id = $filter_src";
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="expenses_' . $start_date . '_to_' . $end_date . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($out, ['#', 'Date', 'Description', 'Category', 'Source / Bank', 'Amount (INR)']);

$result = mysqli_query($conn, "SELECT e.*, c.name as cat_name, s.source_name FROM expenses e LEFT JOIN categories c ON e.category_id=c.id LEFT JOIN expense_sources s ON e.source_id=s.id WHERE e.user_id=$user_id AND e.status=1 AND e.expense_date BETWEEN '$start_date' AND '$end_date' $extra_sql ORDER BY e.expense_date DESC");

$i = 1; $total = 0;
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($out, [
        $i++,
        $row['expense_date'],
        $row['description'],
        $row['cat_name'],
        $row['source_name'],
        $row['amount']
    ]);
    $total += $row['amount'];
}

fputcsv($out, ['', '', '', '', 'TOTAL', number_format($total, 2)]);
fclose($out);
exit();
