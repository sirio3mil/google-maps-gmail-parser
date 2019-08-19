<?php

use App\Exception\AuthorizationNotFoundException;
use App\Service\GmailClientService;
use App\Service\GmailMessageService;
use PhpMimeMailParser\Parser;

require dirname(__DIR__, 1) . '/vendor/autoload.php';

try {

    if (php_sapi_name() != 'cli') {
        throw new Exception('This application must be run on the command line.');
    }

    $assetsFolder = dirname(__DIR__, 1) . '/temp/body/';

    if (!is_dir($assetsFolder)) {
        if (!mkdir($assetsFolder)){
            throw new Exception("Assets folder {$assetsFolder} can not be created");
        }
    }

    $now = new DateTimeImmutable();

    $dateFilename = $assetsFolder . "date.txt";
    $dateTime = $now->sub(new DateInterval("P5Y"));

    if (file_exists($dateFilename)) {
        $timestamp = file_get_contents($dateFilename);
        $dateTime = new DateTimeImmutable('@' . $timestamp);
        if (!$dateTime) {
            throw new Exception("Invalid date time {$timestamp}");
        }
    }


    $gmailClient = new GmailClientService();
    $parser = new Parser();
    $service = new GmailMessageService($gmailClient);

    $service->setUserId('me');

    echo 'searching for messages greater than ', $dateTime->format(DATE_ISO8601), PHP_EOL;

    $messages = $service->listMessages([
        'q' => '{from:noreply-local-guides@google.com from:noreply-maps-timeline@google.com from:google-maps-noreply@google.com} after:' . $dateTime->getTimestamp()
    ]);

    echo count($messages), ' messages found', PHP_EOL;

    if ($messages) {

        $tidyConfig = [
            "output-xhtml" => true,
            "clean" => true
        ];

        $dumpFilename = $assetsFolder . "files.csv";
        $timeZone = new DateTimeZone('UTC');

        $fp = fopen($dumpFilename, 'a');

        /** @var Google_Service_Gmail_Message $message */
        foreach ($messages as $message) {

            $messageId = $message->getId();
            $filename = $assetsFolder . $messageId . ".html";
            $fullMessage = $service->getRawMessage($messageId);
            $parser->setText(GmailMessageService::decodeBody($fullMessage->getRaw()));

            $subject = trim(str_replace('🌍', '', $parser->getHeader('Subject')));

            $dateTime = new DateTimeImmutable($parser->getHeader('Date'));
            $dateTime->setTimezone($timeZone);

            $tidy = new tidy;
            $tidy->parseString($parser->getMessageBody('html'), $tidyConfig, 'utf8');
            $tidy->cleanRepair();

            $doc = new DOMDocument();
            @$doc->loadHTML($tidy);
            $doc->saveHTMLFile($filename);

            fputcsv($fp, [
                $messageId,
                $subject,
                $dateTime->format("Y-m-d H:i:s")
            ]);

            echo 'writing message id ', $messageId, ' on ', $dateTime->format(DATE_ISO8601), PHP_EOL;

        }

        fclose($fp);

    }

    file_put_contents($dateFilename, $now->getTimestamp());

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