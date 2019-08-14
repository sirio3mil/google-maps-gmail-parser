<?php

use App\Exception\AuthorizationNotFoundException;
use App\Service\GmailClientService;
use App\Service\GmailMessageService;

require dirname(__DIR__, 1) . '/vendor/autoload.php';

try {

    if (php_sapi_name() != 'cli') {
        throw new Exception('This application must be run on the command line.');
    }

    $assetsFolder = dirname(__DIR__, 1) . '/temp/body/';

    $gmailClient = new GmailClientService();

    $service = new GmailMessageService($gmailClient);

    $service->setUserId('me');

    $messages = $service->listMessages([
        'q' => '{from:noreply-local-guides@google.com from:noreply-maps-timeline@google.com from:google-maps-noreply@google.com}'
    ]);

    /** @var Google_Service_Gmail_Message $message */
    foreach ($messages as $message) {

        $subject = $dateTime = null;
        $messageId = $message->getId();

        $fullMessage = $service->getMessage($messageId);

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

            $html = GmailMessageService::decodeBody($body->getData());

            file_put_contents($assetsFolder . $messageId . ".html", $html);

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