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
$fs = mysqli_real_escape_string($conn, $filter_start);
$fe = mysqli_real_escape_string($conn, $filter_end);

$sources_q = mysqli_query($conn, "SELECT * FROM expense_sources WHERE user_id=$user_id AND status=1 ORDER BY source_name");

// Fetch unique entered categories for the filter dropdown
$income_cats_q = mysqli_query($conn, "SELECT DISTINCT category FROM incomes WHERE user_id=$user_id AND status=1 ORDER BY category");
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

$incomes = mysqli_query($conn, "SELECT e.*, 
    s.source_name 
    FROM incomes e 
    LEFT JOIN expense_sources s ON e.source_id=s.id 
    WHERE e.user_id=$user_id AND e.status=1 AND e.income_date BETWEEN '$fs' AND '$fe' $cat_filter_sql 
    ORDER BY e.income_date DESC, e.id DESC");

$total_r = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM incomes e WHERE e.user_id=$user_id AND e.status=1 AND e.income_date BETWEEN '$fs' AND '$fe' $cat_filter_sql");
$total_filtered = (float) mysqli_fetch_assoc($total_r)['total'];
$total_count = mysqli_num_rows($incomes);

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

    <!-- TABLE -->
    <div class="card">
        <div class="card-header"><span>Income Records (<?= $total_count ?> records)</span></div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Source / Account</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total_count == 0): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <h3>No income entries found</h3>
                                    <p>Try adjusting filters or add a new income record.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        $i = 1;
                        while ($row = mysqli_fetch_assoc($incomes)): ?>
                            <tr>
                                <td class="text-muted"><?= $i++ ?></td>
                                <td><?= date('d M Y', strtotime($row['income_date'])) ?></td>
                                <td><?= htmlspecialchars($row['description'] ?: '—') ?></td>
                                <td>
                                    <span class="badge badge-success" style="background:rgba(16,185,129,0.1); color:#10b981; border:1px solid rgba(16,185,129,0.2);"><?= htmlspecialchars($row['category'] ?? '—') ?></span>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($row['source_name'] ?? '—') ?></td>
                                <td class="fw-bold" style="color:#10b981;">&#8377;<?= number_format($row['amount'], 2) ?></td>
                                <td>
                                    <form method="POST" action="<?= BASE_URL ?>/actions/income_action.php"
                                        style="display:inline;" onsubmit="return confirm('Cancel this income transaction?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="income_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Cancel</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
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
