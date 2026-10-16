<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}
$page_title = 'Expenses — KRNC Expense Ledger';
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
$filter_cat = (int) ($_GET['cat'] ?? 0);
$filter_src = (int) ($_GET['src'] ?? 0);
$fs = mysqli_real_escape_string($conn, $filter_start);
$fe = mysqli_real_escape_string($conn, $filter_end);

$sources_q = mysqli_query($conn, "SELECT * FROM expense_sources WHERE status=1 ORDER BY source_name");

// Build grouped category structure: parent -> [sub-categories]
$all_cats_q = mysqli_query($conn, "SELECT * FROM categories WHERE status=1 ORDER BY parent_id IS NOT NULL, name");
$parent_cats = [];
$sub_cats = [];
while ($c = mysqli_fetch_assoc($all_cats_q)) {
    if ($c['parent_id'] === null) {
        $parent_cats[$c['id']] = $c;
    } else {
        $sub_cats[$c['parent_id']][] = $c;
    }
}

$cat_filter_sql = $filter_cat > 0 ? "AND e.category_id = $filter_cat" : '';
$src_filter_sql = $filter_src > 0 ? "AND e.source_id = $filter_src" : '';

// Overall period expenses total (for share calculation)
$overall_period_expense_r = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE status = 1 AND expense_date BETWEEN '$fs' AND '$fe'");
$overall_period_total = (float) mysqli_fetch_assoc($overall_period_expense_r)['total'];

// Source breakdown widget query for selected date range
$sources_widget_q = mysqli_query($conn, "
    SELECT s.id, s.source_name, s.bank_name, s.account_number,
           COALESCE(SUM(e.amount), 0) as total_amount,
           COUNT(e.id) as txn_count
    FROM expense_sources s
    LEFT JOIN expenses e ON e.source_id = s.id 
                        AND e.status = 1 
                        AND e.expense_date BETWEEN '$fs' AND '$fe'
    WHERE s.status = 1
    GROUP BY s.id
    ORDER BY total_amount DESC, s.source_name ASC
");

$total_r = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total, COUNT(*) as cnt FROM expenses e WHERE e.status=1 AND e.expense_date BETWEEN '$fs' AND '$fe' $cat_filter_sql $src_filter_sql");
$total_row = mysqli_fetch_assoc($total_r);
$total_filtered = (float) $total_row['total'];
$total_count = (int) $total_row['cnt'];

$p = get_pagination_params($total_count, 20);

$expenses = mysqli_query($conn, "SELECT e.*, 
    c.name as cat_name, 
    p.name as parent_cat_name,
    s.source_name,
    u.name as added_by 
    FROM expenses e 
    LEFT JOIN categories c ON e.category_id=c.id 
    LEFT JOIN categories p ON c.parent_id=p.id
    LEFT JOIN expense_sources s ON e.source_id=s.id 
    LEFT JOIN users u ON e.user_id=u.id 
    WHERE e.status=1 AND e.expense_date BETWEEN '$fs' AND '$fe' $cat_filter_sql $src_filter_sql 
    ORDER BY e.expense_date DESC, e.id DESC
    LIMIT {$p['offset']}, {$p['limit']}");

include 'includes/header.php';
?>
<style>
    .modal-header {
        border-radius: 12%;
    }

    .modal-footer {
        border-radius: 12%;
    }
</style>

<div class="topbar">
    <div>
        <div class="topbar-title">Expenses</div>
        <div class="topbar-sub">Track and manage your spending</div>
    </div>
    <button onclick="openModal('addExpenseModal')" class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            stroke-linecap="round">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Add Expense
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
                    <option value="0">All Categories</option>
                    <?php foreach ($parent_cats as $pid => $pc): ?>
                        <option value="<?= $pid ?>" <?= $filter_cat == $pid ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pc['name']) ?>
                        </option>
                        <?php if (!empty($sub_cats[$pid])):
                            foreach ($sub_cats[$pid] as $sc): ?>
                                <option value="<?= $sc['id'] ?>" <?= $filter_cat == $sc['id'] ? 'selected' : '' ?>>
                                    &nbsp;&nbsp;&nbsp;&#9492; <?= htmlspecialchars($sc['name']) ?>
                                </option>
                            <?php endforeach; endif; ?>
                    <?php endforeach; ?>
                    <?php
                    // Orphan sub-cats (parent deleted)
                    foreach ($sub_cats as $pid => $subs) {
                        if (!isset($parent_cats[$pid])) {
                            foreach ($subs as $sc) {
                                echo '<option value="' . $sc['id'] . '" ' . ($filter_cat == $sc['id'] ? 'selected' : '') . '>' . htmlspecialchars($sc['name']) . ' (orphan)</option>';
                            }
                        }
                    }
                    ?>
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
                <a href="<?= BASE_URL ?>/expenses.php" class="btn btn-outline" style="margin-left:6px;">Reset</a>
            </div>
            <div style="margin-left:auto;">
                <span class="text-muted fs-12">Total:</span>
                <strong
                    style="font-size:20px; margin-left:8px;">&#8377;<?= number_format($total_filtered, 2) ?></strong>
            </div>
        </div>
    </form>

    <!-- SOURCES BREAKDOWN WIDGET -->
    <div style="margin-bottom: 20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 10px;">
            <div style="font-weight: 700; font-size: 13px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                Expenses by Source / Account (<?= date('d M Y', strtotime($filter_start)) ?> &ndash; <?= date('d M Y', strtotime($filter_end)) ?>)
            </div>
            <?php if ($filter_src > 0): ?>
                <a href="?start=<?= urlencode($filter_start) ?>&end=<?= urlencode($filter_end) ?>&cat=<?= $filter_cat ?>&src=0" class="btn btn-sm btn-outline" style="font-size: 11px; padding: 2px 8px;">
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
                $s_url = "?start=" . urlencode($filter_start) . "&end=" . urlencode($filter_end) . "&cat=" . $filter_cat . "&src=" . $toggle_src;
                $acc_suffix = $s_card['account_number'] ? ' •••• ' . htmlspecialchars(substr($s_card['account_number'], -4)) : '';
            ?>
            <a href="<?= $s_url ?>" class="source-widget-card <?= $is_active ? 'active' : '' ?>" title="Click to filter by <?= htmlspecialchars($s_card['source_name']) ?>">
                <div>
                    <div class="source-widget-header">
                        <div class="source-widget-title"><?= htmlspecialchars($s_card['source_name']) ?></div>
                        <?php if ($is_active): ?>
                            <span class="badge badge-info" style="font-size: 10px; padding: 1px 6px;">Active</span>
                        <?php endif; ?>
                    </div>
                    <div class="source-widget-subtitle"><?= htmlspecialchars($s_card['bank_name'] ?: 'Account') ?><?= $acc_suffix ?></div>
                    <div class="source-widget-amount" style="color: #6366f1;">&#8377;<?= number_format($s_card['total_amount'], 2) ?></div>
                </div>
                <div class="source-widget-footer">
                    <span><?= $s_card['txn_count'] ?> transaction<?= $s_card['txn_count'] != 1 ? 's' : '' ?></span>
                    <span style="font-weight: 600; color: #6366f1;"><?= $s_share ?>%</span>
                </div>
            </a>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- TABLE -->
    <div class="card">
        <div class="card-header"><span>Expense List (<?= $total_count ?> records)</span></div>
        <div class="table-responsive no-scroll">
            <table class="table-compact">
                <thead>
                    <tr>
                        <th class="col-num">#</th>
                        <th class="col-date">Date</th>
                        <th class="col-desc">Description</th>
                        <th class="col-cat">Category</th>
                        <th class="col-source">Source</th>
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
                                    <h3>No expenses found</h3>
                                    <p>Try adjusting filters or add a new expense.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        $i = $p['start_record'];
                        while ($row = mysqli_fetch_assoc($expenses)): ?>
                            <tr>
                                <td class="col-num"><?= $i++ ?></td>
                                <td class="col-date"><?= date('d M Y', strtotime($row['expense_date'])) ?></td>
                                <td class="col-desc" data-tooltip="<?= htmlspecialchars($row['description'] ?: '') ?>"><?= htmlspecialchars($row['description'] ?: '—') ?></td>
                                <?php 
                                $full_cat = !empty($row['parent_cat_name']) ? ($row['parent_cat_name'] . ' › ' . $row['cat_name']) : ($row['cat_name'] ?? '—');
                                ?>
                                <td class="col-cat" data-tooltip="<?= htmlspecialchars($full_cat) ?>">
                                    <span class="badge badge-info cat-badge" data-tooltip="<?= htmlspecialchars($full_cat) ?>" title="<?= htmlspecialchars($full_cat) ?>"><?= htmlspecialchars($full_cat) ?></span>
                                </td>
                                <td class="col-source text-muted"><?= htmlspecialchars($row['source_name'] ?? '—') ?></td>
                                <td class="col-amount">&#8377;<?= number_format($row['amount'], 2) ?></td>
                                <td class="col-user"><span class="badge badge-outline fs-11"><?= htmlspecialchars($row['added_by'] ?? '—') ?></span></td>
                                <td class="col-action">
                                    <form method="POST" action="<?= BASE_URL ?>/actions/expense_action.php"
                                        style="display:inline;" onsubmit="return confirm('Cancel this expense?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="expense_id" value="<?= $row['id'] ?>">
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

<!-- ADD EXPENSE MODAL -->
<div id="addExpenseModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <span>Add New Expense</span>
            <button class="modal-close" onclick="closeModal('addExpenseModal')">&#215;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/actions/expense_action.php">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <?php
                $no_cats = (empty($parent_cats) && empty($sub_cats));
                $no_sources = (mysqli_num_rows($sources_q) == 0);
                if ($no_cats || $no_sources):
                    ?>
                    <div class="alert alert-info">
                        <?php if ($no_cats): ?>Please <a href="<?= BASE_URL ?>/categories.php"
                                style="color:inherit;font-weight:700;">add a category</a> first.<?php endif; ?>
                        <?php if ($no_sources): ?>Please <a href="<?= BASE_URL ?>/sources.php"
                                style="color:inherit;font-weight:700;">add a source/bank</a> first.<?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>"
                            required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount (&#8377;) *</label>
                        <input type="number" name="amount" step="0.01" min="0.01" class="form-control"
                            placeholder="0.00" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"
                            style="display:flex; justify-content:space-between; align-items:center;">
                            <span>Parent Category *</span>
                            <a href="javascript:void(0)" onclick="quickAddCategory(null)"
                                style="color:#6366f1; text-decoration:none; display:flex; align-items:center; gap:4px;"
                                title="Quick Add Parent Category">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Add
                            </a>
                        </label>
                        <select id="parent_cat_select" class="form-control" required>
                            <option value="">Select Parent Category</option>
                            <?php foreach ($parent_cats as $pid => $pc): ?>
                                <option value="<?= $pid ?>"><?= htmlspecialchars($pc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="sub_cat_wrapper" style="display:none;">
                        <label class="form-label"
                            style="display:flex; justify-content:space-between; align-items:center;">
                            <span>Sub Category (Optional)</span>
                            <a href="javascript:void(0)"
                                onclick="quickAddCategory(document.getElementById('parent_cat_select').value)"
                                style="color:#6366f1; text-decoration:none; display:flex; align-items:center; gap:4px;"
                                title="Quick Add Sub Category">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Add
                            </a>
                        </label>
                        <select id="sub_cat_select" class="form-control">
                            <option value="">None (General)</option>
                        </select>
                    </div>
                    <input type="hidden" name="category_id" id="final_category_id" value="">

                    <script>
                        const subCatsMap = <?= json_encode($sub_cats) ?>;

                        async function quickAddCategory(parentId) {
                            if (parentId !== null && !parentId) {
                                alert("Please select a parent category first.");
                                return;
                            }
                            const name = prompt(parentId ? "Enter new sub-category name:" : "Enter new parent category name:");
                            if (!name || !name.trim()) return;

                            const fd = new FormData();
                            fd.append('action', 'add');
                            fd.append('name', name.trim());
                            if (parentId) fd.append('parent_id', parentId);
                            fd.append('ajax', '1');

                            try {
                                const res = await fetch('<?= BASE_URL ?>/actions/category_action.php', { method: 'POST', body: fd });
                                const data = await res.json();
                                if (data.success) {
                                    if (parentId) {
                                        if (!subCatsMap[parentId]) subCatsMap[parentId] = [];
                                        subCatsMap[parentId].push({ id: data.id.toString(), name: data.name });
                                        document.getElementById('parent_cat_select').dispatchEvent(new Event('change'));
                                        setTimeout(() => {
                                            const subSel = document.getElementById('sub_cat_select');
                                            if (subSel.tomselect) subSel.tomselect.setValue(data.id.toString());
                                            else subSel.value = data.id.toString();
                                            document.getElementById('final_category_id').value = data.id.toString();
                                        }, 100);
                                    } else {
                                        const parentSel = document.getElementById('parent_cat_select');
                                        if (parentSel.tomselect) {
                                            parentSel.tomselect.addOption({value: data.id.toString(), text: data.name});
                                            parentSel.tomselect.setValue(data.id.toString());
                                        } else {
                                            const opt = document.createElement('option');
                                            opt.value = data.id;
                                            opt.textContent = data.name;
                                            parentSel.appendChild(opt);
                                            parentSel.value = data.id;
                                        }
                                        parentSel.dispatchEvent(new Event('change'));
                                    }
                                } else {
                                    alert(data.error || "Failed to add category");
                                }
                            } catch (e) {
                                alert("An error occurred. Please try again.");
                            }
                        }

                        document.addEventListener('DOMContentLoaded', function () {
                            const parentSel = document.getElementById('parent_cat_select');
                            const subSel = document.getElementById('sub_cat_select');
                            const subWrapper = document.getElementById('sub_cat_wrapper');
                            const finalCat = document.getElementById('final_category_id');

                            function updateFinalCat() {
                                finalCat.value = subSel.value || parentSel.value;
                            }

                            parentSel.addEventListener('change', function () {
                                const pid = this.value;

                                if (subSel.tomselect) {
                                    const ts = subSel.tomselect;
                                    ts.clear();
                                    ts.clearOptions();
                                    ts.addOption({ value: "", text: "None (General)" });

                                    if (pid) {
                                        subWrapper.style.display = 'block';
                                        if (subCatsMap[pid] && subCatsMap[pid].length > 0) {
                                            subCatsMap[pid].forEach(sc => {
                                                ts.addOption({ value: sc.id, text: sc.name });
                                            });
                                        }
                                        ts.setValue("");
                                    } else {
                                        subWrapper.style.display = 'none';
                                        ts.setValue("");
                                    }
                                } else {
                                    // Fallback if TomSelect isn't initialized yet
                                    subSel.innerHTML = '<option value="">None (General)</option>';
                                    if (pid) {
                                        subWrapper.style.display = 'block';
                                        if (subCatsMap[pid] && subCatsMap[pid].length > 0) {
                                            subCatsMap[pid].forEach(sc => {
                                                const opt = document.createElement('option');
                                                opt.value = sc.id;
                                                opt.textContent = sc.name;
                                                subSel.appendChild(opt);
                                            });
                                        }
                                    } else {
                                        subWrapper.style.display = 'none';
                                    }
                                }

                                updateFinalCat();
                            });

                            subSel.addEventListener('change', updateFinalCat);
                        });
                    </script>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">Source / Bank *</label>
                        <select name="source_id" class="form-control" required>
                            <option value="">Select Source</option>
                            <?php mysqli_data_seek($sources_q, 0);
                            while ($s = mysqli_fetch_assoc($sources_q)): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['source_name']) ?>
                                    <?= $s['bank_name'] ? '(' . $s['bank_name'] . ')' : '' ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" placeholder="What was this expense for?">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addExpenseModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>