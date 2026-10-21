<?php
include_once __DIR__ . '/../config/semaphore.php';

function send_sms($number, $message){

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.semaphore.co/api/v4/messages");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        "apikey" => SEMAPHORE_API_KEY,
        "number" => $number,
        "message" => $message,
        "sendername" => SEMAPHORE_SENDER
    ]));

    $response = curl_exec($ch);

    // 🔥 DEBUG (temporary only)
    if (curl_errno($ch)) {
        error_log("SMS CURL ERROR: " . curl_error($ch));
    } else {
        error_log("SMS RESPONSE: " . $response);
    }

    curl_close($ch);

    return $response;
}
?>