<?php
/**
 * MIS Helpdesk - Daily Presence Reset
 *
 * Purpose:
 *   Reset MIS users to Away at 12:00 AM (Asia/Manila).
 *   Preserve users who are On Leave and users with an approved leave
 *   covering the current date.
 *
 * Run from Windows Task Scheduler using:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\biometric\reset_presence.php
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../DB_connection.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    if (!isset($conn) || !$conn instanceof PDO) {
        throw new RuntimeException('Database connection ($conn) is not available.');
    }

    $today = date('Y-m-d');

    $sql = "
        UPDATE users u
        SET u.presence_status = 'Away'
        WHERE LOWER(TRIM(COALESCE(u.role, ''))) = 'misuser'
          AND LOWER(TRIM(COALESCE(u.presence_status, ''))) <> 'on leave'
          AND NOT EXISTS (
              SELECT 1
              FROM leave_requests lr
              WHERE lr.user_id = u.user_id
                AND LOWER(TRIM(COALESCE(lr.status, ''))) = 'approved'
                AND lr.start_date <= :today
                AND lr.end_date >= :today
          )
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':today' => $today]);

    $affected = $stmt->rowCount();

    echo "Presence reset completed successfully.\n";
    echo "Date: {$today}\n";
    echo "Timezone: Asia/Manila\n";
    echo "Users reset to Away: {$affected}\n";
    echo "Scope: MIS users only; On Leave and currently approved leave are preserved.\n";

} catch (Throwable $e) {
    http_response_code(500);
    error_log('[MIS Presence Reset] ' . $e->getMessage());
    echo "Presence reset failed. Check the PHP error log.\n";
}
