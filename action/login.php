
<?php
session_start();
require_once __DIR__ . "/../DB_connection.php";

function loginFailed(string $message = "Invalid username or password."): void
{
    $_SESSION['login_error'] = $message;
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.php");
    exit;
}

$token = $_POST['csrf_token'] ?? '';

if (
    !is_string($token) ||
    empty($_SESSION['login_csrf']) ||
    !hash_equals($_SESSION['login_csrf'], $token)
) {
    loginFailed("Your session expired. Please try again.");
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (
    $username === '' ||
    $password === '' ||
    strlen($username) > 100
) {
    loginFailed();
}

try {
    $sql = "
        SELECT user_id, username, password, role, full_name
        FROM users
        WHERE username = :username
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute(['username' => $username]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Passwords must be stored using password_hash().
    if (!$user || !password_verify($password, $user['password'])) {
        loginFailed();
    }

    $role = strtolower(trim($user['role'] ?? ''));

    if ($role === 'mishead') {
        $accessRole = 'admin';
    } elseif ($role === 'misuser') {
        $accessRole = 'technician';
    } else {
        loginFailed("This account does not have an authorized system role.");
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $role;
    $_SESSION['access_role'] = $accessRole;

    unset($_SESSION['login_csrf'], $_SESSION['login_error']);

    header("Location: ../dashboard.php");
    exit;

} catch (PDOException $e) {
    error_log("MIS login error: " . $e->getMessage());
    loginFailed("Unable to sign in right now. Please try again later.");
}
