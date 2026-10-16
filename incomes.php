<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}
$page_title = 'Income — KRNC Expense Ledger';
$user_id = $_SESSION['user_id'];

$message = '';
if (isset($_SESSION['success'])) {
    $message = '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $message = '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}

$filter_start = $_GET['start'] ?? date('Y-m-01');
$filter_end = $_GET['end'] ?? date('Y-m-t');
$filter_cat = $_GET['cat'] ?? '';
$filter_src = (int) ($_GET['src'] ?? 0);
$fs = mysqli_real_escape_string($conn, $filter_start);
$fe = mysqli_real_escape_string($conn, $filter_end);

$sources_q = mysqli_query($conn, "SELECT * FROM expense_sources WHERE status=1 ORDER BY source_name");

// Fetch unique entered categories for the filter dropdown
$income_cats_q = mysqli_query($conn, "SELECT DISTINCT category FROM incomes WHERE status=1 ORDER BY category");
$income_categories = [];
while ($c_row = mysqli_fetch_assoc($income_cats_q)) {
    if (!empty($c_row['category'])) {
        $income_categories[] = $c_row['category'];
    }
}

$cat_filter_sql = '';
if (!empty($filter_cat)) {
    $esc_cat = mysqli_real_escape_string($conn, $filter_cat);
    $cat_filter_sql = "AND e.category = '$esc_cat'";
}

$src_filter_sql = $filter_src > 0 ? "AND e.source_id = $filter_src" : '';

// Overall period income total (for share calculation)
$overall_period_income_r = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM incomes WHERE status = 1 AND income_date BETWEEN '$fs' AND '$fe'");
$overall_period_total = (float) mysqli_fetch_assoc($overall_period_income_r)['total'];

// Source breakdown widget query for selected date range
$sources_widget_q = mysqli_query($conn, "
    SELECT s.id, s.source_name, s.bank_name, s.account_number,
           COALESCE(SUM(i.amount), 0) as total_amount,
           COUNT(i.id) as txn_count
    FROM expense_sources s
    LEFT JOIN incomes i ON i.source_id = s.id 
                       AND i.status = 1 
                       AND i.income_date BETWEEN '$fs' AND '$fe'
    WHERE s.status = 1
    GROUP BY s.id
    ORDER BY total_amount DESC, s.source_name ASC
");

$total_r = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total, COUNT(*) as cnt FROM incomes e WHERE e.status=1 AND e.income_date BETWEEN '$fs' AND '$fe' $cat_filter_sql $src_filter_sql");
$total_row = mysqli_fetch_assoc($total_r);
$total_filtered = (float) $total_row['total'];
$total_count = (int) $total_row['cnt'];

$p = get_pagination_params($total_count, 20);

$incomes = mysqli_query($conn, "SELECT e.*, 
    s.source_name,
    u.name as added_by 
    FROM incomes e 
    LEFT JOIN expense_sources s ON e.source_id=s.id 
    LEFT JOIN users u ON e.user_id=u.id 
    WHERE e.status=1 AND e.income_date BETWEEN '$fs' AND '$fe' $cat_filter_sql $src_filter_sql 
    ORDER BY e.income_date DESC, e.id DESC
    LIMIT {$p['offset']}, {$p['limit']}");

include 'includes/header.php';
?>

<div class="topbar">
    <div>
        <div class="topbar-title">Income</div>
        <div class="topbar-sub">Track and manage your incoming revenue &amp; deposits</div>
    </div>
    <button onclick="openModal('addIncomeModal')" class="btn btn-primary" style="background:#10b981; border-color:#10b981;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            stroke-linecap="round">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Add Income
    </button>
</div>

<div class="page-content">
    <?= $message ?>

    <!-- FILTER -->
    <form method="GET" class="card mb-20">
        <div class="filter-bar">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">From</label>
                <input type="date" name="start" class="form-control" value="<?= htmlspecialchars($filter_start) ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">To</label>
                <input type="date" name="end" class="form-control" value="<?= htmlspecialchars($filter_end) ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Category</label>
                <select name="cat" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($income_categories as $ic): ?>
                        <option value="<?= htmlspecialchars($ic) ?>" <?= $filter_cat === $ic ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ic) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Source / Account</label>
                <select name="src" class="form-control">
                    <option value="0">All Sources</option>
                    <?php mysqli_data_seek($sources_q, 0); while ($s = mysqli_fetch_assoc($sources_q)): ?>
                        <option value="<?= $s['id'] ?>" <?= $filter_src == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['source_name']) ?> <?= $s['bank_name'] ? '('.htmlspecialchars($s['bank_name']).')' : '' ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="<?= BASE_URL ?>/incomes.php" class="btn btn-outline" style="margin-left:6px;">Reset</a>
            </div>
            <div style="margin-left:auto;">
                <span class="text-muted fs-12">Total Income:</span>
                <strong
                    style="font-size:20px; margin-left:8px; color:#10b981;">&#8377;<?= number_format($total_filtered, 2) ?></strong>
            </div>
        </div>
    </form>

    <!-- SOURCES BREAKDOWN WIDGET -->
    <div style="margin-bottom: 20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 10px;">
            <div style="font-weight: 700; font-size: 13px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                Income by Source / Account (<?= date('d M Y', strtotime($filter_start)) ?> &ndash; <?= date('d M Y', strtotime($filter_end)) ?>)
            </div>
            <?php if ($filter_src > 0): ?>
                <a href="?start=<?= urlencode($filter_start) ?>&end=<?= urlencode($filter_end) ?>&cat=<?= urlencode($filter_cat) ?>&src=0" class="btn btn-sm btn-outline" style="font-size: 11px; padding: 2px 8px;">
                    &times; Clear Source Filter
                </a>
            <?php endif; ?>
        </div>
        <div class="source-widget-grid">
            <?php 
            mysqli_data_seek($sources_widget_q, 0);
            while ($s_card = mysqli_fetch_assoc($sources_widget_q)): 
                $is_active = $filter_src == $s_card['id'];
                $s_share = $overall_period_total > 0 ? round(($s_card['total_amount'] / $overall_period_total) * 100, 1) : 0;
                $toggle_src = $is_active ? 0 : $s_card['id'];
                $s_url = "?start=" . urlencode($filter_start) . "&end=" . urlencode($filter_end) . "&cat=" . urlencode($filter_cat) . "&src=" . $toggle_src;
                $acc_suffix = $s_card['account_number'] ? ' •••• ' . htmlspecialchars(substr($s_card['account_number'], -4)) : '';
            ?>
            <a href="<?= $s_url ?>" class="source-widget-card income-theme <?= $is_active ? 'active' : '' ?>" title="Click to filter by <?= htmlspecialchars($s_card['source_name']) ?>">
                <div>
                    <div class="source-widget-header">
                        <div class="source-widget-title"><?= htmlspecialchars($s_card['source_name']) ?></div>
                        <?php if ($is_active): ?>
                            <span class="badge badge-success" style="font-size: 10px; padding: 1px 6px;">Active</span>
                        <?php endif; ?>
                    </div>
                    <div class="source-widget-subtitle"><?= htmlspecialchars($s_card['bank_name'] ?: 'Account') ?><?= $acc_suffix ?></div>
                    <div class="source-widget-amount" style="color: #10b981;">&#8377;<?= number_format($s_card['total_amount'], 2) ?></div>
                </div>
                <div class="source-widget-footer">
                    <span><?= $s_card['txn_count'] ?> transaction<?= $s_card['txn_count'] != 1 ? 's' : '' ?></span>
                    <span style="font-weight: 600; color: #10b981;"><?= $s_share ?>%</span>
                </div>
            </a>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- TABLE -->
    <div class="card">
        <div class="card-header"><span>Income Records (<?= $total_count ?> records)</span></div>
        <div class="table-responsive no-scroll">
            <table class="table-compact">
                <thead>
                    <tr>
                        <th class="col-num">#</th>
                        <th class="col-date">Date</th>
                        <th class="col-desc">Description</th>
                        <th class="col-cat">Category</th>
                        <th class="col-source">Source / Account</th>
                        <th class="col-amount">Amount</th>
                        <th class="col-user">Added By</th>
                        <th class="col-action">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total_count == 0): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <h3>No income entries found</h3>
                                    <p>Try adjusting filters or add a new income record.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        $i = $p['start_record'];
                        while ($row = mysqli_fetch_assoc($incomes)): ?>
                            <tr>
                                <td class="col-num"><?= $i++ ?></td>
                                <td class="col-date"><?= date('d M Y', strtotime($row['income_date'])) ?></td>
                                <td class="col-desc" data-tooltip="<?= htmlspecialchars($row['description'] ?: '') ?>"><?= htmlspecialchars($row['description'] ?: '—') ?></td>
                                <?php $cat_val = $row['category'] ?: '—'; ?>
                                <td class="col-cat" data-tooltip="<?= htmlspecialchars($cat_val) ?>">
                                    <span class="badge badge-success cat-badge" style="background:rgba(16,185,129,0.1); color:#10b981; border:1px solid rgba(16,185,129,0.2);" data-tooltip="<?= htmlspecialchars($cat_val) ?>" title="<?= htmlspecialchars($cat_val) ?>"><?= htmlspecialchars($cat_val) ?></span>
                                </td>
                                <td class="col-source text-muted"><?= htmlspecialchars($row['source_name'] ?? '—') ?></td>
                                <td class="col-amount" style="color:#10b981;">&#8377;<?= number_format($row['amount'], 2) ?></td>
                                <td class="col-user"><span class="badge badge-outline fs-11"><?= htmlspecialchars($row['added_by'] ?? '—') ?></span></td>
                                <td class="col-action">
                                    <form method="POST" action="<?= BASE_URL ?>/actions/income_action.php"
                                        style="display:inline;" onsubmit="return confirm('Cancel this income transaction?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="income_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" style="padding:3px 8px;font-size:11px;">Cancel</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination_bar($p) ?>
    </div>
</div>

<!-- ADD INCOME MODAL -->
<div id="addIncomeModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <span>Add New Income</span>
            <button class="modal-close" onclick="closeModal('addIncomeModal')">&#215;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/actions/income_action.php">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <?php
                $no_sources = (mysqli_num_rows($sources_q) == 0);
                if ($no_sources):
                    ?>
                    <div class="alert alert-info">
                        Please <a href="<?= BASE_URL ?>/sources.php"
                                style="color:inherit;font-weight:700;">add a source/bank</a> first.
                    </div>
                <?php endif; ?>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input type="date" name="income_date" class="form-control" value="<?= date('Y-m-d') ?>"
                            required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount (&#8377;) *</label>
                        <input type="number" name="amount" step="0.01" min="0.01" class="form-control"
                            placeholder="0.00" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">Category *</label>
                        <input type="text" name="category" class="form-control" placeholder="e.g. Salary, Business, Gift, Freelance" required>
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">Deposit Source / Bank *</label>
                        <select name="source_id" class="form-control" required>
                            <option value="">Select Account / Source</option>
                            <?php mysqli_data_seek($sources_q, 0);
                            while ($s = mysqli_fetch_assoc($sources_q)): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['source_name']) ?>
                                    <?= $s['bank_name'] ? '(' . $s['bank_name'] . ')' : '' ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" placeholder="What was this income from?">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addIncomeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background:#10b981; border-color:#10b981;">Save Income</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
