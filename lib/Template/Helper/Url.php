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

class Url extends \Twig_Extension
{
    public $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
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
        $currentParts = explode('/', $this->getCurrentUrl()->getPath());
        $routeParts = explode('/', $route);

        switch(count($routeParts)) {
            case 0:
            case 1:
                return '';
            case 2:
                return $routeParts[1] === $currentParts[1] ? $class : '';
            case 3:
                return (($routeParts[2] === $currentParts[2] || Inflector::pluralize($currentParts[2]) === $routeParts[2]) && $routeParts[1] === $currentParts[1]) ? $class : '';
        }
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

        if(array_key_exists('id', $params) && $params['id']) {
            $params['id'] .= !empty($query) ? '?' . $query : '';
        } else {
            $params['page'] .= !empty($query) ? '?' . $query : '';
        }

        return $this->container['router']->pathFor($name, $params);
    }
}