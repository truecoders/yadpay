<?php 
namespace YandexMoney\Exceptions;

class YandexAPIException extends \Exception {

}
class YandexFormatError extends YandexAPIException {
    public function __construct() {
        parent::__construct(
            "Request is missformated", 400
        );
    }
}

class YandexScopeError extends YandexAPIException {
    public function __construct() {
        parent::__construct(
            "Scope error. Obtain new access_token from user"
            . "with extended scope", 403
        );
    }
}

class YandexTokenError extends YandexAPIException {
    public function __construct() {
        parent::__construct("Token is expired or incorrect", 401);
    }
}

class YandexServerError extends YandexAPIException {
    public function __construct($status_code) {
        parent::__construct("Server error", $status_code);
    }
}
