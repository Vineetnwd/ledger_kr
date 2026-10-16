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

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="income_' . $start_date . '_to_' . $end_date . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($out, ['#', 'Date', 'Description', 'Category', 'Source / Account', 'Amount (INR)', 'Added By']);

$result = mysqli_query($conn, "SELECT i.*, s.source_name, u.name as added_by FROM incomes i LEFT JOIN expense_sources s ON i.source_id=s.id LEFT JOIN users u ON i.user_id=u.id WHERE i.status=1 AND i.income_date BETWEEN '$start_date' AND '$end_date' ORDER BY i.income_date DESC");

$i = 1; $total = 0;
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($out, [
        $i++,
        $row['income_date'],
        $row['description'],
        $row['category'],
        $row['source_name'],
        $row['amount'],
        $row['added_by'] ?? '—'
    ]);
    $total += $row['amount'];
}

fputcsv($out, ['', '', '', '', 'TOTAL', number_format($total, 2), '']);
fclose($out);
exit();
?>
