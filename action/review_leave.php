<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function respond(bool $success, string $message, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(
        ['success' => $success, 'message' => $message],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

if (empty($_SESSION['user_id'])) {
    respond(false, 'Your session has expired. Please log in again.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.', 405);
}

require_once __DIR__ . '/../DB_connection.php';

if (!isset($conn) || !($conn instanceof PDO)) {
    respond(false, 'Database connection is unavailable.', 500);
}

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        respond(false, 'Invalid request data.', 400);
    }

    $csrf = (string)($data['csrf'] ?? '');
    if (
        empty($_SESSION['monitor_leave_csrf']) ||
        !hash_equals((string)$_SESSION['monitor_leave_csrf'], $csrf)
    ) {
        respond(false, 'Invalid security token. Refresh the page and try again.', 403);
    }

    $userId = (int)$_SESSION['user_id'];

    // Enforce MIS Head authorization using the role stored in the database.
    $roleStmt = $conn->prepare(
        "SELECT LOWER(TRIM(role)) FROM users WHERE user_id = ? LIMIT 1"
    );
    $roleStmt->execute([$userId]);
    $role = (string)$roleStmt->fetchColumn();

    if ($role !== 'mishead') {
        respond(false, 'Only the MIS Head can approve or reject leave requests.', 403);
    }

    $leaveId = filter_var($data['leave_id'] ?? null, FILTER_VALIDATE_INT);
    $action = strtolower(trim((string)($data['action'] ?? '')));
    $remarks = trim((string)($data['remarks'] ?? ''));

    if (!$leaveId || $leaveId < 1) {
        respond(false, 'Invalid leave request.', 400);
    }

    if (!in_array($action, ['approve', 'reject'], true)) {
        respond(false, 'Invalid leave action.', 400);
    }

    if ($action === 'reject' && $remarks === '') {
        respond(false, 'A rejection reason is required.', 422);
    }

    $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';

    $conn->beginTransaction();

    // Only leave requests submitted by MIS Associates can be reviewed here.
    $requestStmt = $conn->prepare("
        SELECT lr.leave_id, lr.status
        FROM leave_requests lr
        INNER JOIN users requester ON requester.user_id = lr.user_id
        WHERE lr.leave_id = ?
          AND LOWER(TRIM(requester.role)) = 'misuser'
        FOR UPDATE
    ");
    $requestStmt->execute([$leaveId]);
    $leave = $requestStmt->fetch();

    if (!$leave) {
        $conn->rollBack();
        respond(false, 'Leave request not found or not submitted by an MIS Associate.', 404);
    }

    if (strtolower(trim((string)$leave['status'])) !== 'pending') {
        $conn->rollBack();
        respond(false, 'This leave request has already been reviewed. Refresh the page.', 409);
    }

    $update = $conn->prepare("
        UPDATE leave_requests
        SET status = ?,
            reviewed_by = ?,
            reviewed_at = NOW(),
            review_remarks = ?
        WHERE leave_id = ?
          AND status = 'Pending'
    ");
    $update->execute([
        $newStatus,
        $userId,
        $remarks !== '' ? $remarks : null,
        $leaveId
    ]);

    if ($update->rowCount() !== 1) {
        $conn->rollBack();
        respond(false, 'The leave request could not be updated. Refresh and try again.', 409);
    }

    $conn->commit();

    respond(
        true,
        $action === 'approve'
            ? 'Leave request approved successfully.'
            : 'Leave request rejected successfully.'
    );
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Review leave request: ' . $e->getMessage());
    respond(false, 'A server error occurred while reviewing the leave request.', 500);
}
