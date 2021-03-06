<?php
/*******************************************************
 * @copyright 2017-2019 Dipity B.V., The Netherlands
 * @package Frontender
 * @subpackage Frontender Platform Core
 *
 * Frontender is a web application development platform consisting of a
 * desktop application (Frontender Desktop) and a web application which
 * consists of a client component (Frontender Platform) and a core
 * component (Frontender Platform Core).
 *
 * Frontender Desktop, Frontender Platform and Frontender Platform Core
 * may not be copied and/or distributed without the express
 * permission of Dipity B.V.
 *******************************************************/

namespace Frontender\Core\Routes\Helpers;

use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Token;

class Tokenize
{
    private $_secret;
    private $_signer;
    private $_headers;
    private static $instance;

    public function __construct()
    {
        $this->_signer = new Sha256();
        $this->_secret = getenv('FEP_TOKEN_SECRET');
    }

    public function parse(string $tokenString) : Builder
    {
        $token = (new Parser())->parse($tokenString);
        $isValid = $token->verify($this->_signer, $this->_secret);

        if (!$isValid) {
            throw new \Exception('Token is invalid');
        }

        if ($token->isExpired()) {
            throw new ExpiredException('Token is expired');
        }

        return $this->makeBuilder($token);
    }

    public function build($token) : Token
    {
        return $token->sign($this->_signer, $this->_secret)->getToken();
    }

    public function makeBuilder(Token $token)
    {
        $builder = new Builder();
        $builder->setToken($token);

        return $builder;
    }

    public static function getInstance() : Tokenize
    {
        if (!self::$instance) {
            self::$instance = new Tokenize();
        }

        return self::$instance;
    }
}

class ExpiredException extends \Exception
{

}