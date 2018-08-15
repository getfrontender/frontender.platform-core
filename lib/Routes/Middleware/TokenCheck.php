<?php

namespace Frontender\Core\Routes\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;
use Frontender\Core\Routes\Helpers\Tokenize;

class TokenCheck
{
    private $_container;
    private $_headerName;

	public function __construct($container)
	{
        $this->_container = $container;
        $this->_headerName = getenv('TOKEN_HEADER');
	}

    public function __invoke(Request $request, Response $response, $next) {
        $header = $request->getHeader($this->_headerName)[0];
        $token = Tokenize::getInstance()->parse($header);

        $this->_container['token'] = $token;
        
        // Check if our site is in the token. If not we have an issue.
        if(in_array($request->getUri()->getHost(), $token->getClaim('domains')) === false) {
            return $response
                ->withStatus(400)
                ->withJson([
                    'error' => 'Domain not supported'
                ]);
        }

        $response = $next($request, $response);

        $token->setExpiration(time() + 1800);

        return $response->withHeader($this->_headerName, Tokenize::getInstance()->build($token));
    }
}