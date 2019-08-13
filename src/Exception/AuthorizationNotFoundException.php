<?php


namespace App\Exception;

use Exception;

class AuthorizationNotFoundException extends Exception
{
    public function __construct($message = "")
    {
        parent::__construct($message);
    }
}
