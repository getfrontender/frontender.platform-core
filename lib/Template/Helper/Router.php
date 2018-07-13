<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Template\Helper;

use Frontender\Core\DB\Adapter;
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
	    $params['locale'] = $params['locale'] ?? $this->container->language->language;
	    $params['slug'] = $params['slug'] ?? '';

	    /**
	     * So what do we need to do?
	     *
	     * I have to check if the current domain (we can use it now)
	     * exists a few times, if so we need to append the locale to the url.
	     *
	     * If not we don't need to locale and the domain has a locale itself.
	     * 
	     * So the steps are basically the same however we only need the basic path.
	     * We are not bound to the routing of Slim Framework, we can make as much logics we need ourselves.
	     */

	    // If a url is found, we won't even look further
	    if(isset($params['url'])) {
		    return $params['url'];
	    }

	    $path = $this->_getPath($params);
	    $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
	    $setting = Adapter::getInstance()->toJSON(array_shift($settings), true);
	    $uri = $this->container->get('request')->getUri();
	    $domain = $uri->getHost();

	    $amount = array_filter($setting['scopes'], function($scope) use ($domain) {
		    return $scope['domain'] === $domain;
	    });

	    if(count($amount) === 1) {
	    	// Use the current domain, without any locale
		    return $uri->withPath($path);
	    } else {
		    return $uri->withPath($params['locale'] . '/' . $path);
	    }

	    // Check if the page is in the aliasses.
	    if(array_key_exists('page', $params)) {
		    $page = @json_decode(file_get_contents($this->container->settings['project']['path'] . '/pages/published/' . $params['page'] . '.json'));
		    if(is_object($page) && property_exists($page, 'cononical') && is_object($page->cononical) && property_exists($page->cononical, $params['locale'])) {
			    $params['page'] = $page->cononical->{$params['locale']};
		    }

		    if(is_object($page) && property_exists($page, 'alias') && is_object($page->alias) && property_exists($page->alias, $params['locale'])) {
		    	$params['page'] = $page->alias->{$params['locale']};
		    }
	    }

//	    $path = $this->container->router->pathFor($name, $params);
//	    $route = $this->container->has('domain') ? str_replace((array_key_exists('locale', $params) && !empty($params['locale']) ? '/' . $params['locale'] : ''), '', $path) : $path;

	    return '';
	    return str_replace('//', '/', $route);
    }

    private function _getPath($params = []) {
	    if(array_key_exists('id', $params)) {
	    	$path = 'details??'; // TBD

	    	// First we will check if we can find the page.
		    $page = Adapter::getInstance()->collection('pages.public')->findOne([
		    	'$or' => [
		    		['definition.route.' . $params['locale'] => $params['page']],
		    		['definition.cononical.' . $params['locale'] => $params['page']]
			    ]
		    ]);

//		    $routes = json_decode(file_get_contents($this->container->settings['project']['path'] . '/routes.json'), true);
		    if($page) {
		    	// TODO: This must change.
			    // Here we also have a slug anyway.
			    // Else the slug is an empty string.

			    $model = $page->definition->template_config->model->controls->name->value ?? false;
			    if ( $model ) {
			    	// Check if we have a redirect.
				    $redirect = Adapter::getInstance()->collection('routes.static')->findOne([
				    	'source' => $model . '/' . $params['id']
				    ]);

				    if ( array_key_exists( $model, $routes ) && array_key_exists( $params['id'], $routes[ $model ] ) ) {
					    // Check if we have an alias for the current language.
					    // Load the new json.
					    $json_path = $this->container->settings['project']['path'] . '/pages/published/' . $routes[ $model ][ $params['id'] ]['path'] . '.json';
					    if(file_exists($json_path)) {
						    $json = json_decode( file_get_contents( $json_path ) );
						    if ( $json && is_object( $json ) && property_exists( $json, 'alias' ) && is_object( $json->alias ) && property_exists( $json->alias, $params['locale'] ) ) {
							    return ( $this->container->has( 'domain' ) ? '' : '/' . $params['locale'] ) . '/' . $json->alias->{$params['locale']};
						    }
					    }

					    return $routes[ $model ][ $params['id'] ]['path'];
				    }
			    }
		    }

		    // We don't have anything else, return the build path
		    return $params['page'] . '/' . $params['slug'] . $this->container->settings->get('id_separator') . $params['id'];
	    } else if(array_key_exists('page', $params) && !array_key_exists('id', $params)) {
	    	return $params['page'];
	    } else {
	    	return '/';
	    }
    }
}