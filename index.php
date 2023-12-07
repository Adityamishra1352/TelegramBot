<?php
$botToken = '6560073983:AAHNyS5KXU85h_F9guWKi-QgcmeYwQHL0Z4';
$apiUrl = 'https://api.telegram.org/bot' . $botToken;

function apiRequest($method, $params)
{
    $ch = curl_init($GLOBALS['apiUrl'] . '/' . $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
}

function getUpdates($offset)
{
    $params = [
        'offset' => $offset + 1,
        'timeout' => 30,
    ];

    return apiRequest('getUpdates', $params);
}

$lastUpdateId = 0;

while (true) {
    $updates = getUpdates($lastUpdateId);

    foreach ($updates['result'] as $update) {
        $chatID = $update['message']['chat']['id'];

        if (isset($update['message']['photo'])) {
            $photo = end($update['message']['photo']);
            $photoID = $photo['file_id'];
            $loadingMessageID = apiRequest('sendMessage', [
                'chat_id' => $chatID,
                'text' => 'Generating photo link. Please wait...',
            ])['result']['message_id'];
            $fileInfo = apiRequest('getFile', ['file_id' => $photoID]);

            if ($fileInfo['ok']) {
                $filePath = $fileInfo['result']['file_path'];
                $photoLink = 'https://api.telegram.org/file/bot' . $botToken . '/' . $filePath;
                apiRequest('editMessageText', [
                    'chat_id' => $chatID,
                    'message_id' => $loadingMessageID,
                    'text' => 'Photo link: ' . $photoLink,
                ]);
            } else {
                apiRequest('editMessageText', [
                    'chat_id' => $chatID,
                    'message_id' => $loadingMessageID,
                    'text' => 'Error generating photo link.',
                ]);
            }
        } else {
            $messageText = $update['message']['text'];

            if ($messageText == '/start') {
                $response = 'Hello! This is your Telegram bot.';
            } else {
                $response = $messageText;
            }

            apiRequest('sendMessage', [
                'chat_id' => $chatID,
                'text' => $response,
            ]);
        }

        $lastUpdateId = $update['update_id'];
    }

    sleep(1);
}
?>