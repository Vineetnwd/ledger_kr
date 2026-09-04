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

// 1. Calculate Opening Balance (prior to start_date)
$bal_inc = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM incomes WHERE user_id=$user_id AND status=1 AND income_date < '$start_date'");
$start_inc = (float) mysqli_fetch_assoc($bal_inc)['total'];

$bal_exp = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE user_id=$user_id AND status=1 AND expense_date < '$start_date'");
$start_exp = (float) mysqli_fetch_assoc($bal_exp)['total'];

$opening_balance = $start_inc - $start_exp;

// 2. Fetch Incomes and Expenses in Selected Period
$txns_q = mysqli_query($conn, "
    (SELECT 'expense' as txn_type, e.id, e.amount, e.description, e.expense_date as txn_date, c.name as cat_name, s.source_name 
     FROM expenses e 
     LEFT JOIN categories c ON e.category_id=c.id 
     LEFT JOIN expense_sources s ON e.source_id=s.id 
     WHERE e.user_id=$user_id AND e.status=1 AND e.expense_date BETWEEN '$start_date' AND '$end_date')
    UNION ALL
    (SELECT 'income' as txn_type, i.id, i.amount, i.description, i.income_date as txn_date, i.category as cat_name, s.source_name 
     FROM incomes i 
     LEFT JOIN expense_sources s ON i.source_id=s.id 
     WHERE i.user_id=$user_id AND i.status=1 AND i.income_date BETWEEN '$start_date' AND '$end_date')
    ORDER BY txn_date ASC, id ASC");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="ledger_statement_' . $start_date . '_to_' . $end_date . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($out, ['#', 'Date', 'Type', 'Description', 'Category', 'Source / Bank', 'Income (+)', 'Expense (-)', 'Running Balance']);

// Opening Balance row
fputcsv($out, [
    '—',
    $start_date,
    'Opening Balance',
    'Opening Balance brought forward',
    '—',
    '—',
    '—',
    '—',
    number_format($opening_balance, 2, '.', '')
]);

$i = 1;
$running_balance = $opening_balance;
$incomes_sum = 0;
$expenses_sum = 0;

while ($row = mysqli_fetch_assoc($txns_q)) {
    if ($row['txn_type'] == 'income') {
        $running_balance += $row['amount'];
        $incomes_sum += $row['amount'];
        $inc_display = $row['amount'];
        $exp_display = '';
    } else {
        $running_balance -= $row['amount'];
        $expenses_sum += $row['amount'];
        $inc_display = '';
        $exp_display = $row['amount'];
    }

    fputcsv($out, [
        $i++,
        $row['txn_date'],
        ucfirst($row['txn_type']),
        $row['description'],
        $row['cat_name'],
        $row['source_name'],
        $inc_display ? number_format($inc_display, 2, '.', '') : '—',
        $exp_display ? number_format($exp_display, 2, '.', '') : '—',
        number_format($running_balance, 2, '.', '')
    ]);
}

// Closing Total Summary row
fputcsv($out, [
    '',
    '',
    'Summary',
    'Closing Statement Summary Totals',
    '',
    '',
    number_format($incomes_sum, 2, '.', ''),
    number_format($expenses_sum, 2, '.', ''),
    number_format($running_balance, 2, '.', '')
]);

fclose($out);
exit();
?>
