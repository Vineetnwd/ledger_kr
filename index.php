<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}
$page_title = 'Dashboard — KRNC Expense Ledger';
$user_id = $_SESSION['user_id'];

// This month
$ms = date('Y-m-01');
$me = date('Y-m-t');

// Expense This Month
$r = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE status=1 AND expense_date BETWEEN '$ms' AND '$me'");
$total_month = (float) mysqli_fetch_assoc($r)['total'];

// Income This Month
$r_inc = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM incomes WHERE status=1 AND income_date BETWEEN '$ms' AND '$me'");
$total_income_month = (float) mysqli_fetch_assoc($r_inc)['total'];

// Last Month Expense
$lms = date('Y-m-01', strtotime('-1 month'));
$lme = date('Y-m-t', strtotime('-1 month'));
$r2 = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE status=1 AND expense_date BETWEEN '$lms' AND '$lme'");
$total_last = (float) mysqli_fetch_assoc($r2)['total'];

// Last Month Income
$r_inc_last = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM incomes WHERE status=1 AND income_date BETWEEN '$lms' AND '$lme'");
$total_income_last = (float) mysqli_fetch_assoc($r_inc_last)['total'];

// All Time
$r3 = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE status=1");
$total_all = (float) mysqli_fetch_assoc($r3)['total'];

$r_inc_all = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM incomes WHERE status=1");
$total_income_all = (float) mysqli_fetch_assoc($r_inc_all)['total'];

// Counts
$r4 = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM expenses WHERE status=1 AND expense_date BETWEEN '$ms' AND '$me'");
$total_txn = (int) mysqli_fetch_assoc($r4)['cnt'];

$r5 = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM categories WHERE status=1");
$total_cats = (int) mysqli_fetch_assoc($r5)['cnt'];

$r6 = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM expense_sources WHERE status=1");
$total_sources = (int) mysqli_fetch_assoc($r6)['cnt'];

$change = ($total_last > 0) ? round((($total_month - $total_last) / $total_last) * 100, 1) : 0;
$inc_change = ($total_income_last > 0) ? round((($total_income_month - $total_income_last) / $total_income_last) * 100, 1) : 0;

// Category doughnut
$cat_result = mysqli_query($conn, "SELECT c.name, COALESCE(SUM(e.amount),0) as total FROM categories c LEFT JOIN expenses e ON e.category_id=c.id AND e.status=1 WHERE c.status=1 GROUP BY c.id ORDER BY total DESC LIMIT 8");
$cat_labels = [];
$cat_data = [];
while ($row = mysqli_fetch_assoc($cat_result)) {
    $cat_labels[] = $row['name'];
    $cat_data[] = (float) $row['total'];
}

// Monthly trend (Incomes and Expenses)
$trend_labels = [];
$trend_data = [];
$trend_income_data = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = date('Y-m-01', strtotime("-$i months"));
    $te = date('Y-m-t', strtotime("-$i months"));
    $trend_labels[] = date('M Y', strtotime($ts));
    
    $r7 = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE status=1 AND expense_date BETWEEN '$ts' AND '$te'");
    $trend_data[] = (float) mysqli_fetch_assoc($r7)['total'];

    $r8 = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM incomes WHERE status=1 AND income_date BETWEEN '$ts' AND '$te'");
    $trend_income_data[] = (float) mysqli_fetch_assoc($r8)['total'];
}

// Recent 5 (Combined Expenses and Incomes)
$recent = mysqli_query($conn, "
    (SELECT 'expense' as txn_type, e.id, e.amount, e.description, e.expense_date as txn_date, 
            IF(p.name IS NOT NULL, CONCAT(p.name, ' › ', c.name), c.name) as cat_name, 
            s.source_name 
     FROM expenses e 
     LEFT JOIN categories c ON e.category_id=c.id 
     LEFT JOIN categories p ON c.parent_id=p.id
     LEFT JOIN expense_sources s ON e.source_id=s.id 
     WHERE e.status=1)
    UNION ALL
    (SELECT 'income' as txn_type, i.id, i.amount, i.description, i.income_date as txn_date, i.category as cat_name, s.source_name 
     FROM incomes i 
     LEFT JOIN expense_sources s ON i.source_id=s.id 
     WHERE i.status=1)
    ORDER BY txn_date DESC, id DESC LIMIT 5");

include 'includes/header.php';
?>

<div class="topbar">
    <div>
        <div class="topbar-title">Dashboard</div>
        <div class="topbar-sub"><?= date('l, F j, Y') ?></div>
    </div>
    <div style="display:flex; gap:8px;">
        <a href="<?= BASE_URL ?>/incomes.php" class="btn btn-primary" style="background:#10b981; border-color:#10b981;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Add Income
        </a>
        <a href="<?= BASE_URL ?>/expenses.php" class="btn btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Add Expense
        </a>
    </div>
</div>

<div class="page-content">

    <!-- STATS ROW -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
        <div class="stat-card" style="--accent:#6366f1;">
            <div class="stat-label">Expenses (This Month)</div>
            <div class="stat-value">&#8377;<?= number_format($total_month, 2) ?></div>
            <div class="stat-change <?= $change >= 0 ? 'text-danger' : 'text-success' ?>">
                <?= $change >= 0 ? '&#9650;' : '&#9660;' ?> <?= abs($change) ?>% vs last month
            </div>
        </div>
        <div class="stat-card" style="--accent:#10b981;">
            <div class="stat-label">Income (This Month)</div>
            <div class="stat-value" style="color:#10b981;">&#8377;<?= number_format($total_income_month, 2) ?></div>
            <div class="stat-change <?= $inc_change >= 0 ? 'text-success' : 'text-danger' ?>">
                <?= $inc_change >= 0 ? '&#9650;' : '&#9660;' ?> <?= abs($inc_change) ?>% vs last month
            </div>
        </div>
        <div class="stat-card" style="--accent:#22c55e;">
            <div class="stat-label">Net Balance (This Month)</div>
            <?php 
            $net_month = $total_income_month - $total_month;
            ?>
            <div class="stat-value" style="color: <?= $net_month >= 0 ? '#10b981' : '#ef4444' ?>;">
                &#8377;<?= number_format($net_month, 2) ?>
            </div>
            <div class="stat-change">Total cashflow overview</div>
        </div>
        <div class="stat-card" style="--accent:#3b82f6;">
            <div class="stat-label">Net Balance (All Time)</div>
            <?php 
            $net_all = $total_income_all - $total_all;
            ?>
            <div class="stat-value" style="color: <?= $net_all >= 0 ? '#10b981' : '#ef4444' ?>;">
                &#8377;<?= number_format($net_all, 2) ?>
            </div>
            <div class="stat-change"><?= $total_txn ?> total transactions</div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-title">Income vs Expenses Trend — Last 6 Months</div>
            <div class="chart-container"><canvas id="trendChart"></canvas></div>
        </div>
        <div class="chart-card">
            <div class="chart-title">Expenses by Category</div>
            <div class="chart-container"><canvas id="catChart"></canvas></div>
        </div>
    </div>

    <!-- RECENT TRANSACTIONS -->
    <div class="card">
        <div class="card-header">
            <span>Recent Transactions (Income &amp; Expenses)</span>
            <div style="display:flex; gap:8px;">
                <a href="<?= BASE_URL ?>/incomes.php" class="btn btn-sm btn-outline" style="color:#10b981; border-color:#10b981;">View Income</a>
                <a href="<?= BASE_URL ?>/expenses.php" class="btn btn-sm btn-outline">View Expenses</a>
            </div>
        </div>
        <div class="table-responsive no-scroll">
            <table class="table-compact">
                <thead>
                    <tr>
                        <th style="width: 85px; text-align: center;">Type</th>
                        <th class="col-date">Date</th>
                        <th class="col-desc">Description</th>
                        <th class="col-cat">Category</th>
                        <th class="col-source">Source</th>
                        <th class="col-amount" style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($recent) == 0): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <h3>No transactions yet</h3>
                                    <p>Add your first income or expense to get started.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        while ($row = mysqli_fetch_assoc($recent)): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <?php if ($row['txn_type'] == 'income'): ?>
                                        <span class="badge" style="background:rgba(16,185,129,0.1); color:#10b981; border:1px solid rgba(16,185,129,0.2);">Income</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">Expense</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-date text-muted font-mono"><?= date('d M Y', strtotime($row['txn_date'])) ?></td>
                                <td class="col-desc" data-tooltip="<?= htmlspecialchars($row['description'] ?: '') ?>"><?= htmlspecialchars($row['description'] ?: '—') ?></td>
                                <?php $cat_val = $row['cat_name'] ?: '—'; ?>
                                <td class="col-cat" data-tooltip="<?= htmlspecialchars($cat_val) ?>">
                                    <span class="badge badge-outline cat-badge" data-tooltip="<?= htmlspecialchars($cat_val) ?>"><?= htmlspecialchars($cat_val) ?></span>
                                </td>
                                <td class="col-source text-muted"><?= htmlspecialchars($row['source_name'] ?? '—') ?></td>
                                <td class="fw-bold col-amount font-mono" style="text-align: right; color: <?= $row['txn_type'] == 'income' ? '#10b981' : '#1e293b' ?>;">
                                    <?= $row['txn_type'] == 'income' ? '+' : '-' ?>&#8377;<?= number_format($row['amount'], 2) ?>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /page-content -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    (function () {
        var trendCtx = document.getElementById('trendChart');
        if (!trendCtx) return;
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($trend_labels) ?>,
                datasets: [
                    {
                        label: 'Income',
                        data: <?= json_encode($trend_income_data) ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.05)',
                        fill: true, tension: 0.4,
                        pointBackgroundColor: '#10b981',
                        pointRadius: 4, pointHoverRadius: 6
                    },
                    {
                        label: 'Expenses',
                        data: <?= json_encode($trend_data) ?>,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.05)',
                        fill: true, tension: 0.4,
                        pointBackgroundColor: '#6366f1',
                        pointRadius: 4, pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'top', labels: { color: '#64748b' } } },
                scales: {
                    x: { grid: { color: '#f1f5f9' }, ticks: { color: '#64748b', font: { size: 11 } } },
                    y: { grid: { color: '#f1f5f9' }, ticks: { color: '#64748b', font: { size: 11 }, callback: function (v) { return '\u20B9' + v; } } }
                }
            }
        });

        var catCtx = document.getElementById('catChart');
        if (!catCtx) return;
        var catLabels = <?= json_encode($cat_labels) ?>;
        var catData = <?= json_encode($cat_data) ?>;
        if (catLabels.length === 0) {
            catCtx.parentElement.innerHTML = '<div class="empty-state"><p>No category data yet.</p></div>';
            return;
        }
        var palette = ['#6366f1', '#10b981', '#f59e0b', '#3b82f6', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6'];
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: catLabels,
                datasets: [{ data: catData, backgroundColor: palette, borderWidth: 0, hoverOffset: 5 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { color: '#64748b', boxWidth: 11, padding: 10, font: { size: 11 } }
                    }
                }
            }
        });
    })();
</script>

<?php include 'includes/footer.php'; ?>