<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — KRNC Ledger</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-wrapper {
            display: flex;
            width: 100%;
            max-width: 880px;
            min-height: 500px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.12);
        }

        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 60%, #7c3aed 100%);
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .brand-name {
            font-size: 24px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
        }

        .brand-name span {
            opacity: 0.65;
        }

        .hero-text h2 {
            font-size: 28px;
            font-weight: 800;
            color: #fff;
            line-height: 1.25;
            margin-bottom: 12px;
        }

        .hero-text p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            line-height: 1.6;
        }

        .feature-list {
            margin-top: 22px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 13px;
            margin-bottom: 10px;
        }

        .feature-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #fff;
            flex-shrink: 0;
        }

        .login-right {
            width: 380px;
            background: #ffffff;
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        h3 {
            font-size: 22px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .sub {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 10px 13px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #1e293b;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            border-radius: 8px;
            transition: all 0.2s;
            outline: none;
        }

        input:focus {
            border-color: #6366f1;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }

        .btn-submit {
            width: 100%;
            padding: 11px;
            background: #6366f1;
            border: none;
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 6px;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
            letter-spacing: 0.2px;
        }

        .btn-submit:hover {
            background: #4f46e5;
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        }

        .footer-link {
            margin-top: 20px;
            text-align: center;
            font-size: 13px;
            color: #64748b;
        }

        .footer-link a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 600;
        }

        .footer-link a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }

        @media (max-width: 640px) {
            .login-left {
                display: none;
            }

            .login-right {
                width: 100%;
                border-radius: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <div class="login-left">
            <div class="brand-name">KRNC<span>Ledger</span></div>
            <div>
                <div class="hero-text">
                    <h2>Manage expenses effortlessly</h2>
                    <p>Track, categorize, and report spending across all your bank accounts — all in one place.</p>
                </div>
                <div class="feature-list">
                    <div class="feature-item">
                        <div class="feature-dot">&#10003;</div> Multi-source expense tracking
                    </div>
                    <div class="feature-item">
                        <div class="feature-dot">&#10003;</div> Beautiful dashboard with charts
                    </div>
                    <div class="feature-item">
                        <div class="feature-dot">&#10003;</div> Export reports as PDF &amp; Excel
                    </div>
                </div>
            </div>
            <div style="font-size:12px; color:rgba(255,255,255,0.45);">&copy; 2026 KRNC Ledger</div>
        </div>

        <div class="login-right">
            <h3>Welcome back &#128075;</h3>
            <p class="sub">Sign in to your account</p>

            <?php
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div>';
                unset($_SESSION['error']);
            }
            if (isset($_SESSION['success'])) {
                echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
                unset($_SESSION['success']);
            }
            ?>

            <form action="actions/login_action.php" method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="you@example.com"
                        autocomplete="email">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                        placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" autocomplete="current-password">
                </div>
                <button type="submit" class="btn-submit">Sign In &rarr;</button>
            </form>

            <div class="footer-link">
                Don't have an account? <a href="register.php">Create one</a>
            </div>
        </div>
    </div>
</body>

</html>