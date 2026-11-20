<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../DB_connection.php';

try {

    // Verify logged-in user
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Please log in again.'
        ]);
        exit;
    }

    // Read JSON request
    $data = json_decode(file_get_contents('php://input'), true);

    $status = $data['status'] ?? '';
    $csrf = $data['csrf'] ?? '';

    // Verify CSRF token
    if (
        empty($_SESSION['presence_csrf']) ||
        !hash_equals($_SESSION['presence_csrf'], $csrf)
    ) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid security token.'
        ]);
        exit;
    }

    // Allowed statuses only
    $allowedStatuses = [
        'Available',
        'Busy',
        'On Leave'
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid availability status.'
        ]);
        exit;
    }

    // Update ONLY the logged-in user's record
    $stmt = $conn->prepare("
        UPDATE users
        SET presence_status = ?
        WHERE user_id = ?
    ");

    $stmt->execute([
        $status,
        $_SESSION['user_id']
    ]);

    echo json_encode([
        'success' => true,
        'status' => $status
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database update failed.'
    ]);

}