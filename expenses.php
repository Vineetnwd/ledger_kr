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
$fs = mysqli_real_escape_string($conn, $filter_start);
$fe = mysqli_real_escape_string($conn, $filter_end);

$sources_q = mysqli_query($conn, "SELECT * FROM expense_sources WHERE user_id=$user_id AND status=1 ORDER BY source_name");

// Build grouped category structure: parent -> [sub-categories]
$all_cats_q = mysqli_query($conn, "SELECT * FROM categories WHERE user_id=$user_id AND status=1 ORDER BY parent_id IS NOT NULL, name");
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
$expenses = mysqli_query($conn, "SELECT e.*, 
    c.name as cat_name, 
    p.name as parent_cat_name,
    s.source_name 
    FROM expenses e 
    LEFT JOIN categories c ON e.category_id=c.id 
    LEFT JOIN categories p ON c.parent_id=p.id
    LEFT JOIN expense_sources s ON e.source_id=s.id 
    WHERE e.user_id=$user_id AND e.status=1 AND e.expense_date BETWEEN '$fs' AND '$fe' $cat_filter_sql 
    ORDER BY e.expense_date DESC, e.id DESC");

$total_r = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM expenses e WHERE e.user_id=$user_id AND e.status=1 AND e.expense_date BETWEEN '$fs' AND '$fe' $cat_filter_sql");
$total_filtered = (float) mysqli_fetch_assoc($total_r)['total'];
$total_count = mysqli_num_rows($expenses);

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

    <!-- TABLE -->
    <div class="card">
        <div class="card-header"><span>Expense List (<?= $total_count ?> records)</span></div>
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
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total_count == 0): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <h3>No expenses found</h3>
                                    <p>Try adjusting filters or add a new expense.</p>
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
                                        <span class="text-muted fs-12"><?= htmlspecialchars($row['parent_cat_name']) ?>
                                            &rsaquo;</span>
                                    <?php endif; ?>
                                    <span class="badge badge-info"><?= htmlspecialchars($row['cat_name'] ?? '—') ?></span>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($row['source_name'] ?? '—') ?></td>
                                <td class="fw-bold">&#8377;<?= number_format($row['amount'], 2) ?></td>
                                <td>
                                    <form method="POST" action="<?= BASE_URL ?>/actions/expense_action.php"
                                        style="display:inline;" onsubmit="return confirm('Cancel this expense?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="expense_id" value="<?= $row['id'] ?>">
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