<?php
require dirname(__DIR__, 1) . '/vendor/autoload.php';

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 * @throws Google_Exception
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Gmail API PHP Quickstart');
    $client->setScopes(Google_Service_Gmail::GMAIL_READONLY);
    $client->setAuthConfig('../config/credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = 'token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

/**
 * Get list of Messages in user's mailbox.
 *
 * @param Google_Service_Gmail $service
 * @param string $userId
 * @param $optParams
 * @return Google_Service_Gmail_Message[]
 */
function listMessages(Google_Service_Gmail $service, string $userId, array $optParams): array
{
    $pageToken = NULL;
    $messages = [];
    do {
        if ($pageToken) {
            $optParams['pageToken'] = $pageToken;
        }
        $messagesResponse = $service->users_messages->listUsersMessages($userId, $optParams);
        if ($messagesResponse->getMessages()) {
            $messages = array_merge($messages, $messagesResponse->getMessages());
            $pageToken = $messagesResponse->getNextPageToken();
        }
    } while ($pageToken);

    return $messages;
}

/**
 * Get Message with given ID.
 *
 * @param Google_Service_Gmail $service
 * @param string $userId
 * @param string $messageId
 * @return Google_Service_Gmail_Message
 */
function getMessage(Google_Service_Gmail $service, string $userId, string $messageId): Google_Service_Gmail_Message
{
    $message = $service->users_messages->get($userId, $messageId);
    return $message;
}


// Get the API client and construct the service object.
try {
    $client = getClient();
} catch (Google_Exception $e) {
}
$service = new Google_Service_Gmail($client);

// Print the labels in the user's account.
$user = 'me';

$messages = listMessages($service, $user, [
    'q' => '{from:noreply-local-guides@google.com from:noreply-maps-timeline@google.com from:google-maps-noreply@google.com}'
]);

echo sizeof($messages) . PHP_EOL;

/** @var Google_Service_Gmail_Message $message */
foreach ($messages as $message) {

    $subject = $dateTime = null;
    $messageId = $message->getId();

    $fullMessage = getMessage($service, $user, $messageId);

    if ($payload = $fullMessage->getPayload()) {
        $headers = $payload->getHeaders();
        /** @var Google_Service_Gmail_MessagePartHeader $header */
        foreach ($headers as $header) {
            switch ($header->getName()){
                case 'Subject':
                    $subject = $header->getValue();
                    break;
                case 'Date':
                    $dateTime = new DateTimeImmutable($header->getValue());
                    break;
            }
        }

        $parts = $payload->getParts();
        /** @var Google_Service_Gmail_MessagePart $part */
        foreach ($parts as $part){
            /** @var Google_Service_Gmail_MessagePartBody $body */
            if ($body = $part->getBody()){
                break;
            }
        }

        echo $body->getSize() . PHP_EOL;

    }
}