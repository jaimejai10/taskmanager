
<?php
session_start();

if (!empty($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | MIS Helpdesk</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .login-container {
            max-width: 440px;
            margin: 55px auto;
        }

        .login-card {
            background: #fff;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(0,0,0,.06);
        }

        .login-heading {
            text-align: center;
            margin-bottom: 25px;
        }

        .login-icon {
            font-size: 42px;
            margin-bottom: 10px;
        }

        .login-heading h2 {
            margin-bottom: 8px;
        }

        .login-heading p {
            color: #6b7280;
            font-size: 14px;
        }

        .login-card .field {
            margin-bottom: 18px;
        }

        .login-card input {
            width: 100%;
            box-sizing: border-box;
        }

        .login-btn {
            width: 100%;
            cursor: pointer;
        }

        .login-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <div class="logo">🖥️</div>
        <div class="header-content">
            <h1>MIS Request</h1>
            <p>IT Helpdesk Login</p>
        </div>
    </div>
</header>

<main class="container login-container">
    <section class="login-card">

        <div class="login-heading">
            <div class="login-icon">🔐</div>
            <h2>Welcome Back</h2>
            <p>Sign in to access the MIS Helpdesk system.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="login-error" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form action="action/login.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES, 'UTF-8') ?>">

            <div class="field">
                <label for="username">Username</label>
                <input
                    type="text"
                    name="username"
                    id="username"
                    placeholder="Enter your username"
                    maxlength="100"
                    autocomplete="username"
                    required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input
                    type="password"
                    name="password"
                    id="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required>
            </div>

            <button type="submit" class="primary-btn login-btn">
                🔓 Sign In
            </button>

        </form>

        <div class="back-link">
            <a href="index.php">← Back to New Request</a>
        </div>

    </section>

    <footer class="footer">
        MIS IT Helpdesk · Authorized users only.
    </footer>
</main>

</body>
</html>
