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
    <title>Create Account — KRNC Ledger</title>
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

        .register-wrapper {
            display: flex;
            width: 100%;
            max-width: 880px;
            min-height: 540px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.12);
        }

        .register-left {
            flex: 1;
            background: linear-gradient(135deg, #10b981 0%, #059669 60%, #0d9488 100%);
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

        .register-right {
            width: 400px;
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
            margin-bottom: 26px;
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
            border-color: #10b981;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
        }

        .btn-submit {
            width: 100%;
            padding: 11px;
            background: #10b981;
            border: none;
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 6px;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .btn-submit:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
        }

        .footer-link {
            margin-top: 20px;
            text-align: center;
            font-size: 13px;
            color: #64748b;
        }

        .footer-link a {
            color: #10b981;
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

        @media (max-width: 640px) {
            .register-left {
                display: none;
            }

            .register-right {
                width: 100%;
                border-radius: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="register-wrapper">
        <div class="register-left">
            <div class="brand-name">KRNC<span> Ledger</span></div>
            <div>
                <div class="hero-text">
                    <h2>Start tracking finances today &#128640;</h2>
                    <p>Create your free account and take full control of your expenses with beautiful reports and
                        powerful tracking.</p>
                </div>
            </div>
            <div style="font-size:12px; color:rgba(255,255,255,0.45);">&copy; 2025 KRNC Ledger</div>
        </div>

        <div class="register-right">
            <h3>Create Account</h3>
            <p class="sub">Get started — it's completely free</p>

            <?php
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div>';
                unset($_SESSION['error']);
            }
            ?>

            <form action="actions/register_action.php" method="POST">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required placeholder="John Doe" autocomplete="name">
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="you@example.com"
                        autocomplete="email">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Min. 6 characters"
                        autocomplete="new-password">
                </div>
                <button type="submit" class="btn-submit">Create Account &rarr;</button>
            </form>

            <div class="footer-link">
                Already have an account? <a href="login.php">Sign In</a>
            </div>
        </div>
    </div>
</body>

</html>