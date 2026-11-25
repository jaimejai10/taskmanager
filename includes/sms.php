<?php
// Semaphore SMS helper.
// Credentials are loaded from config/semaphore.php and must remain private.

require_once __DIR__ . '/../config/semaphore.php';

/**
 * Send an SMS through Semaphore.
 *
 * @return string|false Raw API response on HTTP success; false on transport/API failure.
 */
function send_sms($number, $message)
{
    if (!defined('SEMAPHORE_API_KEY') || !defined('SEMAPHORE_SENDER')) {
        error_log('Semaphore SMS configuration is missing.');
        return false;
    }

    if (!function_exists('curl_init')) {
        error_log('Semaphore SMS failed: PHP cURL extension is not enabled.');
        return false;
    }

    $number = trim((string)$number);
    $message = trim((string)$message);

    if ($number === '' || $message === '') {
        error_log('Semaphore SMS skipped: mobile number or message is empty.');
        return false;
    }

    $ch = curl_init('https://api.semaphore.co/api/v4/messages');

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => http_build_query([
            'apikey' => SEMAPHORE_API_KEY,
            'number' => $number,
            'message' => $message,
            'sendername' => SEMAPHORE_SENDER,
        ]),
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        error_log('Semaphore SMS cURL error: ' . $curlError);
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('Semaphore SMS HTTP error (' . $httpCode . '): ' . substr((string)$response, 0, 1000));
        return false;
    }

    return (string)$response;
}
?>
