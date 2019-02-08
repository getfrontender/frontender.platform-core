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

class Builder extends \Lcobucci\JWT\Builder {
    protected $_token;

    public function setToken($token) {
        $this->_token = $token;

        foreach($token->getHeaders() as $name => $value) {
            $this->setHeader($name, $value);
        }

        foreach($token->getClaims() as $name => $value) {
            $this->setRegisteredClaim($name, $value, false);
        }
    }

    public function getClaim(string $name) {
        if(!$this->_token->hasClaim($name)) {
            return false;
        }

        return $this->_token->getClaim($name);
    }
}