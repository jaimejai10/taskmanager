
<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$accessRole = $_SESSION['access_role'] ?? '';
$isAdmin = ($accessRole === 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MIS Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <div class="logo">🖥️</div>
        <div class="header-content">
            <h1>MIS Helpdesk</h1>
            <p>Dashboard</p>
        </div>
    </div>
</header>

<main class="container">

    <section class="intro">
        <h2>
            Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'], ENT_QUOTES, 'UTF-8') ?>!
        </h2>

        <p>
            Role:
            <strong>
                <?= htmlspecialchars($_SESSION['role'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </strong>
        </p>
    </section>

    <section class="section">
        <div class="section-body">
            <h3 class="section-title">
                <?= $isAdmin ? '🛡️ Management Access' : '🧰 Technician Access' ?>
            </h3>

            <?php if ($isAdmin): ?>
                <p>You are signed in with temporary administrative access as MIS Head.</p>
            <?php else: ?>
                <p>You are signed in as an IT Associate. Technician-specific functions can be added next.</p>
            <?php endif; ?>

            <p>
                <a href="index.php">📝 New Request</a>
            </p>

            <p>
                <a href="track-request.php">🔎 Track Request</a>
            </p>

            <form action="logout.php" method="POST">
                <button type="submit" class="primary-btn">🚪 Logout</button>
            </form>
        </div>
    </section>

</main>

</body>
</html>
