<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}
$page_title = 'Reports — KRNC Expense Ledger';
$user_id = $_SESSION['user_id'];

// Filter parameters
$filter_type = $_GET['type'] ?? 'all';
if (!in_array($filter_type, ['all', 'expense', 'income'])) {
    $filter_type = 'all';
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$fs = mysqli_real_escape_string($conn, $start_date);
$fe = mysqli_real_escape_string($conn, $end_date);

$raw_cat = $_GET['cat'] ?? '';
$filter_src = isset($_GET['src']) ? (int)$_GET['src'] : 0;

// Parse category selection
$exp_cat_id = 0;
$inc_cat_name = '';

if (!empty($raw_cat)) {
    if (strpos($raw_cat, 'exp_') === 0) {
        $exp_cat_id = (int) substr($raw_cat, 4);
    } elseif (strpos($raw_cat, 'inc_') === 0) {
        $inc_cat_name = substr($raw_cat, 4);
    } elseif (is_numeric($raw_cat) && (int)$raw_cat > 0) {
        $exp_cat_id = (int) $raw_cat;
    }
}

// Build WHERE SQL for expenses
$exp_extra_sql = "";
if ($exp_cat_id > 0) {
    $is_parent_q = mysqli_query($conn, "SELECT id FROM categories WHERE parent_id=$exp_cat_id");
    if (mysqli_num_rows($is_parent_q) > 0) {
        $sub_ids = [];
        while ($sub = mysqli_fetch_assoc($is_parent_q)) $sub_ids[] = $sub['id'];
        $sub_ids_str = implode(',', $sub_ids);
        $exp_extra_sql .= " AND e.category_id IN ($exp_cat_id, $sub_ids_str)";
    } else {
        $exp_extra_sql .= " AND e.category_id = $exp_cat_id";
    }
} elseif (!empty($inc_cat_name)) {
    $exp_extra_sql .= " AND 1=0";
}
if ($filter_src > 0) {
    $exp_extra_sql .= " AND e.source_id = $filter_src";
}

// Build WHERE SQL for incomes
$inc_extra_sql = "";
if (!empty($inc_cat_name)) {
    $esc_inc_cat = mysqli_real_escape_string($conn, $inc_cat_name);
    $inc_extra_sql .= " AND i.category = '$esc_inc_cat'";
} elseif ($exp_cat_id > 0) {
    $inc_extra_sql .= " AND 1=0";
}
if ($filter_src > 0) {
    $inc_extra_sql .= " AND i.source_id = $filter_src";
}

// Fetch lists for filter dropdowns
$parent_cats = [];
$sub_cats = [];
$cats_q = mysqli_query($conn, "SELECT * FROM categories WHERE status=1 ORDER BY name ASC");
while ($c = mysqli_fetch_assoc($cats_q)) {
    if (empty($c['parent_id'])) {
        $parent_cats[$c['id']] = $c;
    } else {
        $sub_cats[$c['parent_id']][] = $c;
    }
}

$income_cats_q = mysqli_query($conn, "SELECT DISTINCT category FROM incomes WHERE status=1 ORDER BY category ASC");
$income_categories = [];
while ($ic_row = mysqli_fetch_assoc($income_cats_q)) {
    if (!empty($ic_row['category'])) {
        $income_categories[] = $ic_row['category'];
    }
}

$sources_q = mysqli_query($conn, "SELECT * FROM expense_sources WHERE status=1 ORDER BY source_name ASC");

// Summary calculations
$exp_sum_r = mysqli_query($conn, "SELECT COALESCE(SUM(e.amount),0) as total, COUNT(*) as cnt FROM expenses e WHERE e.status=1 AND e.expense_date BETWEEN '$fs' AND '$fe' $exp_extra_sql");
$exp_summary = mysqli_fetch_assoc($exp_sum_r);
$total_expense = (float) $exp_summary['total'];
$exp_cnt = (int) $exp_summary['cnt'];

$inc_sum_r = mysqli_query($conn, "SELECT COALESCE(SUM(i.amount),0) as total, COUNT(*) as cnt FROM incomes i WHERE i.status=1 AND i.income_date BETWEEN '$fs' AND '$fe' $inc_extra_sql");
$inc_summary = mysqli_fetch_assoc($inc_sum_r);
$total_income = (float) $inc_summary['total'];
$inc_cnt = (int) $inc_summary['cnt'];

if ($filter_type === 'expense') {
    $grand_total = $total_expense;
    $total_cnt = $exp_cnt;
    $avg = $total_cnt > 0 ? $grand_total / $total_cnt : 0;
} elseif ($filter_type === 'income') {
    $grand_total = $total_income;
    $total_cnt = $inc_cnt;
    $avg = $total_cnt > 0 ? $grand_total / $total_cnt : 0;
} else { // 'all'
    $grand_total = $total_income - $total_expense; // Net balance
    $total_cnt = $exp_cnt + $inc_cnt;
    $avg = $total_cnt > 0 ? ($total_income + $total_expense) / $total_cnt : 0;
}

// Category Breakdown query based on filter_type
if ($filter_type === 'expense') {
    $cat_count_res = mysqli_query($conn, "SELECT COUNT(DISTINCT c.id) as cnt FROM categories c JOIN expenses e ON e.category_id=c.id WHERE e.status=1 AND e.expense_date BETWEEN '$fs' AND '$fe' $exp_extra_sql");
    $cat_total_records = (int) ($cat_count_res ? mysqli_fetch_assoc($cat_count_res)['cnt'] : 0);
    $p_cat = get_pagination_params($cat_total_records, 10, 'cp', 'climit');
    $cat_breakdown = mysqli_query($conn, "SELECT c.name, p.name as parent_name, 'expense' as txn_type, COALESCE(SUM(e.amount),0) as total, COUNT(e.id) as cnt FROM categories c LEFT JOIN categories p ON c.parent_id = p.id JOIN expenses e ON e.category_id=c.id WHERE e.status=1 AND e.expense_date BETWEEN '$fs' AND '$fe' $exp_extra_sql GROUP BY c.id ORDER BY total DESC LIMIT {$p_cat['offset']}, {$p_cat['limit']}");
} elseif ($filter_type === 'income') {
    $cat_count_res = mysqli_query($conn, "SELECT COUNT(DISTINCT i.category) as cnt FROM incomes i WHERE i.status=1 AND i.income_date BETWEEN '$fs' AND '$fe' $inc_extra_sql");
    $cat_total_records = (int) ($cat_count_res ? mysqli_fetch_assoc($cat_count_res)['cnt'] : 0);
    $p_cat = get_pagination_params($cat_total_records, 10, 'cp', 'climit');
    $cat_breakdown = mysqli_query($conn, "SELECT i.category as name, NULL as parent_name, 'income' as txn_type, COALESCE(SUM(i.amount),0) as total, COUNT(i.id) as cnt FROM incomes i WHERE i.status=1 AND i.income_date BETWEEN '$fs' AND '$fe' $inc_extra_sql GROUP BY i.category ORDER BY total DESC LIMIT {$p_cat['offset']}, {$p_cat['limit']}");
} else {
    $cat_breakdown_sql = "
        SELECT name, parent_name, txn_type, SUM(amount) as total, COUNT(*) as cnt FROM (
            SELECT CONVERT(c.name USING utf8mb4) COLLATE utf8mb4_unicode_ci as name, 
                   CONVERT(p.name USING utf8mb4) COLLATE utf8mb4_unicode_ci as parent_name, 
                   'expense' as txn_type, e.amount 
            FROM categories c 
            LEFT JOIN categories p ON c.parent_id = p.id 
            JOIN expenses e ON e.category_id=c.id 
            WHERE e.status=1 AND e.expense_date BETWEEN '$fs' AND '$fe' $exp_extra_sql
            UNION ALL
            SELECT CONVERT(i.category USING utf8mb4) COLLATE utf8mb4_unicode_ci as name, 
                   CAST(NULL AS CHAR) as parent_name, 
                   'income' as txn_type, i.amount 
            FROM incomes i 
            WHERE i.status=1 AND i.income_date BETWEEN '$fs' AND '$fe' $inc_extra_sql
        ) combined_cats
        GROUP BY txn_type, name, parent_name
    ";
    $cat_count_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ($cat_breakdown_sql) as t");
    $cat_total_records = (int) ($cat_count_res ? mysqli_fetch_assoc($cat_count_res)['cnt'] : 0);
    $p_cat = get_pagination_params($cat_total_records, 10, 'cp', 'climit');
    $cat_breakdown = mysqli_query($conn, "$cat_breakdown_sql ORDER BY total DESC LIMIT {$p_cat['offset']}, {$p_cat['limit']}");
}

// Source breakdown query based on filter_type
if ($filter_type === 'expense') {
    $src_breakdown = mysqli_query($conn, "SELECT s.source_name, s.bank_name, COALESCE(SUM(e.amount),0) as total, COUNT(e.id) as cnt FROM expense_sources s JOIN expenses e ON e.source_id=s.id WHERE e.status=1 AND e.expense_date BETWEEN '$fs' AND '$fe' $exp_extra_sql GROUP BY s.id ORDER BY total DESC");
} elseif ($filter_type === 'income') {
    $src_breakdown = mysqli_query($conn, "SELECT s.source_name, s.bank_name, COALESCE(SUM(i.amount),0) as total, COUNT(i.id) as cnt FROM expense_sources s JOIN incomes i ON i.source_id=s.id WHERE i.status=1 AND i.income_date BETWEEN '$fs' AND '$fe' $inc_extra_sql GROUP BY s.id ORDER BY total DESC");
} else {
    $src_breakdown = mysqli_query($conn, "
        SELECT s.id, s.source_name, s.bank_name,
               COALESCE(SUM(CASE WHEN t.txn_type='income' THEN t.amount ELSE 0 END), 0) as inc_total,
               COALESCE(SUM(CASE WHEN t.txn_type='expense' THEN t.amount ELSE 0 END), 0) as exp_total,
               COUNT(t.id) as cnt
        FROM expense_sources s
        JOIN (
            SELECT 'expense' as txn_type, id, source_id, amount FROM expenses WHERE status=1 AND expense_date BETWEEN '$fs' AND '$fe' $exp_extra_sql
            UNION ALL
            SELECT 'income' as txn_type, id, source_id, amount FROM incomes WHERE status=1 AND income_date BETWEEN '$fs' AND '$fe' $inc_extra_sql
        ) t ON t.source_id = s.id
        WHERE s.status=1
        GROUP BY s.id, s.source_name, s.bank_name
        ORDER BY inc_total DESC, exp_total DESC
    ");
}

// Transactions list pagination & query
$p = get_pagination_params($total_cnt, 20);

if ($filter_type === 'expense') {
    $transactions = mysqli_query($conn, "
        SELECT 'expense' as txn_type, e.id, e.amount, e.description, e.expense_date as txn_date, 
               c.name as cat_name, p.name as parent_cat_name, 
               s.source_name, u.name as added_by 
        FROM expenses e 
        LEFT JOIN categories c ON e.category_id=c.id 
        LEFT JOIN categories p ON c.parent_id = p.id 
        LEFT JOIN expense_sources s ON e.source_id=s.id 
        LEFT JOIN users u ON e.user_id=u.id 
        WHERE e.status=1 AND e.expense_date BETWEEN '$fs' AND '$fe' $exp_extra_sql 
        ORDER BY e.expense_date DESC, e.id DESC 
        LIMIT {$p['offset']}, {$p['limit']}
    ");
} elseif ($filter_type === 'income') {
    $transactions = mysqli_query($conn, "
        SELECT 'income' as txn_type, i.id, i.amount, i.description, i.income_date as txn_date, 
               i.category as cat_name, NULL as parent_cat_name, 
               s.source_name, u.name as added_by 
        FROM incomes i 
        LEFT JOIN expense_sources s ON i.source_id=s.id 
        LEFT JOIN users u ON i.user_id=u.id 
        WHERE i.status=1 AND i.income_date BETWEEN '$fs' AND '$fe' $inc_extra_sql 
        ORDER BY i.income_date DESC, i.id DESC 
        LIMIT {$p['offset']}, {$p['limit']}
    ");
} else {
    $transactions = mysqli_query($conn, "
        SELECT * FROM (
            SELECT 'expense' as txn_type, e.id, e.amount, e.description, e.expense_date as txn_date, 
                   CONVERT(c.name USING utf8mb4) COLLATE utf8mb4_unicode_ci as cat_name, 
                   CONVERT(p.name USING utf8mb4) COLLATE utf8mb4_unicode_ci as parent_cat_name, 
                   s.source_name, u.name as added_by 
            FROM expenses e 
            LEFT JOIN categories c ON e.category_id=c.id 
            LEFT JOIN categories p ON c.parent_id = p.id 
            LEFT JOIN expense_sources s ON e.source_id=s.id 
            LEFT JOIN users u ON e.user_id=u.id 
            WHERE e.status=1 AND e.expense_date BETWEEN '$fs' AND '$fe' $exp_extra_sql

            UNION ALL

            SELECT 'income' as txn_type, i.id, i.amount, i.description, i.income_date as txn_date, 
                   CONVERT(i.category USING utf8mb4) COLLATE utf8mb4_unicode_ci as cat_name, 
                   CAST(NULL AS CHAR) as parent_cat_name, 
                   s.source_name, u.name as added_by 
            FROM incomes i 
            LEFT JOIN expense_sources s ON i.source_id=s.id 
            LEFT JOIN users u ON i.user_id=u.id 
            WHERE i.status=1 AND i.income_date BETWEEN '$fs' AND '$fe' $inc_extra_sql
        ) combined
        ORDER BY txn_date DESC, id DESC 
        LIMIT {$p['offset']}, {$p['limit']}
    ");
}

include 'includes/header.php';
?>

<div class="topbar">
    <div>
        <div class="topbar-title">Reports</div>
        <div class="topbar-sub">Generate, analyze, and export comprehensive financial ledger reports</div>
    </div>
</div>

<div class="page-content">

    <!-- EXPORT & PRINT CENTER -->
    <div class="card mb-20" style="padding: 18px 22px;">
        <div style="font-weight:700; font-size:14px; color:#1e293b; margin-bottom:14px; display:flex; align-items:center; gap:8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:-1px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Export &amp; Print Statement Center
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; width: 100%;">
            <!-- Ledger Statement Group -->
            <div style="display:flex; flex-direction:column; gap:8px; background:#fcfaff; border:1px solid #ebe2fe; padding:12px; border-radius:10px;">
                <div style="font-size:10px; font-weight:700; color:#8b5cf6; text-transform:uppercase; letter-spacing:0.5px;">1. Unified Statement</div>
                <a href="<?= BASE_URL ?>/actions/export_statement.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"
                    class="btn" style="background:#8b5cf6; border-color:#8b5cf6; color:#fff; text-align:center; justify-content:center; gap:6px;" target="_blank">
                    &#128438; Print Ledger Statement
                </a>
                <a href="<?= BASE_URL ?>/actions/export_statement_excel.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"
                    class="btn" style="background:#7c3aed; border-color:#7c3aed; color:#fff; text-align:center; justify-content:center; gap:6px;">
                    &#8595; Ledger Excel
                </a>
            </div>
            
            <!-- Expenses Group -->
            <div style="display:flex; flex-direction:column; gap:8px; background:#fffbfb; border:1px solid #fee2e2; padding:12px; border-radius:10px;">
                <div style="font-size:10px; font-weight:700; color:#ef4444; text-transform:uppercase; letter-spacing:0.5px;">2. Expense Reports</div>
                <a href="<?= BASE_URL ?>/actions/export_excel.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&cat=<?= $exp_cat_id ?>&src=<?= $filter_src ?>"
                    class="btn" style="background:#ef4444; border-color:#ef4444; color:#fff; text-align:center; justify-content:center; gap:6px;">
                    &#8595; Expense Excel
                </a>
                <a href="<?= BASE_URL ?>/actions/export_pdf.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&cat=<?= $exp_cat_id ?>&src=<?= $filter_src ?>"
                    class="btn" style="background:#dc2626; border-color:#dc2626; color:#fff; text-align:center; justify-content:center; gap:6px;" target="_blank">
                    &#8595; Expense PDF
                </a>
            </div>

            <!-- Incomes Group -->
            <div style="display:flex; flex-direction:column; gap:8px; background:#f6fcf9; border:1px solid #d1fae5; padding:12px; border-radius:10px;">
                <div style="font-size:10px; font-weight:700; color:#059669; text-transform:uppercase; letter-spacing:0.5px;">3. Income Reports</div>
                <a href="<?= BASE_URL ?>/actions/export_income_excel.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&src=<?= $filter_src ?>"
                    class="btn" style="background:#10b981; border-color:#10b981; color:#fff; text-align:center; justify-content:center; gap:6px;">
                    &#8595; Income Excel
                </a>
                <a href="<?= BASE_URL ?>/actions/export_income_pdf.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&src=<?= $filter_src ?>"
                    class="btn" style="background:#059669; border-color:#059669; color:#fff; text-align:center; justify-content:center; gap:6px;" target="_blank">
                    &#8595; Income PDF
                </a>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <form method="GET" class="card mb-20" id="reportFilterForm">
        <div class="filter-bar">
            <!-- Ask Report Type: All, Expense, or Income -->
            <div class="form-group" style="margin-bottom:0; min-width:180px;">
                <label class="form-label" style="font-weight:700; color:#4f46e5;">Report Type</label>
                <select name="type" id="report_type_select" class="form-control" style="font-weight:600;">
                    <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All (Income &amp; Expenses)</option>
                    <option value="expense" <?= $filter_type === 'expense' ? 'selected' : '' ?>>Expenses Only</option>
                    <option value="income" <?= $filter_type === 'income' ? 'selected' : '' ?>>Income Only</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="form-group" style="margin-bottom:0; min-width:180px;">
                <label class="form-label">Category</label>
                <select name="cat" id="cat_filter_select" class="form-control">
                    <option value="">All Categories</option>
                    <?php if ($filter_type !== 'income'): ?>
                        <optgroup label="Expense Categories">
                            <?php foreach ($parent_cats as $pid => $pc): ?>
                                <option value="exp_<?= $pid ?>" <?= ($raw_cat === "exp_$pid" || $raw_cat === (string)$pid) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pc['name']) ?>
                                </option>
                                <?php if (!empty($sub_cats[$pid])):
                                    foreach ($sub_cats[$pid] as $sc): ?>
                                        <option value="exp_<?= $sc['id'] ?>" <?= ($raw_cat === "exp_{$sc['id']}" || $raw_cat === (string)$sc['id']) ? 'selected' : '' ?>>
                                            &nbsp;&nbsp;&nbsp;&#9492; <?= htmlspecialchars($sc['name']) ?>
                                        </option>
                                    <?php endforeach; endif; ?>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                    <?php if ($filter_type !== 'expense'): ?>
                        <optgroup label="Income Categories">
                            <?php foreach ($income_categories as $ic): ?>
                                <option value="inc_<?= htmlspecialchars($ic) ?>" <?= $raw_cat === "inc_$ic" ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ic) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0; min-width:160px;">
                <label class="form-label">Source / Bank</label>
                <select name="src" class="form-control">
                    <option value="0">All Sources</option>
                    <?php mysqli_data_seek($sources_q, 0); while($s = mysqli_fetch_assoc($sources_q)): ?>
                        <option value="<?= $s['id'] ?>" <?= $filter_src == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['source_name']) ?> <?= $s['bank_name'] ? '('.htmlspecialchars($s['bank_name']).')' : '' ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div style="display:flex; gap:6px; align-items:flex-end;">
                <button type="submit" id="btnGenerateReport" class="btn btn-primary" style="font-weight:700;">Generate Report</button>
                <a href="<?= BASE_URL ?>/reports.php" class="btn btn-outline">Reset</a>
            </div>
        </div>
    </form>

    <!-- SUMMARY CARDS -->
    <?php if ($filter_type === 'all'): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;margin-bottom:20px;">
            <!-- Income Card -->
            <div class="card" style="border-top:3px solid #10b981;">
                <div class="card-body" style="text-align:center;padding:18px;">
                    <div class="text-muted fs-11" style="text-transform:uppercase;letter-spacing:0.5px;font-weight:700;color:#059669;">
                        Total Income (+)</div>
                    <div style="font-size:26px;font-weight:800;margin:8px 0;color:#059669;">
                        +&#8377;<?= number_format($total_income, 2) ?>
                    </div>
                    <div class="text-muted fs-12"><?= $inc_cnt ?> income transaction<?= $inc_cnt == 1 ? '' : 's' ?></div>
                </div>
            </div>
            <!-- Expense Card -->
            <div class="card" style="border-top:3px solid #ef4444;">
                <div class="card-body" style="text-align:center;padding:18px;">
                    <div class="text-muted fs-11" style="text-transform:uppercase;letter-spacing:0.5px;font-weight:700;color:#dc2626;">
                        Total Expenses (-)</div>
                    <div style="font-size:26px;font-weight:800;margin:8px 0;color:#dc2626;">
                        -&#8377;<?= number_format($total_expense, 2) ?>
                    </div>
                    <div class="text-muted fs-12"><?= $exp_cnt ?> expense transaction<?= $exp_cnt == 1 ? '' : 's' ?></div>
                </div>
            </div>
            <!-- Net Balance Card -->
            <div class="card" style="border-top:3px solid <?= $grand_total >= 0 ? '#10b981' : '#ef4444' ?>;">
                <div class="card-body" style="text-align:center;padding:18px;">
                    <div class="text-muted fs-11" style="text-transform:uppercase;letter-spacing:0.5px;font-weight:700;color:#475569;">
                        Net Balance</div>
                    <div style="font-size:26px;font-weight:800;margin:8px 0;color:<?= $grand_total >= 0 ? '#059669' : '#dc2626' ?>;">
                        <?= $grand_total >= 0 ? '+' : '-' ?>&#8377;<?= number_format(abs($grand_total), 2) ?>
                    </div>
                    <div class="text-muted fs-12"><?= $grand_total >= 0 ? 'Surplus (+)' : 'Deficit (-)' ?> in period</div>
                </div>
            </div>
            <!-- Transactions Count Card -->
            <div class="card" style="border-top:3px solid #6366f1;">
                <div class="card-body" style="text-align:center;padding:18px;">
                    <div class="text-muted fs-11" style="text-transform:uppercase;letter-spacing:0.5px;font-weight:700;color:#4f46e5;">
                        Transactions</div>
                    <div style="font-size:26px;font-weight:800;margin:8px 0;color:#1e293b;"><?= $total_cnt ?></div>
                    <div class="text-muted fs-12"><?= date('d M Y', strtotime($start_date)) ?> &mdash; <?= date('d M Y', strtotime($end_date)) ?></div>
                </div>
            </div>
        </div>
    <?php elseif ($filter_type === 'expense'): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:14px;margin-bottom:20px;">
            <div class="card" style="border-top:3px solid #ef4444;">
                <div class="card-body" style="text-align:center;padding:20px;">
                    <div class="text-muted fs-11" style="text-transform:uppercase;letter-spacing:0.5px;font-weight:700;color:#dc2626;">
                        Total Expenses</div>
                    <div style="font-size:28px;font-weight:800;margin:8px 0;color:#dc2626;">&#8377;<?= number_format($grand_total, 2) ?></div>
                    <div class="text-muted fs-12"><?= date('d M Y', strtotime($start_date)) ?> &mdash; <?= date('d M Y', strtotime($end_date)) ?></div>
                </div>
            </div>
            <div class="card" style="border-top:3px solid #10b981;">
                <div class="card-body" style="text-align:center;padding:20px;">
                    <div class="text-muted fs-11" style="text-transform:uppercase;letter-spacing:0.5px;font-weight:700;">
                        Transactions</div>
                    <div style="font-size:28px;font-weight:800;margin:8px 0;"><?= $total_cnt ?></div>
                    <div class="text-muted fs-12">Total expense records</div>
                </div>
            </div>
            <div class="card" style="border-top:3px solid #f59e0b;">
                <div class="card-body" style="text-align:center;padding:20px;">
                    <div class="text-muted fs-11" style="text-transform:uppercase;letter-spacing:0.5px;font-weight:700;">Avg Per Transaction</div>
                    <div style="font-size:28px;font-weight:800;margin:8px 0;">&#8377;<?= number_format($avg, 2) ?></div>
                    <div class="text-muted fs-12">In selected period</div>
                </div>
            </div>
        </div>
    <?php else: // income ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:14px;margin-bottom:20px;">
            <div class="card" style="border-top:3px solid #10b981;">
                <div class="card-body" style="text-align:center;padding:20px;">
                    <div class="text-muted fs-11" style="text-transform:uppercase;letter-spacing:0.5px;font-weight:700;color:#059669;">
                        Total Income</div>
                    <div style="font-size:28px;font-weight:800;margin:8px 0;color:#059669;">+&#8377;<?= number_format($grand_total, 2) ?></div>
                    <div class="text-muted fs-12"><?= date('d M Y', strtotime($start_date)) ?> &mdash; <?= date('d M Y', strtotime($end_date)) ?></div>
                </div>
            </div>
            <div class="card" style="border-top:3px solid #6366f1;">
                <div class="card-body" style="text-align:center;padding:20px;">
                    <div class="text-muted fs-11" style="text-transform:uppercase;letter-spacing:0.5px;font-weight:700;">
                        Transactions</div>
                    <div style="font-size:28px;font-weight:800;margin:8px 0;"><?= $total_cnt ?></div>
                    <div class="text-muted fs-12">Total income records</div>
                </div>
            </div>
            <div class="card" style="border-top:3px solid #f59e0b;">
                <div class="card-body" style="text-align:center;padding:20px;">
                    <div class="text-muted fs-11" style="text-transform:uppercase;letter-spacing:0.5px;font-weight:700;">Avg Per Transaction</div>
                    <div style="font-size:28px;font-weight:800;margin:8px 0;">&#8377;<?= number_format($avg, 2) ?></div>
                    <div class="text-muted fs-12">In selected period</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- BREAKDOWNS -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:14px;margin-bottom:20px;">
        <!-- By Category -->
        <div class="card">
            <div class="card-header">
                <span>By Category (<?= $cat_total_records ?>)</span>
            </div>
            <div class="table-responsive no-scroll">
                <table class="table-compact">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th class="col-cat">Category</th>
                            <th style="width:65px;text-align:center;">Count</th>
                            <th class="col-amount" style="text-align:right;">Total</th>
                            <th style="width:75px;text-align:right;">Share %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$cat_breakdown || mysqli_num_rows($cat_breakdown) == 0): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;color:#94a3b8;padding:20px;">No category records for this period.</td>
                            </tr>
                        <?php else:
                            $ci = $p_cat['start_record'];
                            while ($row = mysqli_fetch_assoc($cat_breakdown)):
                                $row_type = $row['txn_type'] ?? 'expense';
                                $base_total = ($row_type === 'income') ? $total_income : $total_expense;
                                if ($filter_type !== 'all') {
                                    $base_total = $grand_total;
                                }
                                $share = $base_total > 0 ? round(((float) $row['total'] / $base_total) * 100, 1) : 0;
                                $is_sub = !empty($row['parent_name']);
                                $full_cat = $is_sub ? ($row['parent_name'] . ' › ' . $row['name']) : $row['name'];
                                ?>
                                <tr>
                                    <td class="col-num"><?= $ci++ ?></td>
                                    <td class="col-cat" data-tooltip="<?= htmlspecialchars($full_cat) ?>">
                                        <?php if ($filter_type === 'all'): ?>
                                            <span class="badge <?= $row_type === 'income' ? 'badge-success' : 'badge-info' ?> cat-badge" data-tooltip="<?= htmlspecialchars($full_cat) ?>" title="<?= htmlspecialchars($full_cat) ?>">
                                                <small style="font-weight:700;"><?= $row_type === 'income' ? '[INC]' : '[EXP]' ?></small> <?= htmlspecialchars($full_cat) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge <?= $filter_type === 'income' ? 'badge-success' : 'badge-info' ?> cat-badge" data-tooltip="<?= htmlspecialchars($full_cat) ?>" title="<?= htmlspecialchars($full_cat) ?>">
                                                <?= htmlspecialchars($full_cat) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted font-mono" style="text-align:center;"><?= $row['cnt'] ?></td>
                                    <td class="fw-bold col-amount font-mono" style="text-align:right; color:<?= $row_type === 'income' ? '#059669' : '#1e293b' ?>;">
                                        <?= $row_type === 'income' ? '+' : '' ?>&#8377;<?= number_format($row['total'], 2) ?>
                                    </td>
                                    <td class="text-muted font-mono" style="text-align:right; font-weight:600;"><?= $share ?>%</td>
                                </tr>
                            <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
            <?= render_pagination_bar($p_cat, 'cp', 'climit') ?>
        </div>

        <!-- By Source / Bank -->
        <div class="card">
            <div class="card-header">
                <span>By Source / Bank (<?= $src_breakdown ? mysqli_num_rows($src_breakdown) : 0 ?>)</span>
            </div>
            <div class="table-responsive no-scroll">
                <table class="table-compact">
                    <thead>
                        <?php if ($filter_type === 'all'): ?>
                            <tr>
                                <th class="col-num">#</th>
                                <th>Source</th>
                                <th style="text-align:right;">Income (+)</th>
                                <th style="text-align:right;">Expense (-)</th>
                                <th class="col-amount" style="text-align:right;">Net Flow</th>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <th class="col-num">#</th>
                                <th>Source</th>
                                <th>Bank</th>
                                <th style="width:65px;text-align:center;">Count</th>
                                <th class="col-amount" style="text-align:right;">Total</th>
                            </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                        <?php if (!$src_breakdown || mysqli_num_rows($src_breakdown) == 0): ?>
                            <tr>
                                <td colspan="<?= $filter_type === 'all' ? 5 : 5 ?>" style="text-align:center;color:#94a3b8;padding:20px;">No source records for this period.</td>
                            </tr>
                        <?php else:
                            $si = 1;
                            while ($row = mysqli_fetch_assoc($src_breakdown)): ?>
                                <?php if ($filter_type === 'all'): 
                                    $net_flow = (float)$row['inc_total'] - (float)$row['exp_total'];
                                ?>
                                    <tr>
                                        <td class="col-num"><?= $si++ ?></td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($row['source_name']) ?></div>
                                            <?php if (!empty($row['bank_name'])): ?>
                                                <div class="text-muted fs-11"><?= htmlspecialchars($row['bank_name']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="font-mono" style="text-align:right; color:#059669; font-weight:600;">+&#8377;<?= number_format($row['inc_total'], 2) ?></td>
                                        <td class="font-mono" style="text-align:right; color:#dc2626; font-weight:600;">-&#8377;<?= number_format($row['exp_total'], 2) ?></td>
                                        <td class="fw-bold col-amount font-mono" style="text-align:right; color:<?= $net_flow >= 0 ? '#059669' : '#dc2626' ?>;">
                                            <?= $net_flow >= 0 ? '+' : '-' ?>&#8377;<?= number_format(abs($net_flow), 2) ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td class="col-num"><?= $si++ ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($row['source_name']) ?></td>
                                        <td class="text-muted"><?= htmlspecialchars($row['bank_name'] ?: '—') ?></td>
                                        <td class="text-muted font-mono" style="text-align:center;"><?= $row['cnt'] ?></td>
                                        <td class="fw-bold col-amount font-mono" style="text-align:right; color:<?= $filter_type === 'income' ? '#059669' : '#1e293b' ?>;">
                                            <?= $filter_type === 'income' ? '+' : '' ?>&#8377;<?= number_format($row['total'], 2) ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- FULL TRANSACTIONS TABLE -->
    <div class="card">
        <div class="card-header">
            <?php if ($filter_type === 'all'): ?>
                <span>All Transactions &mdash; <?= $total_cnt ?> records &mdash; Net: <strong style="color:<?= $grand_total >= 0 ? '#10b981' : '#ef4444' ?>;"><?= $grand_total >= 0 ? '+' : '-' ?>&#8377;<?= number_format(abs($grand_total), 2) ?></strong></span>
                <div style="display:flex;gap:8px;">
                    <a href="<?= BASE_URL ?>/actions/export_statement_excel.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" class="btn btn-sm" style="background:#7c3aed; color:#fff;">&#8595; Ledger Excel</a>
                    <a href="<?= BASE_URL ?>/actions/export_statement.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" class="btn btn-sm" style="background:#8b5cf6; color:#fff;" target="_blank">&#128438; Print Statement</a>
                </div>
            <?php elseif ($filter_type === 'expense'): ?>
                <span>All Expenses &mdash; <?= $total_cnt ?> records &mdash; &#8377;<?= number_format($grand_total, 2) ?></span>
                <div style="display:flex;gap:8px;">
                    <a href="<?= BASE_URL ?>/actions/export_excel.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&cat=<?= $exp_cat_id ?>&src=<?= $filter_src ?>" class="btn btn-sm btn-success">&#8595; Excel</a>
                    <a href="<?= BASE_URL ?>/actions/export_pdf.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&cat=<?= $exp_cat_id ?>&src=<?= $filter_src ?>" class="btn btn-sm btn-danger" target="_blank">&#8595; PDF</a>
                </div>
            <?php else: ?>
                <span>All Income &mdash; <?= $total_cnt ?> records &mdash; +&#8377;<?= number_format($grand_total, 2) ?></span>
                <div style="display:flex;gap:8px;">
                    <a href="<?= BASE_URL ?>/actions/export_income_excel.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&src=<?= $filter_src ?>" class="btn btn-sm btn-success">&#8595; Excel</a>
                    <a href="<?= BASE_URL ?>/actions/export_income_pdf.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&src=<?= $filter_src ?>" class="btn btn-sm btn-danger" target="_blank">&#8595; PDF</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="table-responsive no-scroll">
            <table class="table-compact">
                <thead>
                    <tr>
                        <th class="col-num">#</th>
                        <?php if ($filter_type === 'all'): ?>
                            <th style="width:75px; text-align:center;">Type</th>
                        <?php endif; ?>
                        <th class="col-date">Date</th>
                        <th class="col-desc">Description</th>
                        <th class="col-cat">Category</th>
                        <th class="col-source">Source</th>
                        <th class="col-amount" style="text-align:right;">Amount</th>
                        <th class="col-user">Added By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$transactions || mysqli_num_rows($transactions) == 0): ?>
                        <tr>
                            <td colspan="<?= $filter_type === 'all' ? 8 : 7 ?>">
                                <div class="empty-state">
                                    <h3>No records found for this period</h3>
                                    <p>Try selecting a different date range or adjusting your filters.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        $i = $p['start_record'];
                        while ($row = mysqli_fetch_assoc($transactions)): 
                            $is_inc = ($row['txn_type'] === 'income');
                        ?>
                            <tr>
                                <td class="col-num"><?= $i++ ?></td>
                                <?php if ($filter_type === 'all'): ?>
                                    <td style="text-align:center;">
                                        <?php if ($is_inc): ?>
                                            <span class="badge badge-success" style="font-size:10px; padding:3px 7px; text-transform:uppercase; font-weight:700;">Income</span>
                                        <?php else: ?>
                                            <span class="badge" style="font-size:10px; padding:3px 7px; text-transform:uppercase; font-weight:700; background:#fee2e2; color:#dc2626; border:1px solid #fca5a5;">Expense</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td class="col-date"><?= date('d M Y', strtotime($row['txn_date'])) ?></td>
                                <td class="col-desc" data-tooltip="<?= htmlspecialchars($row['description'] ?: '') ?>"><?= htmlspecialchars($row['description'] ?: '—') ?></td>
                                <?php 
                                $full_cat = !empty($row['parent_cat_name']) ? ($row['parent_cat_name'] . ' › ' . $row['cat_name']) : ($row['cat_name'] ?? '—');
                                ?>
                                <td class="col-cat" data-tooltip="<?= htmlspecialchars($full_cat) ?>">
                                    <span class="badge <?= $is_inc ? 'badge-success' : 'badge-info' ?> cat-badge" data-tooltip="<?= htmlspecialchars($full_cat) ?>" title="<?= htmlspecialchars($full_cat) ?>">
                                        <?= htmlspecialchars($full_cat) ?>
                                    </span>
                                </td>
                                <td class="col-source text-muted"><?= htmlspecialchars($row['source_name'] ?? '—') ?></td>
                                <td class="col-amount" style="text-align:right; font-weight:700; color:<?= $is_inc ? '#059669' : ($filter_type === 'all' ? '#dc2626' : '#1e293b') ?>;">
                                    <?= $is_inc ? '+&#8377;' : ($filter_type === 'all' ? '-&#8377;' : '&#8377;') ?><?= number_format($row['amount'], 2) ?>
                                </td>
                                <td class="col-user"><span class="badge badge-outline fs-11"><?= htmlspecialchars($row['added_by'] ?? '—') ?></span></td>
                            </tr>
                        <?php endwhile; endif; ?>
                </tbody>
                <!-- SHOW TOTALS BELOW AS REQUESTED -->
                <tfoot>
                    <?php if ($filter_type === 'all'): ?>
                        <tr style="background:#f8fafc; border-top:2px solid #cbd5e1; font-weight:700;">
                            <td colspan="6" style="text-align:right; padding:12px 16px; font-size:13px; color:#475569;">
                                <div style="display:flex; justify-content:flex-end; gap:20px; align-items:center; flex-wrap:wrap;">
                                    <span>Total Income (<?= $inc_cnt ?>): <strong style="color:#059669; font-size:14px;">+&#8377;<?= number_format($total_income, 2) ?></strong></span>
                                    <span>Total Expenses (<?= $exp_cnt ?>): <strong style="color:#dc2626; font-size:14px;">-&#8377;<?= number_format($total_expense, 2) ?></strong></span>
                                    <span style="padding-left:14px; border-left:2px solid #cbd5e1;">Net Balance:</span>
                                </div>
                            </td>
                            <td class="col-amount font-mono" style="padding:12px 14px; font-size:14px; color:<?= $grand_total >= 0 ? '#059669' : '#dc2626' ?>; font-weight:800; text-align:right;">
                                <?= $grand_total >= 0 ? '+' : '-' ?>&#8377;<?= number_format(abs($grand_total), 2) ?>
                            </td>
                            <td></td>
                        </tr>
                    <?php elseif ($filter_type === 'expense'): ?>
                        <tr style="background:#f8fafc; border-top:2px solid #cbd5e1; font-weight:700;">
                            <td colspan="5" style="text-align:right; padding:12px 16px; font-size:13px; color:#475569;">
                                Grand Total Expenses (<?= $total_cnt ?> records):
                            </td>
                            <td class="col-amount font-mono" style="padding:12px 14px; font-size:15px; color:#dc2626; font-weight:800; text-align:right;">
                                &#8377;<?= number_format($grand_total, 2) ?>
                            </td>
                            <td></td>
                        </tr>
                    <?php else: // income ?>
                        <tr style="background:#f8fafc; border-top:2px solid #cbd5e1; font-weight:700;">
                            <td colspan="5" style="text-align:right; padding:12px 16px; font-size:13px; color:#475569;">
                                Grand Total Income (<?= $total_cnt ?> records):
                            </td>
                            <td class="col-amount font-mono" style="padding:12px 14px; font-size:15px; color:#059669; font-weight:800; text-align:right;">
                                +&#8377;<?= number_format($grand_total, 2) ?>
                            </td>
                            <td></td>
                        </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>
        <?= render_pagination_bar($p) ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>