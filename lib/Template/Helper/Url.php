<?php

/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Template\Helper;

use Slim\Container;
use Slim\Http\Uri;
use Doctrine\Common\Inflector\Inflector;
use Frontender\Core\Template\Helper\Router;

class Url extends \Twig_Extension
{
    public $container;
    public $router;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->router = new Router($container);
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('current_url', [$this, 'getCurrentUrl']),
            new \Twig_SimpleFunction('is_active', [$this, 'isActiveUrl']),
            new \Twig_SimpleFunction('translate_url', [$this, 'translateUrl'])
        ];
    }

    public function getCurrentUrl()
    {
        return $this->container['request']->getUri();
    }

    public function isActiveUrl($route, $class)
    {
        $path = is_object($route) ? ltrim($route->getPath(), '/') : $route;

        return $path === ltrim($this->getCurrentUrl()->getPath(), '/') ? $class : '';
    }

    /**
     * Return the translated url.
     *
     * TODO: Refactor for multilingual (after we know what to do).
     *
     * @param $locale
     */
    public function translateUrl($locale)
    {
        $route = $this->container['page']->getRequest()->getAttribute('route');
        $name = $route->getName();
        $query = http_build_query($this->container->request->getQueryParams());

        $params = $route->getArguments();
        $params['locale'] = $locale;

        if (array_key_exists('id', $params) && $params['id']) {
            $params['id'] .= !empty($query) ? '?' . $query : '';
        } else if (array_key_exists('page', $params)) {
            $params['page'] .= !empty($query) ? '?' . $query : '';
        }

        return $this->router->route($params);
    }
}