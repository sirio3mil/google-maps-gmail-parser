<?php

use App\Exception\AuthorizationNotFoundException;
use App\Service\GmailClientService;
use App\Service\GmailMessageService;
use ImdbScraper\Helper\Cleaner;
use PhpMimeMailParser\Parser;

require dirname(__DIR__, 1) . '/vendor/autoload.php';

try {

    if (php_sapi_name() != 'cli') {
        throw new Exception('This application must be run on the command line.');
    }

    $assetsFolder = dirname(__DIR__, 1) . '/temp/body/';

    $gmailClient = new GmailClientService();
    $parser = new Parser();
    $service = new GmailMessageService($gmailClient);

    $service->setUserId('me');

    $messages = $service->listMessages([
        'q' => '{from:noreply-local-guides@google.com from:noreply-maps-timeline@google.com from:google-maps-noreply@google.com}'
    ]);

    /** @var Google_Service_Gmail_Message $message */
    foreach ($messages as $message) {
        $subject = $dateTime = null;
        $messageId = $message->getId();
        $fullMessage = $service->getRawMessage($messageId);
        $parser->setText(GmailMessageService::decodeBody($fullMessage->getRaw()));
        $html = Cleaner::clearText($parser->getMessageBody('html'));
        $subject = $parser->getHeader('Subject');
        $dateTime = new DateTimeImmutable($parser->getHeader('Date'));
        file_put_contents($assetsFolder . $messageId . ".html", $html);
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