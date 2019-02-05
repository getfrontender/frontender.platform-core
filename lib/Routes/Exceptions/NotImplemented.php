<?php

namespace Frontender\Core\Routes\Exceptions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\SlimException;

class NotImplemented extends SlimException
{
    public function __construct(ServerRequestInterface $request, ResponseInterface $response)
    {
        $response = $response->withStatus(501);
        parent::__construct($request, $response);
    }
}