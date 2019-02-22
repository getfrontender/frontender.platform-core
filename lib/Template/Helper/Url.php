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
            new \Twig_SimpleFunction('is_current', [$this, 'isCurrentUrl']),
            new \Twig_SimpleFunction('translate_url', [$this, 'translateUrl'])
        ];
    }

    public function getCurrentUrl()
    {
        return $this->container['request']->getUri();
    }

    public function isActiveUrl($route)
    {
        // I also have to check the domain of the current scope compaired to the domain of the delivered route.
        if (!is_string($route)) {
            if ($route->getHost() !== $this->getCurrentUrl()->getHost()) {
                return false;
            }
        }

        $path = is_object($route) ? ltrim($route->getPath(), '/') : $route;

        return strpos(ltrim($this->getCurrentUrl()->getPath(), '/'), $path) === 0;
    }

    public function isCurrentUrl($route)
    {
        if (!is_string($route)) {
            return $route->__toString() === $this->getCurrentUrl()->__toString();
        }

        return $route === $this->getCurrentUrl()->__toString();
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