<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}
$page_title = 'Categories — KRNC Expense Ledger';
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

// Fetch parent categories with counts
$parents = mysqli_query($conn, "SELECT c.*,
    COUNT(DISTINCT sub.id) as sub_count,
    COUNT(DISTINCT e.id)   as expense_count,
    COALESCE(SUM(e.amount),0) as total_amount
    FROM categories c
    LEFT JOIN categories sub ON sub.parent_id = c.id AND sub.status = 1
    LEFT JOIN expenses e ON e.category_id = c.id AND e.status = 1
    WHERE c.user_id = $user_id AND c.status = 1 AND c.parent_id IS NULL
    GROUP BY c.id ORDER BY c.name");

// Pre-fetch sub-categories grouped by parent
$sub_map = [];
$sub_result = mysqli_query($conn, "SELECT c.*,
    COUNT(DISTINCT e.id) as expense_count,
    COALESCE(SUM(e.amount),0) as total_amount
    FROM categories c
    LEFT JOIN expenses e ON e.category_id = c.id AND e.status = 1
    WHERE c.user_id = $user_id AND c.status = 1 AND c.parent_id IS NOT NULL
    GROUP BY c.id ORDER BY c.name");
while ($sub = mysqli_fetch_assoc($sub_result)) {
    $sub_map[$sub['parent_id']][] = $sub;
}

$preselect_parent = (int) ($_GET['parent'] ?? 0);

include 'includes/header.php';
?>

<div class="topbar">
    <div>
        <div class="topbar-title">Categories</div>
        <div class="topbar-sub">Organize expenses into categories &amp; sub-categories</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button onclick="openModal('addSubModal')" class="btn btn-outline">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Add Sub-category
        </button>
        <button onclick="openModal('addCatModal')" class="btn btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Add Category
        </button>
    </div>
</div>

<div class="page-content">
    <?= $message ?>

    <?php if (mysqli_num_rows($parents) == 0): ?>
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <svg width="40" height="40" fill="none" stroke="#cbd5e1" viewBox="0 0 24 24"
                        style="margin:0 auto 12px;display:block;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
                        <line x1="7" y1="7" x2="7.01" y2="7" />
                    </svg>
                    <h3>No categories yet</h3>
                    <p>Add categories like &ldquo;Food&rdquo;, &ldquo;Travel&rdquo;, &ldquo;Utilities&rdquo; to organize
                        expenses.</p>
                </div>
            </div>
        </div>

    <?php else:
        mysqli_data_seek($parents, 0);
        while ($parent = mysqli_fetch_assoc($parents)): ?>

            <!-- PARENT CATEGORY CARD -->
            <div class="card mb-16">

                <!-- Parent Header Row -->
                <div style="
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        ">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <!-- Icon -->
                        <div style="
                    width:38px; height:38px; border-radius:10px;
                    background:#eef2ff; border:1px solid #c7d2fe;
                    display:flex; align-items:center; justify-content:center;
                    font-size:18px; flex-shrink:0;
                ">&#128193;</div>

                        <div>
                            <div style="font-weight:700; font-size:15px; color:#1e293b; line-height:1.2;">
                                <?= htmlspecialchars($parent['name']) ?>
                            </div>
                            <div style="font-size:11px; color:#64748b; margin-top:2px;">
                                <?= $parent['expense_count'] ?> direct expense<?= $parent['expense_count'] != 1 ? 's' : '' ?>
                                &bull;
                                <?= $parent['sub_count'] ?> sub-categor<?= $parent['sub_count'] != 1 ? 'ies' : 'y' ?>
                                <?php if ($parent['total_amount'] > 0): ?>
                                    &bull; &#8377;<?= number_format($parent['total_amount'], 2) ?> direct
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div style="display:flex; align-items:center; gap:8px;">
                        <button
                            onclick="openAddSub(<?= $parent['id'] ?>, '<?= htmlspecialchars(addslashes($parent['name'])) ?>')"
                            class="btn btn-sm btn-outline">
                            + Sub-category
                        </button>
                        <button onclick="editCategory(<?= $parent['id'] ?>, '<?= htmlspecialchars(addslashes($parent['name'])) ?>')" class="btn btn-sm btn-secondary">
                            Edit
                        </button>
                        <form method="POST" action="<?= BASE_URL ?>/actions/category_action.php" style="display:inline;"
                            onsubmit="return confirm('Delete this category?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="category_id" value="<?= $parent['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" <?= ($parent['expense_count'] > 0 || $parent['sub_count'] > 0) ? 'disabled title="Has linked data — delete sub-categories and expenses first."' : '' ?>>
                                Delete
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Sub-categories List -->
                <?php if (!empty($sub_map[$parent['id']])): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="padding-left:54px;">Sub-category</th>
                                    <th>Expenses</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sub_map[$parent['id']] as $sub): ?>
                                    <tr>
                                        <td style="padding-left:54px;">
                                            <span style="color:#cbd5e1; margin-right:6px; font-size:14px;">&#9492;</span>
                                            <span class="badge badge-info"><?= htmlspecialchars($sub['name']) ?></span>
                                        </td>
                                        <td style="color:#64748b;"><?= $sub['expense_count'] ?>
                                            expense<?= $sub['expense_count'] != 1 ? 's' : '' ?></td>
                                        <td style="font-weight:700;">&#8377;<?= number_format($sub['total_amount'], 2) ?></td>
                                        <td><span class="badge badge-success">Active</span></td>
                                        <td>
                                            <button onclick="editCategory(<?= $sub['id'] ?>, '<?= htmlspecialchars(addslashes($sub['name'])) ?>')" class="btn btn-sm btn-secondary">
                                                Edit
                                            </button>
                                            <form method="POST" action="<?= BASE_URL ?>/actions/category_action.php"
                                                style="display:inline;" onsubmit="return confirm('Delete this sub-category?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="category_id" value="<?= $sub['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" <?= $sub['expense_count'] > 0 ? 'disabled title="Has linked expenses"' : '' ?>>
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php else: ?>
                    <!-- No sub-categories hint -->
                    <div style="padding:11px 18px 11px 54px; border-top:1px solid #f1f5f9; font-size:12px; color:#94a3b8;">
                        No sub-categories yet.
                        <button onclick="openAddSub(<?= $parent['id'] ?>, '<?= htmlspecialchars(addslashes($parent['name'])) ?>')"
                            style="background:none;border:none;color:#6366f1;cursor:pointer;font-size:12px;font-weight:600;padding:0 0 0 4px;">
                            Add one &rarr;
                        </button>
                    </div>
                <?php endif; ?>

            </div><!-- /card -->

        <?php endwhile; endif; ?>
</div><!-- /page-content -->

<!-- ===== ADD PARENT CATEGORY MODAL ===== -->
<div id="addCatModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <span>Add New Category</span>
            <button class="modal-close" onclick="closeModal('addCatModal')">&#215;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/actions/category_action.php">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="parent_id" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Food, Travel, Utilities"
                        required autofocus>
                </div>
                <p style="font-size:12px; color:#64748b; margin-top:4px;">
                    This creates a <strong>top-level</strong> category.
                    Use &ldquo;Add Sub-category&rdquo; to add items under it.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addCatModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Category</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== ADD SUB-CATEGORY MODAL ===== -->
<div id="addSubModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <span>Add Sub-category</span>
            <button class="modal-close" onclick="closeModal('addSubModal')">&#215;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/actions/category_action.php">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Parent Category *</label>
                    <select name="parent_id" id="sub_parent_id" class="form-control" required>
                        <option value="">Select Parent Category</option>
                        <?php mysqli_data_seek($parents, 0);
                        while ($p = mysqli_fetch_assoc($parents)): ?>
                            <option value="<?= $p['id'] ?>" <?= $preselect_parent == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Sub-category Name *</label>
                    <input type="text" name="name" id="sub_name_input" class="form-control"
                        placeholder="e.g. Breakfast, Air Travel, Electricity" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addSubModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Sub-category</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddSub(parentId, parentName) {
        var sel = document.getElementById('sub_parent_id');
        if (sel) sel.value = parentId;
        openModal('addSubModal');
        setTimeout(function () {
            var inp = document.getElementById('sub_name_input');
            if (inp) inp.focus();
        }, 100);
    }
    <?php if ($preselect_parent > 0): ?>
        document.addEventListener('DOMContentLoaded', function () { openModal('addSubModal'); });
    <?php endif; ?>
    function editCategory(id, currentName) {
        var newName = prompt("Edit category name:", currentName);
        if (newName !== null && newName.trim() !== "" && newName.trim() !== currentName) {
            document.getElementById('edit_cat_id').value = id;
            document.getElementById('edit_cat_name').value = newName.trim();
            document.getElementById('editCatForm').submit();
        }
    }
</script>

<form id="editCatForm" method="POST" action="<?= BASE_URL ?>/actions/category_action.php" style="display:none;">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="category_id" id="edit_cat_id">
    <input type="hidden" name="name" id="edit_cat_name">
</form>

<?php include 'includes/footer.php'; ?>