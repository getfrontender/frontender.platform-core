<?php

namespace Frontender\Core\Routes\Middleware;

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class ApiLocale
{
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        $locale = $request->getQueryParam('locale');

        if($locale) {
            $this->container->language->set($locale);
        }

        return $next($request, $response);
    }
}
