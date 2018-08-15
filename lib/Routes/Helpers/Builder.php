<?php

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