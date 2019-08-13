<?php

use App\Exception\AuthorizationNotFoundException;
use App\Service\GmailClientService;

require dirname(__DIR__, 1) . '/vendor/autoload.php';

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
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

$gmailClient = new GmailClientService();

try {
    $service = $gmailClient->getService();

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
                switch ($header->getName()) {
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
            foreach ($parts as $part) {
                /** @var Google_Service_Gmail_MessagePartBody $body */
                if ($body = $part->getBody()) {
                    break;
                }
            }

            echo $body->getSize() . PHP_EOL;

        }
    }

} catch (AuthorizationNotFoundException $e) {
    $authUrl = $e->getMessage();
    printf("Open the following link in your browser:\n%s\n", $authUrl);
    print 'Enter verification code: ';
    $authCode = trim(fgets(STDIN));
    try {
        $gmailClient->setAccessTokenWithAuthCode($authCode);
    } catch (Exception $e) {
    }

} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}