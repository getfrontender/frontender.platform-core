<?php

namespace Frontender\Core\Routes\Helpers;

use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Token;

class Tokenize {
    private $_secret;
    private $_signer;
    private $_headers;
    private static $instance;

    public function __construct() {
        $this->_signer = new Sha256();
        $this->_secret = getenv('TOKEN_SECRET');
    }

    public function parse(string $tokenString): Builder {
        $token = (new Parser())->parse($tokenString);
        $isValid = $token->verify($this->_signer, $this->_secret);

        if(!$isValid) {
            throw new \Exception('Token is invalid');
        }

        if($token->isExpired()) {
            throw new \Exception('Token is expired');
        }

        return $this->makeBuilder($token);
    }

    public function build($token): Token {
        return $token->sign($this->_signer, $this->_secret)->getToken();
    }

    public function makeBuilder(Token $token) {
        $builder = new Builder();
        $builder->setToken($token);

        return $builder;
    }

    public static function getInstance(): Tokenize {
        if(!self::$instance) {
            self::$instance = new Tokenize();
        }

        return self::$instance;
    }
}