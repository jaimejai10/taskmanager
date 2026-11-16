
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$role = strtolower(trim($_SESSION['role'] ?? ''));

// Redirect destination based on logged-in account.
if ($role === 'mishead') {
    $dashboard = 'admin-dashboard.php';
} elseif ($role === 'misuser') {
    $dashboard = 'user-dashboard.php';
} else {
    $dashboard = '../login.php';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Access Denied | MIS Helpdesk</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f6fa;
            font-family: "Segoe UI", Arial, sans-serif;
            padding: 20px;
        }

        .access-card {
            width: 100%;
            max-width: 1000px;
            background: #fff;
            border: 2px solid #1674c5;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(16, 43, 82, .12);
            text-align: center;
        }

        .header {
            background: linear-gradient(110deg, #07529b, #0878ce);
            color: #fff;
            padding: 25px 35px;
            display: flex;
            align-items: center;
            gap: 20px;
            text-align: left;
        }

        .header-icon {
            font-size: 42px;
            border-right: 2px solid #ffffff80;
            padding-right: 20px;
        }

        .header h1 {
            font-size: 27px;
            font-weight: 700;
        }

        .content {
            padding: 55px 30px;
        }

        .lock-icon {
            width: 135px;
            height: 135px;
            border-radius: 50%;
            background: #e3f2ff;
            color: #0759a5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 75px;
            margin: 0 auto 25px;
        }

        .content h2 {
            color: #103e78;
            font-size: clamp(32px, 5vw, 55px);
            font-weight: 800;
            margin-bottom: 15px;
        }

        .role-label {
            display: inline-block;
            background: #e1f1ff;
            color: #0759a5;
            padding: 12px 35px;
            border-radius: 40px;
            font-size: 25px;
            font-weight: 700;
            margin-bottom: 25px;
        }

        .message {
            color: #475569;
            font-size: 17px;
            line-height: 1.7;
            margin-bottom: 35px;
        }

        .divider {
            height: 2px;
            background: #e2e8f0;
            margin: 0 auto 30px;
            max-width: 750px;
        }

        .back-button {
            display: inline-block;
            background: linear-gradient(110deg, #0759a5, #087bd2);
            color: #fff;
            text-decoration: none;
            padding: 16px 35px;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 700;
            transition: .2s;
        }

        .back-button:hover {
            background: #064681;
            transform: translateY(-2px);
        }

        @media(max-width: 600px) {
            .header {
                padding: 20px;
            }

            .header h1 {
                font-size: 19px;
            }

            .header-icon {
                font-size: 30px;
            }

            .content {
                padding: 40px 18px;
            }

            .role-label {
                font-size: 19px;
            }

            .message {
                font-size: 14px;
            }
        }
    </style>
</head>

<body>

<div class="access-card">

    <header class="header">
        <div class="header-icon">⚙</div>
        <h1>MIS Helpdesk System</h1>
    </header>

    <main class="content">

        <div class="lock-icon">🔒</div>

        <h2>ACCESS DENIED</h2>

        <div class="role-label">
            <span>👤</span> MIS Head Only
        </div>

        <p class="message">
            You do not have the required permission to access this page.
            <br>
            Please contact the MIS Administrator if you believe this is an error.
        </p>

        <div class="divider"></div>

        <a href="<?= htmlspecialchars($dashboard, ENT_QUOTES, 'UTF-8') ?>"
           class="back-button">
            🏠 &nbsp; Return to Dashboard
        </a>

    </main>

</div>

</body>
</html>
