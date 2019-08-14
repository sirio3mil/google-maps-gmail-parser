<?php


namespace App\Service;

use App\Exception\AuthorizationNotFoundException;
use Google_Exception;
use Google_Service_Gmail_Message;
use function base64_decode;
use function strtr;

class GmailMessageService
{
    /**
     * @var GmailClientService
     */
    private $service;

    /**
     * @var string
     */
    private $userId;

    /**
     * GmailMessageService constructor.
     * @param GmailClientService $gmailClientService
     * @throws AuthorizationNotFoundException
     * @throws Google_Exception
     */
    public function __construct(GmailClientService $gmailClientService)
    {
        $this->service = $gmailClientService->getService();
    }

    /**
     * @param $optParams
     * @return Google_Service_Gmail_Message[]
     */
    public function listMessages(array $optParams): array
    {
        $pageToken = NULL;
        $messages = [];
        do {
            if ($pageToken) {
                $optParams['pageToken'] = $pageToken;
            }
            $messagesResponse = $this->service->users_messages->listUsersMessages($this->userId, $optParams);
            if ($messagesResponse->getMessages()) {
                $messages = array_merge($messages, $messagesResponse->getMessages());
                $pageToken = $messagesResponse->getNextPageToken();
            }
        } while ($pageToken);

        return $messages;
    }

    /**
     * @param string $messageId
     * @return Google_Service_Gmail_Message
     */
    public function getMessage(string $messageId): Google_Service_Gmail_Message
    {
        $message = $this->service->users_messages->get($this->userId, $messageId);
        return $message;
    }

    /**
     * @param string $messageId
     * @return Google_Service_Gmail_Message
     */
    public function getRawMessage(string $messageId): Google_Service_Gmail_Message
    {
        $message = $this->service->users_messages->get($this->userId, $messageId, [
            'format' => 'raw'
        ]);
        return $message;
    }

    /**
     * @return string
     */
    public function getUserId(): string
    {
        return $this->userId;
    }

    /**
     * @param string $userId
     */
    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * @param $body
     * @return null|string
     */
    public static function decodeBody($body): ?string
    {
        $rawData = $body;
        $sanitizedData = strtr($rawData, '-_', '+/');
        $decodedMessage = base64_decode($sanitizedData);
        if (!$decodedMessage) {
            $decodedMessage = null;
        }
        return $decodedMessage;
    }
}
