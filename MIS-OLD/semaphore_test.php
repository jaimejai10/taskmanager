<?php

$apiKey = "95fb7617a1881e187323558ae0d5dfd9";

$number = "09072943742"; // Receiver number
$message = "Hello! Semaphore SMS test successful.";

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "https://api.semaphore.co/api/v4/messages");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$data = [
    'apikey' => $apiKey,
    'number' => $number,
    'message' => $message,
];

curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

$response = curl_exec($ch);

if ($response === false) {
    echo "CURL ERROR: " . curl_error($ch);
} else {
    echo "<pre>";
    print_r($response);
    echo "</pre>";
}

curl_close($ch);

?>