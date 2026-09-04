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

// Summary
$sum_r = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total, COUNT(*) as cnt FROM incomes i WHERE i.user_id=$user_id AND i.status=1 AND i.income_date BETWEEN '$start_date' AND '$end_date'");
$summary = mysqli_fetch_assoc($sum_r);
$grand_total = (float) $summary['total'];
$total_cnt = (int) $summary['cnt'];

// Category breakdown
$cat_r = mysqli_query($conn, "SELECT category, COALESCE(SUM(amount),0) as total, COUNT(*) as cnt FROM incomes WHERE user_id=$user_id AND status=1 AND income_date BETWEEN '$start_date' AND '$end_date' GROUP BY category ORDER BY total DESC");

// Incomes
$inc_r = mysqli_query($conn, "SELECT i.*, s.source_name FROM incomes i LEFT JOIN expense_sources s ON i.source_id=s.id WHERE i.user_id=$user_id AND i.status=1 AND i.income_date BETWEEN '$start_date' AND '$end_date' ORDER BY i.income_date ASC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income Report — KRNC Ledger</title>
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
            background: #10b981;
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
            color: #10b981;
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
            background: #e6fbf4;
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
            max-width: 860px;
            margin: 24px auto;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.07);
        }

        /* ===== HEADER ===== */
        .report-header {
            background: linear-gradient(135deg, #10b981, #059669);
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
            font-size: 22px;
            font-weight: 800;
            color: #059669;
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
        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            padding: 8px 14px;
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
            padding: 9px 14px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 12px;
            color: #1e293b;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:nth-child(even) td {
            background: #fafafa;
        }

        .amount-col {
            text-align: right;
            font-weight: 700;
        }

        .total-row td {
            background: #e6fbf4 !important;
            font-weight: 800;
            font-size: 13px;
            border-top: 2px solid #a7f3d0;
            color: #065f46;
        }

        .badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 100px;
            font-size: 10px;
            font-weight: 600;
            background: #d1fae5;
            color: #065f46;
        }

        /* ===== CAT TABLE ===== */
        .cat-section {
            padding: 0;
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
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .report-footer {
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }
        }

        /* ===== PRINT STYLES ===== */
        @media print {
            body {
                background: #fff;
                padding: 0;
                font-size: 12px;
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

            thead th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
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
        <h2>Income Report — <?= date('d M Y', strtotime($start_date)) ?> to <?= date('d M Y', strtotime($end_date)) ?></h2>
        <div class="btns">
            <a href="<?= BASE_URL ?>/reports.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" class="btn-back">&larr; Back</a>
            <button class="btn-print" onclick="window.print()">&#128438; Print / Save PDF</button>
        </div>
    </div>

    <!-- REPORT CONTENT -->
    <div class="report">

        <!-- REPORT HEADER -->
        <div class="report-header">
            <div>
                <div class="brand">KRNC <span>Ledger</span></div>
                <div style="color:rgba(255,255,255,0.75); font-size:12px; margin-top:4px;">Income Report</div>
            </div>
            <div class="meta">
                <p>Prepared for: <strong><?= htmlspecialchars($user_name) ?></strong></p>
                <p>Period: <strong><?= date('d M Y', strtotime($start_date)) ?> &ndash; <?= date('d M Y', strtotime($end_date)) ?></strong></p>
                <p>Generated: <strong><?= date('d M Y, h:i A') ?></strong></p>
            </div>
        </div>

        <!-- SUMMARY BOXES -->
        <div class="summary-row">
            <div class="summary-box">
                <div class="s-label">Total Amount</div>
                <div class="s-value">&#8377;<?= number_format($grand_total, 2) ?></div>
                <div class="s-sub">In selected period</div>
            </div>
            <div class="summary-box">
                <div class="s-label">Deposits</div>
                <div class="s-value"><?= $total_cnt ?></div>
                <div class="s-sub">Total income records</div>
            </div>
            <div class="summary-box">
                <div class="s-label">Avg Deposit</div>
                <div class="s-value">&#8377;<?= $total_cnt > 0 ? number_format($grand_total / $total_cnt, 2) : '0.00' ?></div>
                <div class="s-sub">Average per deposit</div>
            </div>
        </div>

        <!-- CATEGORY BREAKDOWN -->
        <?php if (mysqli_num_rows($cat_r) > 0): ?>
            <div class="section-title">Category Breakdown</div>
            <div class="cat-section">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Category</th>
                                <th>Deposits</th>
                                <th style="text-align:right;">Total Amount</th>
                                <th style="text-align:right;">Share %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $ci = 1;
                            while ($row = mysqli_fetch_assoc($cat_r)):
                                $share = $grand_total > 0 ? round(((float) $row['total'] / $grand_total) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td style="color:#94a3b8;"><?= $ci++ ?></td>
                                    <td><span class="badge"><?= htmlspecialchars($row['category']) ?></span></td>
                                    <td><?= $row['cnt'] ?></td>
                                    <td class="amount-col">&#8377;<?= number_format($row['total'], 2) ?></td>
                                    <td class="amount-col"><?= $share ?>%</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- INCOME DETAIL TABLE -->
        <div class="section-title">Income Details</div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Source / Account</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($inc_r) == 0): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:20px; color:#94a3b8;">No income found in this period.</td>
                        </tr>
                    <?php else:
                        $i = 1;
                        while ($row = mysqli_fetch_assoc($inc_r)): ?>
                            <tr>
                                <td style="color:#94a3b8;"><?= $i++ ?></td>
                                <td style="white-space:nowrap;"><?= date('d M Y', strtotime($row['income_date'])) ?></td>
                                <td><?= htmlspecialchars($row['description'] ?: '—') ?></td>
                                <td><span class="badge"><?= htmlspecialchars($row['category'] ?? '—') ?></span></td>
                                <td style="color:#64748b;"><?= htmlspecialchars($row['source_name'] ?? '—') ?></td>
                                <td class="amount-col" style="color:#059669;">&#8377;<?= number_format($row['amount'], 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <tr class="total-row">
                            <td colspan="5" style="text-align:right; padding-right:14px;">Grand Total</td>
                            <td class="amount-col">&#8377;<?= number_format($grand_total, 2) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- FOOTER -->
        <div class="report-footer">
            <p>KRNC Ledger &mdash; Income Management System</p>
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
