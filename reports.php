<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}
$page_title = 'Reports — KRNC Expense Ledger';
$user_id = $_SESSION['user_id'];

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$fs = mysqli_real_escape_string($conn, $start_date);
$fe = mysqli_real_escape_string($conn, $end_date);

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

// Fetch lists for filter dropdowns
$parent_cats = [];
$sub_cats = [];
$cats_q = mysqli_query($conn, "SELECT * FROM categories WHERE user_id=$user_id AND status=1 ORDER BY name ASC");
while ($c = mysqli_fetch_assoc($cats_q)) {
    if (empty($c['parent_id'])) {
        $parent_cats[$c['id']] = $c;
    } else {
        $sub_cats[$c['parent_id']][] = $c;
    }
}
$sources_q = mysqli_query($conn, "SELECT * FROM expense_sources WHERE user_id=$user_id AND status=1 ORDER BY source_name ASC");

// Summary
// Summary
$total_r = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total, COUNT(*) as cnt FROM expenses e WHERE e.user_id=$user_id AND e.status=1 AND e.expense_date BETWEEN '$fs' AND '$fe' $extra_sql");
$summary = mysqli_fetch_assoc($total_r);
$grand_total = (float) $summary['total'];
$total_cnt = (int) $summary['cnt'];
$avg = $total_cnt > 0 ? $grand_total / $total_cnt : 0;

// Category breakdown (with parent category name)
$cat_breakdown = mysqli_query($conn, "SELECT c.name, p.name as parent_name, COALESCE(SUM(e.amount),0) as total, COUNT(e.id) as cnt FROM categories c LEFT JOIN categories p ON c.parent_id = p.id JOIN expenses e ON e.category_id=c.id WHERE e.user_id=$user_id AND e.status=1 AND e.expense_date BETWEEN '$fs' AND '$fe' $extra_sql GROUP BY c.id ORDER BY COALESCE(p.name, c.name) ASC, c.parent_id IS NULL DESC, c.name ASC");

// Source breakdown
$src_breakdown = mysqli_query($conn, "SELECT s.source_name, s.bank_name, COALESCE(SUM(e.amount),0) as total, COUNT(e.id) as cnt FROM expense_sources s JOIN expenses e ON e.source_id=s.id WHERE e.user_id=$user_id AND e.status=1 AND e.expense_date BETWEEN '$fs' AND '$fe' $extra_sql GROUP BY s.id ORDER BY total DESC");

// Expense rows
$expenses = mysqli_query($conn, "SELECT e.*, c.name as cat_name, p.name as parent_cat_name, s.source_name FROM expenses e LEFT JOIN categories c ON e.category_id=c.id LEFT JOIN categories p ON c.parent_id = p.id LEFT JOIN expense_sources s ON e.source_id=s.id WHERE e.user_id=$user_id AND e.status=1 AND e.expense_date BETWEEN '$fs' AND '$fe' $extra_sql ORDER BY e.expense_date DESC");

include 'includes/header.php';
?>

<div class="topbar">
    <div>
        <div class="topbar-title">Reports</div>
        <div class="topbar-sub">Generate and export reports and ledger statements</div>
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
                    class="btn" style="background:#8b5cf6; border-color:#8b5cf6; color:#fff; text-align:center; justify-content:center; gap:6px;">
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
                <a href="<?= BASE_URL ?>/actions/export_excel.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&cat=<?= $filter_cat ?>&src=<?= $filter_src ?>"
                    class="btn" style="background:#ef4444; border-color:#ef4444; color:#fff; text-align:center; justify-content:center; gap:6px;">
                    &#8595; Expense Excel
                </a>
                <a href="<?= BASE_URL ?>/actions/export_pdf.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&cat=<?= $filter_cat ?>&src=<?= $filter_src ?>"
                    class="btn" style="background:#dc2626; border-color:#dc2626; color:#fff; text-align:center; justify-content:center; gap:6px;">
                    &#8595; Expense PDF
                </a>
            </div>

            <!-- Incomes Group -->
            <div style="display:flex; flex-direction:column; gap:8px; background:#f6fcf9; border:1px solid #d1fae5; padding:12px; border-radius:10px;">
                <div style="font-size:10px; font-weight:700; color:#059669; text-transform:uppercase; letter-spacing:0.5px;">3. Income Reports</div>
                <a href="<?= BASE_URL ?>/actions/export_income_excel.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"
                    class="btn" style="background:#10b981; border-color:#10b981; color:#fff; text-align:center; justify-content:center; gap:6px;">
                    &#8595; Income Excel
                </a>
                <a href="<?= BASE_URL ?>/actions/export_income_pdf.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"
                    class="btn" style="background:#059669; border-color:#059669; color:#fff; text-align:center; justify-content:center; gap:6px;">
                    &#8595; Income PDF
                </a>
            </div>
        </div>
    </div>

    <!-- DATE FILTER -->
    <form method="GET" class="card mb-20">
        <div class="filter-bar">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Category</label>
                <select name="cat" class="form-control">
                    <option value="0">All Categories</option>
                    <?php foreach ($parent_cats as $pid => $pc): ?>
                        <option value="<?= $pid ?>" <?= $filter_cat == $pid ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pc['name']) ?></option>
                        <?php if (!empty($sub_cats[$pid])):
                            foreach ($sub_cats[$pid] as $sc): ?>
                                <option value="<?= $sc['id'] ?>" <?= $filter_cat == $sc['id'] ? 'selected' : '' ?>>
                                    &nbsp;&nbsp;&nbsp;&#9492; <?= htmlspecialchars($sc['name']) ?>
                                </option>
                            <?php endforeach; endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
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
            <div><button type="submit" class="btn btn-primary">Generate Report</button></div>
        </div>
    </form>

    <!-- SUMMARY CARDS -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:14px;margin-bottom:20px;">
        <div class="card" style="border-top:3px solid #6366f1;">
            <div class="card-body" style="text-align:center;padding:20px;">
                <div class="text-muted fs-11" style="text-transform:uppercase;letter-spacing:0.5px;font-weight:700;">
                    Total Amount</div>
                <div style="font-size:28px;font-weight:800;margin:8px 0;">&#8377;<?= number_format($grand_total, 2) ?>
                </div>
                <div class="text-muted fs-12"><?= date('d M Y', strtotime($start_date)) ?> &mdash;
                    <?= date('d M Y', strtotime($end_date)) ?></div>
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
                <div class="text-muted fs-11" style="text-transform:uppercase;letter-spacing:0.5px;font-weight:700;">Avg
                    Per Transaction</div>
                <div style="font-size:28px;font-weight:800;margin:8px 0;">&#8377;<?= number_format($avg, 2) ?></div>
                <div class="text-muted fs-12">In selected period</div>
            </div>
        </div>
    </div>

    <!-- BREAKDOWNS -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:14px;margin-bottom:20px;">
        <div class="card">
            <div class="card-header">By Category</div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Count</th>
                            <th>Total</th>
                            <th>Share %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($cat_breakdown) == 0): ?>
                            <tr>
                                <td colspan="4" style="text-align:center;color:#94a3b8;padding:20px;">No data for this
                                    period.</td>
                            </tr>
                        <?php else:
                            while ($row = mysqli_fetch_assoc($cat_breakdown)):
                                $share = $grand_total > 0 ? round(((float) $row['total'] / $grand_total) * 100, 1) : 0;
                                $is_sub = !empty($row['parent_name']);
                                ?>
                                <tr>
                                    <td>
                                    <?php if ($is_sub): ?>
                                        <span class="badge badge-info"><?= htmlspecialchars($row['parent_name']) ?> =&gt; <?= htmlspecialchars($row['name']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-info"><?= htmlspecialchars($row['name']) ?></span>
                                    <?php endif; ?>
                                </td>
                                    <td class="text-muted"><?= $row['cnt'] ?></td>
                                    <td class="fw-bold">&#8377;<?= number_format($row['total'], 2) ?></td>
                                    <td><?= $share ?>%</td>
                                </tr>
                            <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header">By Source / Bank</div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Bank</th>
                            <th>Count</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($src_breakdown) == 0): ?>
                            <tr>
                                <td colspan="4" style="text-align:center;color:#94a3b8;padding:20px;">No data for this
                                    period.</td>
                            </tr>
                        <?php else:
                            while ($row = mysqli_fetch_assoc($src_breakdown)): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($row['source_name']) ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($row['bank_name'] ?: '—') ?></td>
                                    <td class="text-muted"><?= $row['cnt'] ?></td>
                                    <td class="fw-bold">&#8377;<?= number_format($row['total'], 2) ?></td>
                                </tr>
                            <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- FULL TABLE -->
    <div class="card">
        <div class="card-header">
            <span>All Expenses — <?= $total_cnt ?> records &mdash; &#8377;<?= number_format($grand_total, 2) ?></span>
            <div style="display:flex;gap:8px;">
                <a href="<?= BASE_URL ?>/actions/export_excel.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&cat=<?= $filter_cat ?>&src=<?= $filter_src ?>"
                    class="btn btn-sm btn-success">&#8595; Excel</a>
                <a href="<?= BASE_URL ?>/actions/export_pdf.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&cat=<?= $filter_cat ?>&src=<?= $filter_src ?>"
                    class="btn btn-sm btn-danger">&#8595; PDF</a>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Source</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($expenses) == 0): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <h3>No expenses in this period</h3>
                                    <p>Try a different date range.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        $i = 1;
                        while ($row = mysqli_fetch_assoc($expenses)): ?>
                            <tr>
                                <td class="text-muted"><?= $i++ ?></td>
                                <td><?= date('d M Y', strtotime($row['expense_date'])) ?></td>
                                <td><?= htmlspecialchars($row['description'] ?: '—') ?></td>
                                <td>
                                    <?php if (!empty($row['parent_cat_name'])): ?>
                                        <span class="badge badge-info"><?= htmlspecialchars($row['parent_cat_name']) ?> =&gt; <?= htmlspecialchars($row['cat_name'] ?? '—') ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-info"><?= htmlspecialchars($row['cat_name'] ?? '—') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($row['source_name'] ?? '—') ?></td>
                                <td class="fw-bold">&#8377;<?= number_format($row['amount'], 2) ?></td>
                            </tr>
                        <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>