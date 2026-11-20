<?php
// Save as: C:\\xampp\\htdocs\\taskmanager\\account\\my-account.php
session_start();
date_default_timezone_set('Asia/Manila');

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../DB_connection.php';
if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    exit('DB_connection.php must provide a PDO connection named $conn.');
}
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$userId = (int)$_SESSION['user_id'];
if (empty($_SESSION['account_csrf'])) {
    $_SESSION['account_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['account_csrf'];
$success = '';
$error = '';

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
function isAdminRole(string $role): bool {
    return in_array(strtolower(trim($role)), ['admin', 'administrator', 'mishead'], true);
}
function normalizeDateField(string $value, string $label): ?string {
    $value = trim($value);
    if ($value === '') return null;
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new RuntimeException('Please enter a valid ' . $label . '.');
    }
    return $value;
}
function nullableText(string $value, int $maxLength, string $label): ?string {
    $value = trim($value);
    if ($value === '') return null;
    if (mb_strlen($value) > $maxLength) {
        throw new RuntimeException($label . ' is too long.');
    }
    return $value;
}

// Read the actual role from the database; do not trust a form or URL parameter.
$meStmt = $conn->prepare('SELECT user_id, employee_no, full_name, position, mobile_no, username, password, role, created_at, presence_status, date_of_birth, date_hired, blood_type, sss_no, pagibig_no, philhealth_no, tin_no, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, emergency_contact_address FROM users WHERE user_id = ? LIMIT 1');
$meStmt->execute([$userId]);
$me = $meStmt->fetch();
if (!$me) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}
$isAdmin = isAdminRole((string)$me['role']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Your session token is invalid. Refresh the page and try again.');
        }

        $action = (string)($_POST['action'] ?? '');

        if ($action === 'update_my_profile') {
            $employeeNo = trim((string)($_POST['employee_no'] ?? ''));
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $mobileNo = trim((string)($_POST['mobile_no'] ?? ''));
            $dob = normalizeDateField((string)($_POST['date_of_birth'] ?? ''), 'date of birth');
            $doh = normalizeDateField((string)($_POST['date_hired'] ?? ''), 'date hired');
            $bloodType = nullableText((string)($_POST['blood_type'] ?? ''), 5, 'Blood type');
            $sssNo = nullableText((string)($_POST['sss_no'] ?? ''), 30, 'SSS number');
            $pagibigNo = nullableText((string)($_POST['pagibig_no'] ?? ''), 30, 'Pag-IBIG number');
            $philhealthNo = nullableText((string)($_POST['philhealth_no'] ?? ''), 30, 'PhilHealth number');
            $tinNo = nullableText((string)($_POST['tin_no'] ?? ''), 30, 'TIN');
            $emergencyName = nullableText((string)($_POST['emergency_contact_name'] ?? ''), 150, 'Emergency contact name');
            $emergencyNumber = nullableText((string)($_POST['emergency_contact_number'] ?? ''), 25, 'Emergency contact number');
            $emergencyRelationship = nullableText((string)($_POST['emergency_contact_relationship'] ?? ''), 80, 'Emergency contact relationship');
            $emergencyAddress = nullableText((string)($_POST['emergency_contact_address'] ?? ''), 1000, 'Emergency contact address');

            if ($fullName === '') throw new RuntimeException('Full name is required.');
            if (mb_strlen($fullName) > 150) throw new RuntimeException('Full name is too long.');
            if (mb_strlen($employeeNo) > 50) throw new RuntimeException('Employee number is too long.');
            if ($mobileNo !== '' && !preg_match('/^[0-9+()\- ]{7,25}$/', $mobileNo)) {
                throw new RuntimeException('Please enter a valid mobile number.');
            }
            if ($emergencyNumber !== null && !preg_match('/^[0-9+()\- ]{7,25}$/', $emergencyNumber)) {
                throw new RuntimeException('Please enter a valid emergency contact number.');
            }
            if ($bloodType !== null && !in_array(strtoupper($bloodType), ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true)) {
                throw new RuntimeException('Please select a valid blood type.');
            }

            $stmt = $conn->prepare('UPDATE users SET employee_no = ?, full_name = ?, mobile_no = ?, date_of_birth = ?, date_hired = ?, blood_type = ?, sss_no = ?, pagibig_no = ?, philhealth_no = ?, tin_no = ?, emergency_contact_name = ?, emergency_contact_number = ?, emergency_contact_relationship = ?, emergency_contact_address = ? WHERE user_id = ?');
            $stmt->execute([$employeeNo !== '' ? $employeeNo : null, $fullName, $mobileNo !== '' ? $mobileNo : null, $dob, $doh, $bloodType !== null ? strtoupper($bloodType) : null, $sssNo, $pagibigNo, $philhealthNo, $tinNo, $emergencyName, $emergencyNumber, $emergencyRelationship, $emergencyAddress, $userId]);
            $_SESSION['full_name'] = $fullName;
            $_SESSION['name'] = $fullName;
            $success = 'Your profile has been updated.';
        }
        elseif ($action === 'change_password') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            if (!password_verify($currentPassword, (string)$me['password'])) {
                throw new RuntimeException('Your current password is incorrect.');
            }
            if (strlen($newPassword) < 8) throw new RuntimeException('New password must be at least 8 characters.');
            if ($newPassword !== $confirmPassword) throw new RuntimeException('New password and confirmation do not match.');

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
            $stmt->execute([$hash, $userId]);
            $success = 'Your password has been changed successfully.';
        }
        elseif ($action === 'admin_update_user') {
            if (!$isAdmin) throw new RuntimeException('You are not authorized to manage other accounts.');

            $targetId = filter_var($_POST['target_user_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$targetId || $targetId < 1) throw new RuntimeException('Invalid user account selected.');

            $employeeNo = trim((string)($_POST['employee_no'] ?? ''));
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $position = trim((string)($_POST['position'] ?? ''));
            $mobileNo = trim((string)($_POST['mobile_no'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $role = strtolower(trim((string)($_POST['role'] ?? '')));
            $dob = normalizeDateField((string)($_POST['date_of_birth'] ?? ''), 'date of birth');
            $doh = normalizeDateField((string)($_POST['date_hired'] ?? ''), 'date hired');
            $bloodType = nullableText((string)($_POST['blood_type'] ?? ''), 5, 'Blood type');
            $sssNo = nullableText((string)($_POST['sss_no'] ?? ''), 30, 'SSS number');
            $pagibigNo = nullableText((string)($_POST['pagibig_no'] ?? ''), 30, 'Pag-IBIG number');
            $philhealthNo = nullableText((string)($_POST['philhealth_no'] ?? ''), 30, 'PhilHealth number');
            $tinNo = nullableText((string)($_POST['tin_no'] ?? ''), 30, 'TIN');
            $emergencyName = nullableText((string)($_POST['emergency_contact_name'] ?? ''), 150, 'Emergency contact name');
            $emergencyNumber = nullableText((string)($_POST['emergency_contact_number'] ?? ''), 25, 'Emergency contact number');
            $emergencyRelationship = nullableText((string)($_POST['emergency_contact_relationship'] ?? ''), 80, 'Emergency contact relationship');
            $emergencyAddress = nullableText((string)($_POST['emergency_contact_address'] ?? ''), 1000, 'Emergency contact address');

            if ($fullName === '' || $username === '' || $role === '') throw new RuntimeException('Full name, username, and role are required.');
            if (mb_strlen($fullName) > 150 || mb_strlen($username) > 80 || mb_strlen($position) > 120 || mb_strlen($employeeNo) > 50) {
                throw new RuntimeException('One or more fields exceed the allowed length.');
            }
            if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) throw new RuntimeException('Username may contain letters, numbers, dots, underscores, and hyphens only.');
            if ($mobileNo !== '' && !preg_match('/^[0-9+()\- ]{7,25}$/', $mobileNo)) throw new RuntimeException('Please enter a valid mobile number.');
            if ($emergencyNumber !== null && !preg_match('/^[0-9+()\- ]{7,25}$/', $emergencyNumber)) throw new RuntimeException('Please enter a valid emergency contact number.');
            if ($bloodType !== null && !in_array(strtoupper($bloodType), ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true)) throw new RuntimeException('Please select a valid blood type.');

            // Only allow roles already used in the system; do not invent role values.
            $roleStmt = $conn->query("SELECT DISTINCT role FROM users WHERE role IS NOT NULL AND TRIM(role) <> ''");
            $existingRoles = array_map(static fn($r) => strtolower(trim((string)$r['role'])), $roleStmt->fetchAll());
            if (!in_array($role, $existingRoles, true)) throw new RuntimeException('Please select an existing system role.');

            $dupe = $conn->prepare('SELECT user_id FROM users WHERE username = ? AND user_id <> ? LIMIT 1');
            $dupe->execute([$username, $targetId]);
            if ($dupe->fetch()) throw new RuntimeException('That username is already in use.');

            $stmt = $conn->prepare('UPDATE users SET employee_no = ?, full_name = ?, position = ?, mobile_no = ?, username = ?, role = ?, date_of_birth = ?, date_hired = ?, blood_type = ?, sss_no = ?, pagibig_no = ?, philhealth_no = ?, tin_no = ?, emergency_contact_name = ?, emergency_contact_number = ?, emergency_contact_relationship = ?, emergency_contact_address = ? WHERE user_id = ?');
            $stmt->execute([$employeeNo !== '' ? $employeeNo : null, $fullName, $position !== '' ? $position : null, $mobileNo !== '' ? $mobileNo : null, $username, $role, $dob, $doh, $bloodType !== null ? strtoupper($bloodType) : null, $sssNo, $pagibigNo, $philhealthNo, $tinNo, $emergencyName, $emergencyNumber, $emergencyRelationship, $emergencyAddress, $targetId]);

            if ($targetId === $userId) {
                $_SESSION['full_name'] = $fullName;
                $_SESSION['name'] = $fullName;
                $_SESSION['role'] = $role;
            }
            $success = 'The user account has been updated.';
        }
        elseif ($action === 'admin_reset_password') {
            if (!$isAdmin) throw new RuntimeException('You are not authorized to reset other users’ passwords.');
            $targetId = filter_var($_POST['target_user_id'] ?? null, FILTER_VALIDATE_INT);
            $newPassword = (string)($_POST['admin_new_password'] ?? '');
            if (!$targetId || $targetId < 1) throw new RuntimeException('Invalid user account selected.');
            if (strlen($newPassword) < 8) throw new RuntimeException('Temporary password must be at least 8 characters.');
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $targetId]);
            if ($stmt->rowCount() === 0) {
                $check = $conn->prepare('SELECT user_id FROM users WHERE user_id = ?');
                $check->execute([$targetId]);
                if (!$check->fetch()) throw new RuntimeException('User account was not found.');
            }
            $success = 'Password reset successfully. Give the temporary password to the user securely.';
        }
        else {
            throw new RuntimeException('Unknown account action.');
        }

        // Reload current account after any update.
        $meStmt->execute([$userId]);
        $me = $meStmt->fetch();
        $isAdmin = isAdminRole((string)$me['role']);

    } catch (Throwable $e) {
        $error = $e instanceof PDOException ? 'Database error. Please check the database fields and try again.' : $e->getMessage();
    }
}

// Admin account list and selected account editor.
$users = [];
$editUser = null;
$existingRoles = [];
if ($isAdmin) {
    $users = $conn->query('SELECT user_id, employee_no, full_name, position, mobile_no, username, role, presence_status, created_at FROM users ORDER BY full_name')->fetchAll();
    $existingRoles = array_values(array_unique(array_map(static fn($u) => (string)$u['role'], $users)));
    $editId = filter_input(INPUT_GET, 'edit_user', FILTER_VALIDATE_INT);
    if ($editId) {
        $editStmt = $conn->prepare('SELECT user_id, employee_no, full_name, position, mobile_no, username, role, date_of_birth, date_hired, blood_type, sss_no, pagibig_no, philhealth_no, tin_no, emergency_contact_name, emergency_contact_number, emergency_contact_relationship, emergency_contact_address FROM users WHERE user_id = ? LIMIT 1');
        $editStmt->execute([$editId]);
        $editUser = $editStmt->fetch() ?: null;
    }
}

$userName = $me['full_name'] ?: 'MIS User';
$roleLabel = 'ACCOUNT PROFILE';
$layoutRole = $isAdmin ? 'admin' : 'user';
$currentPage = 'account';
$portalLabel = 'Centralized Account';
$navBase = '../MIS/';
$initial = strtoupper(mb_substr((string)$userName, 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Account | MIS Helpdesk</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f4f7fb;color:#17243b;font-family:Arial,sans-serif}.dashboard-content{padding:26px;max-width:1500px;margin:auto}.page-heading{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:22px}.page-heading h2{font-size:23px;font-weight:800;margin:0;color:#102f50}.page-heading p{margin:5px 0 0;color:#64748b;font-size:13px}.account-card{background:#fff;border:1px solid #e7edf5;border-radius:14px;box-shadow:0 4px 18px rgba(15,45,80,.04);margin-bottom:20px;overflow:hidden}.card-heading{padding:17px 20px;border-bottom:1px solid #edf1f6;display:flex;align-items:center;gap:10px}.card-heading h3{font-size:15px;font-weight:800;margin:0}.card-body{padding:20px}.profile-summary{display:flex;align-items:center;gap:15px}.profile-avatar{width:62px;height:62px;border-radius:50%;display:grid;place-items:center;background:#0879ed;color:#fff;font-size:25px;font-weight:800}.profile-summary h3{font-size:18px;font-weight:800;margin:0 0 4px}.profile-summary p{font-size:13px;color:#64748b;margin:0}.form-label{font-size:12px;font-weight:700;color:#42536b}.form-control,.form-select{font-size:13px;border-radius:8px;padding:10px 11px;border-color:#dce5ef}.form-control:disabled{background:#f5f7fa}.section-note{font-size:12px;color:#64748b}.table thead th{font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:#64748b;background:#f8fafc;white-space:nowrap}.table tbody td{font-size:13px;vertical-align:middle}.status-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:6px}.status-available{background:#22c55e}.status-busy{background:#facc15}.status-leave{background:#94a3b8}.account-actions{display:flex;gap:7px;flex-wrap:wrap}.btn{border-radius:8px;font-size:13px;font-weight:700}.role-pill{background:#edf4ff;color:#1d4ed8;border-radius:20px;padding:4px 9px;font-size:10px;font-weight:800}.empty-state{padding:30px;text-align:center;color:#64748b}
@media(max-width:700px){.dashboard-content{padding:15px 10px}.page-heading{align-items:flex-start;flex-direction:column}.card-body{padding:15px}}
</style>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="main-area">
<?php require __DIR__ . '/../includes/header.php'; ?>
<section class="dashboard-content">
    <div class="page-heading">
        <div><h2><i class="bi bi-person-circle me-2"></i>Account Management</h2><p>View and maintain your account information and security settings.</p></div>
        <a href="<?= $isAdmin ? '../MIS/admin-dashboard.php' : '../MIS/user-dashboard.php' ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to Dashboard</a>
    </div>

    <?php if ($success): ?><div class="alert alert-success py-2"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= h($error) ?></div><?php endif; ?>

    <div class="row g-3">
        <div class="col-xl-7">
            <section class="account-card">
                <div class="card-heading"><i class="bi bi-person-vcard text-primary"></i><h3>My Profile</h3></div>
                <div class="card-body">
                    <div class="profile-summary mb-4">
                        <div class="profile-avatar"><?= h($initial) ?></div>
                        <div><h3><?= h($me['full_name']) ?></h3><p><?= h($me['position'] ?: 'Position not set') ?></p></div>
                    </div>
                    <form method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="update_my_profile">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Employee Number</label><input class="form-control" name="employee_no" maxlength="50" value="<?= h($me['employee_no']) ?>" placeholder="Enter employee number"></div>
                            <div class="col-md-6"><label class="form-label">Full Name *</label><input class="form-control" name="full_name" maxlength="150" required value="<?= h($me['full_name']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Position</label><input class="form-control" value="<?= h($me['position']) ?>" disabled><div class="section-note mt-1">Contact your administrator or HR to change your position.</div></div>
                            <div class="col-md-6"><label class="form-label">Mobile Number</label><input class="form-control" name="mobile_no" maxlength="25" value="<?= h($me['mobile_no']) ?>" placeholder="09XXXXXXXXX"></div>
                            <div class="col-md-6"><label class="form-label">Username</label><input class="form-control" value="<?= h($me['username']) ?>" disabled></div>
                            <div class="col-12"><hr class="my-1"><h6 class="fw-bold text-primary mb-0">Personal Information</h6></div>
                            <div class="col-md-6"><label class="form-label">Date of Birth</label><input type="date" class="form-control" name="date_of_birth" value="<?= h($me['date_of_birth']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Date Hired</label><input type="date" class="form-control" name="date_hired" value="<?= h($me['date_hired']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Blood Type</label><select class="form-select" name="blood_type"><option value="">Select blood type</option><?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $blood): ?><option value="<?= h($blood) ?>" <?= strtoupper((string)$me['blood_type']) === $blood ? 'selected' : '' ?>><?= h($blood) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label">SSS Number</label><input class="form-control" name="sss_no" maxlength="30" value="<?= h($me['sss_no']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Pag-IBIG Number</label><input class="form-control" name="pagibig_no" maxlength="30" value="<?= h($me['pagibig_no']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">PhilHealth Number</label><input class="form-control" name="philhealth_no" maxlength="30" value="<?= h($me['philhealth_no']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">TIN</label><input class="form-control" name="tin_no" maxlength="30" value="<?= h($me['tin_no']) ?>"></div>
                            <div class="col-12"><hr class="my-1"><h6 class="fw-bold text-primary mb-0">In Case of Emergency</h6></div>
                            <div class="col-md-6"><label class="form-label">Contact Name</label><input class="form-control" name="emergency_contact_name" maxlength="150" value="<?= h($me['emergency_contact_name']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Contact Number</label><input class="form-control" name="emergency_contact_number" maxlength="25" value="<?= h($me['emergency_contact_number']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Relationship</label><input class="form-control" name="emergency_contact_relationship" maxlength="80" value="<?= h($me['emergency_contact_relationship']) ?>" placeholder="e.g. Spouse, Parent, Sibling"></div>
                            <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="emergency_contact_address" rows="2" maxlength="1000"><?= h($me['emergency_contact_address']) ?></textarea></div>
                        </div>
                        <div class="d-flex justify-content-end mt-4"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Save Profile</button></div>
                    </form>
                </div>
            </section>

            <section class="account-card">
                <div class="card-heading"><i class="bi bi-shield-lock text-primary"></i><h3>Change Password</h3></div>
                <div class="card-body">
                    <form method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="change_password">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" required autocomplete="current-password"></div>
                            <div class="col-md-6"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" minlength="8" required autocomplete="new-password"><div class="section-note mt-1">At least 8 characters.</div></div>
                            <div class="col-md-6"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" name="confirm_password" minlength="8" required autocomplete="new-password"></div>
                        </div>
                        <div class="d-flex justify-content-end mt-4"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-key me-1"></i> Change Password</button></div>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-xl-5">
            <section class="account-card">
                <div class="card-heading"><i class="bi bi-info-circle text-primary"></i><h3>Account Information</h3></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-secondary small">User ID</span><strong><?= (int)$me['user_id'] ?></strong></div>
                    <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-secondary small">Presence</span><strong><?= h($me['presence_status'] ?: 'Available') ?></strong></div>
                    <div class="d-flex justify-content-between py-2"><span class="text-secondary small">Account Created</span><strong><?= h($me['created_at'] ?: '—') ?></strong></div>
                    <p class="section-note mt-3 mb-0">Your role, username, and position are controlled by authorized administrators or HR.</p>
                </div>
            </section>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <section class="account-card mt-2">
        <div class="card-heading"><i class="bi bi-people text-primary"></i><h3>Manage User Accounts</h3><span class="badge text-bg-primary ms-auto"><?= count($users) ?> users</span></div>
        <div class="card-body">
            <p class="section-note">Administrator access: edit employee details, usernames, positions, and existing system roles. Password resets are available separately.</p>
            <?php if ($editUser): ?>
            <div class="border rounded-3 p-3 mb-4" id="edit-account">
                <div class="d-flex justify-content-between align-items-center mb-3"><strong><i class="bi bi-pencil-square me-1"></i> Edit Account: <?= h($editUser['full_name']) ?></strong><a class="btn btn-sm btn-outline-secondary" href="my-account.php">Close</a></div>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="admin_update_user">
                    <input type="hidden" name="target_user_id" value="<?= (int)$editUser['user_id'] ?>">
                    <div class="col-md-4"><label class="form-label">Employee Number</label><input class="form-control" name="employee_no" value="<?= h($editUser['employee_no']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Full Name *</label><input class="form-control" name="full_name" required value="<?= h($editUser['full_name']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Position</label><input class="form-control" name="position" value="<?= h($editUser['position']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Mobile Number</label><input class="form-control" name="mobile_no" value="<?= h($editUser['mobile_no']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Username *</label><input class="form-control" name="username" required value="<?= h($editUser['username']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Role (Backend)</label><select class="form-select" name="role" required><?php foreach ($existingRoles as $r): ?><option value="<?= h(strtolower($r)) ?>" <?= strtolower((string)$editUser['role']) === strtolower($r) ? 'selected' : '' ?>><?= h($r) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><hr class="my-1"><h6 class="fw-bold text-primary mb-0">Personal Information</h6></div>
                    <div class="col-md-4"><label class="form-label">Date of Birth</label><input type="date" class="form-control" name="date_of_birth" value="<?= h($editUser['date_of_birth']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Date Hired</label><input type="date" class="form-control" name="date_hired" value="<?= h($editUser['date_hired']) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Blood Type</label><select class="form-select" name="blood_type"><option value="">Select blood type</option><?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $blood): ?><option value="<?= h($blood) ?>" <?= strtoupper((string)$editUser['blood_type']) === $blood ? 'selected' : '' ?>><?= h($blood) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">SSS Number</label><input class="form-control" name="sss_no" maxlength="30" value="<?= h($editUser['sss_no']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Pag-IBIG Number</label><input class="form-control" name="pagibig_no" maxlength="30" value="<?= h($editUser['pagibig_no']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">PhilHealth Number</label><input class="form-control" name="philhealth_no" maxlength="30" value="<?= h($editUser['philhealth_no']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">TIN</label><input class="form-control" name="tin_no" maxlength="30" value="<?= h($editUser['tin_no']) ?>"></div>
                    <div class="col-12"><hr class="my-1"><h6 class="fw-bold text-primary mb-0">In Case of Emergency</h6></div>
                    <div class="col-md-6"><label class="form-label">Contact Name</label><input class="form-control" name="emergency_contact_name" maxlength="150" value="<?= h($editUser['emergency_contact_name']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Contact Number</label><input class="form-control" name="emergency_contact_number" maxlength="25" value="<?= h($editUser['emergency_contact_number']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Relationship</label><input class="form-control" name="emergency_contact_relationship" maxlength="80" value="<?= h($editUser['emergency_contact_relationship']) ?>"></div>
                    <div class="col-12"><label class="form-label">Emergency Contact Address</label><textarea class="form-control" name="emergency_contact_address" rows="2" maxlength="1000"><?= h($editUser['emergency_contact_address']) ?></textarea></div>
                    <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Save Account Changes</button></div>
                </form>
                <hr>
                <form method="post" class="row g-2 align-items-end" onsubmit="return confirm('Reset this user password? Make sure you share the temporary password securely.');">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="admin_reset_password">
                    <input type="hidden" name="target_user_id" value="<?= (int)$editUser['user_id'] ?>">
                    <div class="col-md-8"><label class="form-label">Set Temporary Password (minimum 8 characters)</label><input type="password" class="form-control" name="admin_new_password" minlength="8" required></div>
                    <div class="col-md-4"><button class="btn btn-outline-danger w-100" type="submit"><i class="bi bi-key me-1"></i> Reset Password</button></div>
                </form>
            </div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>User</th><th>Employee No.</th><th>Username</th><th>Position</th><th>Role</th><th>Presence</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): $presence = strtolower((string)($u['presence_status'] ?? '')); $presenceClass = $presence === 'busy' ? 'status-busy' : ($presence === 'on leave' ? 'status-leave' : 'status-available'); ?>
                        <tr>
                            <td><strong><?= h($u['full_name']) ?></strong><div class="small text-secondary">ID: <?= (int)$u['user_id'] ?></div></td>
                            <td><?= h($u['employee_no'] ?: '—') ?></td>
                            <td><?= h($u['username']) ?></td>
                            <td><?= h($u['position'] ?: '—') ?></td>
                            <td><span class="role-pill"><?= h($u['role']) ?></span></td>
                            <td><span class="status-dot <?= $presenceClass ?>"></span><?= h($u['presence_status'] ?: 'Available') ?></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="?edit_user=<?= (int)$u['user_id'] ?>#edit-account"><i class="bi bi-pencil"></i> Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$users): ?><tr><td colspan="7" class="empty-state">No user accounts found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <?php endif; ?>
</section>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
