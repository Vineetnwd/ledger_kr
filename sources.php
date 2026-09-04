<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}
$page_title = 'Sources — KRNC Expense Ledger';
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

// Handle add
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'add') {
    $source_name = mysqli_real_escape_string($conn, trim($_POST['source_name']));
    $bank_name = mysqli_real_escape_string($conn, trim($_POST['bank_name']));
    $account_number = mysqli_real_escape_string($conn, trim($_POST['account_number']));
    $ifsc_code = mysqli_real_escape_string($conn, strtoupper(trim($_POST['ifsc_code'])));

    if (empty($source_name)) {
        $_SESSION['error'] = "Source name is required.";
    } else {
        mysqli_query($conn, "INSERT INTO expense_sources (user_id, source_name, bank_name, account_number, ifsc_code, created_by) VALUES ($user_id, '$source_name', '$bank_name', '$account_number', '$ifsc_code', $user_id)");
        $_SESSION['success'] = "Source '$source_name' added!";
    }
    header("Location: " . BASE_URL . "/sources.php");
    exit();
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'delete') {
    $sid = (int) $_POST['source_id'];
    $chk = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM expenses WHERE source_id=$sid AND status=1");
    $cnt = (int) mysqli_fetch_assoc($chk)['cnt'];
    if ($cnt > 0) {
        $_SESSION['error'] = "Cannot delete: $cnt active expense(s) are linked to this source.";
    } else {
        mysqli_query($conn, "UPDATE expense_sources SET status=0, updated_by=$user_id WHERE id=$sid AND user_id=$user_id");
        $_SESSION['success'] = "Source deleted.";
    }
    header("Location: " . BASE_URL . "/sources.php");
    exit();
}

$sources = mysqli_query($conn, "SELECT s.*, COUNT(e.id) as expense_count, COALESCE(SUM(e.amount),0) as total_amount FROM expense_sources s LEFT JOIN expenses e ON e.source_id=s.id AND e.status=1 WHERE s.user_id=$user_id AND s.status=1 GROUP BY s.id ORDER BY s.source_name");

include 'includes/header.php';
?>

<div class="topbar">
    <div>
        <div class="topbar-title">Sources / Bank Accounts</div>
        <div class="topbar-sub">Manage your payment sources</div>
    </div>
    <button onclick="openModal('addSourceModal')" class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            stroke-linecap="round">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Add Source
    </button>
</div>

<div class="page-content">
    <?= $message ?>
    <div class="card">
        <div class="card-header"><span>All Sources (<?= mysqli_num_rows($sources) ?>)</span></div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Bank</th>
                        <th>Account No.</th>
                        <th>IFSC</th>
                        <th>Expenses</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($sources) == 0): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <h3>No sources yet</h3>
                                    <p>Add your bank accounts, wallets, or cash sources.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        $i = 1;
                        while ($row = mysqli_fetch_assoc($sources)): ?>
                            <tr>
                                <td class="text-muted"><?= $i++ ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($row['source_name']) ?></td>
                                <td><?= htmlspecialchars($row['bank_name'] ?: '—') ?></td>
                                <td class="text-muted">
                                    <?= $row['account_number'] ? '&bull;&bull;&bull;&bull; ' . htmlspecialchars(substr($row['account_number'], -4)) : '—' ?>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($row['ifsc_code'] ?: '—') ?></td>
                                <td><?= $row['expense_count'] ?></td>
                                <td class="fw-bold">&#8377;<?= number_format($row['total_amount'], 2) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;"
                                        onsubmit="return confirm('Delete this source?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="source_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" <?= $row['expense_count'] > 0 ? 'disabled title="Has linked expenses"' : '' ?>>Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD SOURCE MODAL -->
<div id="addSourceModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <span>Add Expense Source</span>
            <button class="modal-close" onclick="closeModal('addSourceModal')">&#215;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Source Name *</label>
                    <input type="text" name="source_name" class="form-control"
                        placeholder="e.g. HDFC Savings, Petty Cash" required autofocus>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="HDFC Bank">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Number</label>
                        <input type="text" name="account_number" class="form-control" placeholder="50100XXXXXXXX">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">IFSC Code</label>
                    <input type="text" name="ifsc_code" class="form-control" placeholder="HDFC0001234"
                        style="text-transform:uppercase;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addSourceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Source</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>