<?php

namespace Frontender\Core\Routes\Helpers;

class Builder extends \Lcobucci\JWT\Builder {
    protected $_token;
    protected $claims;

    public function setToken($token) {
        foreach($token->getHeaders() as $name => $value) {
            $this->setHeader($name, $value);
        }

        foreach($token->getClaims() as $name => $value) {
            $this->setRegisteredClaim($name, $value, false);
        }
    }

    public function getClaim(string $name) {
        return $this->claims[$name];
    }
}