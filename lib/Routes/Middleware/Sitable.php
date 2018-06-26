<?php

namespace Frontender\Core\Routes\Middleware;

use FastRoute\Dispatcher;
use Frontender\Core\DB\Adapter;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\NotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * The Sitable middleware is the heart of the multi-site functionality.
 *
 * What will this do:
 * 1. It will check if the site is present as a main container.
 *    1a. If the site isn't the main container than check if it is a language alias
 * 2. If the site is found, do a internal redirect.
 * 3. If the site isn't found as a main container or language alias, return a 404 error.
 */
class Sitable
{
    protected $_container;

    function __construct(\Slim\Container $container)
    {
        $this->_container = $container;
    }

    public function __invoke(Request $request, Response $response, $next) {
	    if ( strpos( $request->getUri()->getPath(), '/api' ) === 0 ) {
		    return $next( $request, $response );
	    }

	    $router   = $this->_container->get( 'router' );
	    $settings = Adapter::getInstance()->collection( 'settings' )->find()->toArray();
	    $setting  = array_shift( $settings );
	    $setting  = Adapter::getInstance()->toJSON( $setting )->scopes;
	    $domains = array_column( $setting, 'domain' );
	    $routeInfo = $request->getAttribute('routeInfo');

	    if ( ! in_array( $request->getUri()->getHost(), $domains ) ) {
		    die( 'Domain not found!' );
	    }

	    $index  = array_search( $request->getUri()->getHost(), $domains );
	    $locale = $setting[ $index ]->locale;

	    // TODO: Check the scenarios here, there are a few to be honest.
	    $uri = $request->getUri();

	    // If the locale is found, and is also present in the path, we need to remove it from the path.
	    // This will be done with a redirect.
	    if(isset($routeInfo[2]) && strpos($uri->getPath(), $routeInfo[2]['locale']) <= 1) {
	    	return $response->withRedirect(
			    $uri->withPath(
				    str_replace($locale . '/', '', $uri->getPath())
			    )
		    );
	    }

	    $uri = $uri->withPath(
		    $locale . $uri->getPath()
	    );

	    // The following path comes directly from the App code from Slim framework.
	    // This does what we need it to do and this will work for us.
	    // Way better than internal redirects.
	    $request   = $request->withUri( $uri );
	    $routeInfo = $router->dispatch( $request );

	    if($routeInfo[0] !== Dispatcher::FOUND) {
	    	throw new NotFoundException($request, $response);
	    }

	    $routeArguments = [];
	    foreach ( $routeInfo[2] as $k => $v ) {
		    $routeArguments[ $k ] = urldecode( $v );
	    }

	    $route = $router->lookupRoute( $routeInfo[1] );
	    $route->prepare( $request, $routeArguments );

	    // add route to the request's attributes in case a middleware or handler needs access to the route
	    $routeInfo['request'] = [ $request->getMethod(), (string) $request->getUri() ];
	    $request              = $request->withAttribute( 'route', $route )
	                                    ->withAttribute( 'routeInfo', $routeInfo );


	    return $next( $request, $response );
    }
}
