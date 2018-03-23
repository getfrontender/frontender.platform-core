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

class Router extends \Twig_Extension
{
    public $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('route', [$this, 'route'])
        ];
    }

    public function route($params = [])
    {
	    $params['locale'] = $this->container->language->language;
	    $params['slug'] = $params['slug'] ?? '';

	    /**
	     * So to get the correct routing, what do we need to do here:
	     * 1. Get the page json
	     * 2. Try to get the model.
	     * 3. If a model is found, use the model name and given ID to create the new route.
	     */
	    if(array_key_exists('url', $params)) {
		    // Direct url.
		    return $params['url'];
	    } else if(array_key_exists('id', $params)) {
		    $routes = json_decode(file_get_contents($this->container->settings['project']['path'] . '/routes.json'), true);
		    $json = @json_decode(file_get_contents($this->container->settings['project']['path'] . '/pages/published/' . $params['page'] . '.json'));
		    if($json) {
			    $model_path = [ 'template_config', 'model', 'controls', 'name', 'value' ];
			    $model      = array_reduce( $model_path, function ( $json, $index ) {
				    if ( ! $json || ! $json->{$index} ) {
					    return false;
				    }

				    return $json->{$index};
			    }, $json );

			    if ( $model ) {
				    if ( array_key_exists( $model, $routes ) && array_key_exists( $params['id'], $routes[ $model ] ) ) {
					    return $this->_getAlias(($this->container->has('domain') ? '' : '/' . $params['locale']) . $routes[ $model ][ $params['id'] ]['path']);
				    }
			    }
		    }
		    $name = 'details';
	    } else if(array_key_exists('page', $params) && !array_key_exists('id', $params)) {
		    $name = 'list';
	    } else {
		    $name = 'home';
	    }

	    // Check if the page is in the aliasses.
	    if(array_key_exists('page', $params)) {
		    $page = @json_decode(file_get_contents($this->container->settings['project']['path'] . '/pages/published/' . $params['page'] . '.json'));
		    if(is_object($page) && property_exists($page, 'alias') && is_object($page->alias) && property_exists($page->alias, $params['locale'])) {
		    	$params['page'] = $page->alias->{$params['locale']};
		    }
	    }

	    $path = $this->container->router->pathFor($name, $params);
	    $route = $this->container->has('domain') ? str_replace((array_key_exists('locale', $params) && !empty($params['locale']) ? '/' . $params['locale'] : ''), '', $path) : $path;

	    return str_replace('//', '/', $route);
    }

    private function _getAlias($page) {

    }
}