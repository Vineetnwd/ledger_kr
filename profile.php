<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}
$page_title = 'My Profile — KRNC Expense Ledger';
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

// Fetch current user details from DB
$user_q = mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id");
$user = mysqli_fetch_assoc($user_q);

include 'includes/header.php';
?>

<div class="topbar">
    <div>
        <div class="topbar-title">My Profile</div>
        <div class="topbar-sub">Manage your account information and password settings</div>
    </div>
</div>

<div class="page-content">
    <?= $message ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">

        <!-- PROFILE CARD -->
        <div class="card">
            <div class="card-header"><span>Update Profile Details</span></div>
            <div class="card-body" style="padding:20px;">
                <form method="POST" action="<?= BASE_URL ?>/actions/profile_action.php">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-control"
                            value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                    </div>

                    <div class="form-group" style="margin-top:14px;">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-control"
                            value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                    </div>

                    <div class="form-group"
                        style="margin-top:14px; background:#f8fafc; padding:12px; border-radius:8px; border:1px dashed #cbd5e1;">
                        <span class="text-muted fs-11"
                            style="display:block; text-transform:uppercase; font-weight:700; letter-spacing:0.5px;">Account
                            Metadata</span>
                        <div style="font-size:12px; margin-top:4px;">
                            Member since: <strong
                                class="text-muted"><?= date('d M Y, h:i A', strtotime($user['created_at'])) ?></strong>
                        </div>
                    </div>

                    <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- PASSWORD CARD -->
        <div class="card">
            <div class="card-header"><span>Change Password</span></div>
            <div class="card-body" style="padding:20px;">
                <form method="POST" action="<?= BASE_URL ?>/actions/profile_action.php">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label class="form-label">Current Password *</label>
                        <input type="password" name="current_password" class="form-control" placeholder="••••••••"
                            required>
                    </div>

                    <div class="form-group" style="margin-top:14px;">
                        <label class="form-label">New Password *</label>
                        <input type="password" name="new_password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <div class="form-group" style="margin-top:14px;">
                        <label class="form-label">Confirm New Password *</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="••••••••"
                            required>
                    </div>

                    <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary"
                            style="background:#7c3aed; border-color:#7c3aed;">Update Password</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>