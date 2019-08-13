<?php


namespace App\Service;

use App\Exception\AuthorizationNotFoundException;
use Exception;
use Google_Client;
use Google_Exception;
use Google_Service_Gmail;
use function file_exists;
use function json_decode;
use function json_encode;
use function file_get_contents;
use function file_put_contents;
use function dirname;
use function mkdir;


class GmailClientService
{

    /** @var Google_Client */
    private $client;
    private $tokenPath;
    private $credentialsPath;
    private $dirname;

    public function __construct()
    {
        $this->dirname = dirname(__DIR__, 2);
        $this->tokenPath = $this->dirname . '/config/token.json';
        $this->credentialsPath = $this->dirname . '/config/credentials.json';
    }

    /**
     * @throws Google_Exception
     * @throws AuthorizationNotFoundException
     * @throws Exception
     */
    public function setClient(): void
    {
        $this->client = new Google_Client();
        $this->client->setApplicationName('Gmail API PHP Quickstart');
        $this->client->setScopes(Google_Service_Gmail::GMAIL_READONLY);
        $this->client->setAuthConfig($this->credentialsPath);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');

        $this->setAccessToken();

    }

    /**
     * @return Google_Client
     * @throws Google_Exception
     * @throws AuthorizationNotFoundException
     */
    public function getClient(): Google_Client
    {
        if (is_null($this->client)) {
            $this->setClient();
        }
        return $this->client;
    }

    /**
     * @return Google_Service_Gmail
     * @throws Google_Exception
     * @throws AuthorizationNotFoundException
     */
    public function getService(): Google_Service_Gmail
    {
        return new Google_Service_Gmail($this->getClient());
    }

    /**
     * @throws AuthorizationNotFoundException
     * @throws Exception
     */
    protected function setAccessToken(): void
    {
        if (file_exists($this->tokenPath)) {
            $accessToken = json_decode(file_get_contents($this->tokenPath), true);
            $this->client->setAccessToken($accessToken);
        }
        // If there is no previous token or it's expired.
        if ($this->client->isAccessTokenExpired()) {
            $this->refreshAccessToken();
        }
    }

    /**
     * @throws AuthorizationNotFoundException
     */
    protected function refreshAccessToken(): void
    {
        // Refresh the token if possible, else fetch a new one.
        if (!$this->client->getRefreshToken()) {
            throw new AuthorizationNotFoundException($this->client->createAuthUrl());
        }
        $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
        $this->saveAccessToken();
    }

    /**
     * @param string $authCode
     * @throws Exception
     */
    public function setAccessTokenWithAuthCode(string $authCode): void
    {
        // Exchange authorization code for an access token.
        $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
        $this->client->setAccessToken($accessToken);

        // Check to see if there was an error.
        if (array_key_exists('error', $accessToken)) {
            throw new Exception(join(', ', $accessToken));
        }

        $this->saveAccessToken();
    }

    protected function saveAccessToken(): void
    {
        // Save the token to a file.
        if (!file_exists(dirname($this->tokenPath))) {
            mkdir(dirname($this->tokenPath), 0700, true);
        }
        file_put_contents($this->tokenPath, json_encode($this->client->getAccessToken()));
    }
}
