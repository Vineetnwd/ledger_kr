<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$start_date = mysqli_real_escape_string($conn, $_GET['start_date'] ?? date('Y-m-01'));
$end_date = mysqli_real_escape_string($conn, $_GET['end_date'] ?? date('Y-m-t'));

// 1. Calculate Opening Balance (prior to start_date)
$bal_inc = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM incomes WHERE status=1 AND income_date < '$start_date'");
$start_inc = (float) mysqli_fetch_assoc($bal_inc)['total'];

$bal_exp = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE status=1 AND expense_date < '$start_date'");
$start_exp = (float) mysqli_fetch_assoc($bal_exp)['total'];

$opening_balance = $start_inc - $start_exp;

// 2. Fetch Incomes and Expenses in Selected Period
$txns_q = mysqli_query($conn, "
    (SELECT 'expense' as txn_type, e.id, e.amount, e.description, e.expense_date as txn_date, 
     IF(p.name IS NOT NULL, p.name, c.name) as main_cat, 
     IF(p.name IS NOT NULL, c.name, NULL) as sub_cat, 
     s.source_name 
     FROM expenses e 
     LEFT JOIN categories c ON e.category_id=c.id 
     LEFT JOIN categories p ON c.parent_id=p.id
     LEFT JOIN expense_sources s ON e.source_id=s.id 
     WHERE e.status=1 AND e.expense_date BETWEEN '$start_date' AND '$end_date')
    UNION ALL
    (SELECT 'income' as txn_type, i.id, i.amount, i.description, i.income_date as txn_date, 
     i.category as main_cat, 
     NULL as sub_cat, 
     s.source_name 
     FROM incomes i 
     LEFT JOIN expense_sources s ON i.source_id=s.id 
     WHERE i.status=1 AND i.income_date BETWEEN '$start_date' AND '$end_date')
    ORDER BY txn_date ASC, id ASC");

$incomes_sum = 0;
$expenses_sum = 0;
$txns = [];
while ($row = mysqli_fetch_assoc($txns_q)) {
    if ($row['txn_type'] == 'income') {
        $incomes_sum += $row['amount'];
    } else {
        $expenses_sum += $row['amount'];
    }
    $txns[] = $row;
}
$net_change = $incomes_sum - $expenses_sum;
$closing_balance = $opening_balance + $net_change;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ledger Statement — KRNC Expense Ledger</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            font-size: 13px;
            padding: 0;
        }

        /* ===== SCREEN CONTROLS (hidden when printing) ===== */
        .screen-bar {
            background: #8b5cf6;
            padding: 12px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .screen-bar h2 {
            font-size: 15px;
            color: #fff;
            font-weight: 700;
        }

        .screen-bar .btns {
            display: flex;
            gap: 8px;
        }

        .btn-print {
            background: #fff;
            color: #8b5cf6;
            border: none;
            padding: 8px 20px;
            border-radius: 7px;
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-print:hover {
            background: #f5f3ff;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 18px;
            border-radius: 7px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        /* ===== REPORT CONTAINER ===== */
        .report {
            max-width: 1100px;
            margin: 24px auto;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.07);
        }

        /* ===== HEADER ===== */
        .report-header {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            padding: 28px 32px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .report-header .brand {
            font-size: 22px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
        }

        .report-header .brand span {
            opacity: 0.6;
        }

        .report-header .meta {
            text-align: right;
        }

        .report-header .meta p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 12px;
        }

        .report-header .meta strong {
            color: #fff;
            font-size: 13px;
        }

        /* ===== SUMMARY ROW ===== */
        .summary-row {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            background: #faf5ff;
        }

        .summary-box {
            flex: 1;
            padding: 18px 24px;
            border-right: 1px solid #e2e8f0;
        }

        .summary-box:last-child {
            border-right: none;
        }

        .summary-box .s-label {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .summary-box .s-value {
            font-size: 20px;
            font-weight: 800;
            margin: 4px 0 2px;
            letter-spacing: -0.5px;
        }

        .summary-box .s-sub {
            font-size: 11px;
            color: #64748b;
        }

        /* ===== SECTION TITLE ===== */
        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 14px 24px 8px;
            border-bottom: 1px solid #f1f5f9;
        }

        /* ===== TABLES ===== */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            padding: 10px 10px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        tbody td {
            padding: 10px 10px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 12px;
            color: #1e293b;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .amount-col {
            text-align: right;
            font-weight: 600;
            white-space: nowrap;
        }

        .bal-col {
            text-align: right;
            font-weight: 700;
            color: #334155;
            background: #f8fafc;
            white-space: nowrap;
        }

        .opening-row td {
            background: #f1f5f9 !important;
            font-weight: 600;
            color: #475569;
        }

        .total-row td {
            background: #f5f3ff !important;
            font-weight: 800;
            font-size: 13px;
            border-top: 2px solid #ddd6fe;
        }

        .badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 100px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-income {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-expense {
            background: #fee2e2;
            color: #991b1b;
        }

        /* ===== FOOTER ===== */
        .report-footer {
            padding: 14px 24px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .report-footer p {
            font-size: 11px;
            color: #94a3b8;
        }

        /* ===== RESPONSIVE STYLES ===== */
        @media (max-width: 768px) {
            .screen-bar {
                flex-direction: column;
                text-align: center;
                padding: 12px 16px;
                gap: 10px;
            }

            .screen-bar h2 {
                font-size: 13px;
                line-height: 1.3;
            }

            .screen-bar .btns {
                width: 100%;
                justify-content: center;
                gap: 8px;
            }

            .report {
                margin: 12px;
                border-radius: 8px;
            }

            .report-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 20px 24px;
                gap: 16px;
            }

            .report-header .meta {
                text-align: left;
            }

            .summary-row {
                flex-direction: column;
            }

            .summary-box {
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
                padding: 14px 20px;
            }

            .summary-box:last-child {
                border-bottom: none;
            }

            .report-footer {
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }
        }

        /* ===== PRINT STYLES ===== */
        @media print {
            @page {
                size: landscape;
                margin: 10mm;
            }

            body {
                background: #fff;
                padding: 0;
                font-size: 11px;
            }

            .screen-bar {
                display: none !important;
            }

            .report {
                margin: 0;
                border-radius: 0;
                box-shadow: none;
                border: none;
                max-width: 100%;
            }

            .table-responsive {
                overflow-x: visible !important;
            }

            thead th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            thead th,
            tbody td {
                padding: 6px 8px !important;
            }

            .report-header {
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: flex-end !important;
                padding: 24px 32px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .report-header .meta {
                text-align: right !important;
            }

            .summary-row {
                display: flex !important;
                flex-direction: row !important;
                width: 100% !important;
            }

            .summary-box {
                flex: 1 !important;
                border-right: 1px solid #e2e8f0 !important;
                border-bottom: none !important;
                padding: 16px 20px !important;
            }

            .summary-box:last-child {
                border-right: none !important;
            }

            tbody tr:nth-child(even) td {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .opening-row td,
            .total-row td {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .badge {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

    <!-- SCREEN ONLY CONTROLS -->
    <div class="screen-bar">
        <h2>Ledger Statement — <?= date('d M Y', strtotime($start_date)) ?> to
            <?= date('d M Y', strtotime($end_date)) ?></h2>
        <div class="btns">
            <a href="<?= BASE_URL ?>/reports.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"
                class="btn-back">&larr; Back to Reports</a>
            <button class="btn-print" onclick="window.print()">&#128438; Print Statement / Save PDF</button>
        </div>
    </div>

    <!-- REPORT CONTENT -->
    <div class="report">

        <!-- REPORT HEADER -->
        <div class="report-header">
            <div>
                <div class="brand">kr<span>ledger</span></div>
                <div style="color:rgba(255,255,255,0.75); font-size:12px; margin-top:4px;">Unified Ledger Statement
                </div>
            </div>
            <div class="meta">
                <p>Statement for: <strong><?= htmlspecialchars($user_name) ?></strong></p>
                <p>Period: <strong><?= date('d M Y', strtotime($start_date)) ?> &ndash;
                        <?= date('d M Y', strtotime($end_date)) ?></strong></p>
                <p>Generated: <strong><?= date('d M Y, h:i A') ?></strong></p>
            </div>
        </div>

        <!-- SUMMARY BOXES -->
        <div class="summary-row">
            <div class="summary-box">
                <div class="s-label">Opening Balance</div>
                <div class="s-value" style="color: <?= $opening_balance >= 0 ? '#10b981' : '#ef4444' ?>;">
                    &#8377;<?= number_format($opening_balance, 2) ?></div>
                <div class="s-sub">Before <?= date('d M Y', strtotime($start_date)) ?></div>
            </div>
            <div class="summary-box">
                <div class="s-label">Total Income (+)</div>
                <div class="s-value" style="color: #10b981;">&#8377;<?= number_format($incomes_sum, 2) ?></div>
                <div class="s-sub">In selected period</div>
            </div>
            <div class="summary-box">
                <div class="s-label">Total Expenses (-)</div>
                <div class="s-value" style="color: #ef4444;">&#8377;<?= number_format($expenses_sum, 2) ?></div>
                <div class="s-sub">In selected period</div>
            </div>
            <div class="summary-box">
                <div class="s-label">Closing Balance</div>
                <div class="s-value" style="color: <?= $closing_balance >= 0 ? '#10b981' : '#ef4444' ?>;">
                    &#8377;<?= number_format($closing_balance, 2) ?></div>
                <div class="s-sub">As of <?= date('d M Y', strtotime($end_date)) ?></div>
            </div>
        </div>

        <!-- DETAIL TABLE -->
        <div class="section-title">Ledger Transaction History</div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Sub Category</th>
                        <th>Source</th>
                        <th style="text-align:right;">Income (+)</th>
                        <th style="text-align:right;">Expense (-)</th>
                        <th style="text-align:right;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Opening Balance Row -->
                    <tr class="opening-row">
                        <td>—</td>
                        <td><?= date('d M Y', strtotime($start_date)) ?></td>
                        <td><span class="badge" style="background:#e2e8f0; color:#475569;">Opening</span></td>
                        <td colspan="6">Opening Balance brought forward</td>
                        <td class="bal-col">&#8377;<?= number_format($opening_balance, 2) ?></td>
                    </tr>

                    <?php
                    $i = 1;
                    $running_balance = $opening_balance;
                    if (count($txns) == 0):
                        ?>
                        <tr>
                            <td colspan="10" style="text-align:center; padding:20px; color:#94a3b8;">No ledger transactions
                                found in this period.</td>
                        </tr>
                    <?php
                    else:
                        foreach ($txns as $txn):
                            if ($txn['txn_type'] == 'income') {
                                $running_balance += $txn['amount'];
                                $inc_display = '&#8377;' . number_format($txn['amount'], 2);
                                $exp_display = '—';
                                $amt_style = 'color:#10b981; font-weight:600;';
                            } else {
                                $running_balance -= $txn['amount'];
                                $inc_display = '—';
                                $exp_display = '&#8377;' . number_format($txn['amount'], 2);
                                $amt_style = 'color:#ef4444; font-weight:600;';
                            }
                            ?>
                            <tr>
                                <td style="color:#94a3b8;"><?= $i++ ?></td>
                                <td style="white-space:nowrap;"><?= date('d M Y', strtotime($txn['txn_date'])) ?></td>
                                <td>
                                    <span class="badge <?= $txn['txn_type'] == 'income' ? 'badge-income' : 'badge-expense' ?>">
                                        <?= ucfirst($txn['txn_type']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($txn['description'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($txn['main_cat'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($txn['sub_cat'] ?? '—') ?></td>
                                <td style="color:#64748b;"><?= htmlspecialchars($txn['source_name'] ?? '—') ?></td>
                                <td class="amount-col" style="color:#10b981;"><?= $inc_display ?></td>
                                <td class="amount-col" style="color:#ef4444;"><?= $exp_display ?></td>
                                <td class="bal-col">&#8377;<?= number_format($running_balance, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="7" style="text-align:right; padding-right:14px;">Closing Statement Summary</td>
                            <td class="amount-col" style="color:#10b981;">&#8377;<?= number_format($incomes_sum, 2) ?></td>
                            <td class="amount-col" style="color:#ef4444;">&#8377;<?= number_format($expenses_sum, 2) ?></td>
                            <td class="bal-col">&#8377;<?= number_format($closing_balance, 2) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- FOOTER -->
        <div class="report-footer">
            <p>KRNC Expense Ledger &mdash; Unified Transaction Statement</p>
            <p>Report generated on <?= date('d M Y \a\t h:i A') ?></p>
        </div>
    </div>

    <script>
        // Auto-trigger print dialog if ?print=1 is in URL
        if (window.location.search.indexOf('print=1') !== -1) {
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 300);
            });
        }
    </script>
</body>

</html>