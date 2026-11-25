
<?php
/**
 * MIS Helpdesk - Daily Presence Reset
 * Runs once on the first database request each Manila day.
 */

if (!isset($conn) || !($conn instanceof PDO)) {
    return;
}

try {
    $timezone = new DateTimeZone('Asia/Manila');
    $today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');

    $markerPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'mishelpdesk_presence_reset_' . $today . '.lock';

    $lockHandle = @fopen($markerPath, 'c+');

    if ($lockHandle === false) {
        error_log('MIS presence reset: Unable to open daily lock file.');
        return;
    }

    try {
        if (!flock($lockHandle, LOCK_EX)) {
            return;
        }

        rewind($lockHandle);
        $alreadyRun = trim((string) stream_get_contents($lockHandle));

        if ($alreadyRun === $today) {
            return;
        }

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

        rewind($lockHandle);
        ftruncate($lockHandle, 0);
        fwrite($lockHandle, $today);
        fflush($lockHandle);

        error_log('MIS daily presence reset completed for ' . $today);

    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }

} catch (Throwable $e) {
    error_log('MIS daily presence reset failed: ' . $e->getMessage());
}
