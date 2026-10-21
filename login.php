<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Login | MIS Request</title><link rel="stylesheet" href="css/style.css"></head>
<body>
<header class="topbar"><div class="topbar-inner"><div class="logo">🖥️</div><div class="header-content"><h1>MIS Request</h1><p>IT Helpdesk System</p></div><div class="nav-menu">
        <button class="nav-toggle" type="button" aria-label="Open navigation menu"
                aria-expanded="false" aria-controls="main-navigation">
            <span></span><span></span><span></span>
        </button>
        <nav class="nav-dropdown" id="main-navigation">
            <a href="index.php">📝 New Request</a>
            <a href="track-request.php">🔎 Track Request</a>
            <a href="login.php">🔐 Login</a>
        </nav>
    </div></div></header>
<main class="container">
<div class="login-wrap"><section class="login-card">
<div class="login-icon">🔐</div><h2>Login to MIS Request</h2><p>Enter your credentials to access the system.</p>
<form onsubmit="event.preventDefault(); alert('Login authentication will be connected in a later step.');">
<div class="field"><label for="username">Username</label><input type="text" id="username" name="username" placeholder="Enter username" autocomplete="username" required></div>
<div class="field"><label for="password">Password</label><input type="password" id="password" name="password" placeholder="Enter password" autocomplete="current-password" required></div>
<button class="submit-btn" type="submit">Login</button>
</form>
<p class="login-note">Authorized MIS users only.</p>
</section></div>
<footer class="footer">MIS IT Helpdesk · For technical assistance, contact the MIS team.</footer>
</main>
<script>
(function () {
    const menu = document.querySelector('.nav-menu');
    if (!menu) return;
    const toggle = menu.querySelector('.nav-toggle');
    const dropdown = menu.querySelector('.nav-dropdown');

    toggle.addEventListener('click', function () {
        const isOpen = dropdown.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
    });

    document.addEventListener('click', function (event) {
        if (!menu.contains(event.target)) {
            dropdown.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open navigation menu');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            dropdown.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open navigation menu');
        }
    });
})();
</script>

</body></html>
