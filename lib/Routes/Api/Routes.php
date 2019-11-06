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

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Middleware\ApiLocale;
use Frontender\Core\Routes\Middleware\TokenCheck;
use Frontender\Core\Routes\Traits\Authorizable;
use Slim\Http\Request;
use Slim\Http\Response;
use Frontender\Core\App;
use Frontender\Core\DB\Adapter;
use MongoDB\BSON\ObjectId;


class Routes extends CoreRoute {
    protected $group = '/api/routes';

    use Authorizable;

    public function __construct(App $container) {
        parent::__construct($container);

        $this->collection = Adapter::getInstance()->collection('routes');
    }

    public function registerCreateRoutes() {
        $self = $this;

        $this->app->put('', function(Request $request, Response $response) use ($self) {
        	$self->isAuthorized('manage-redirects', $request, $response);

            // Insert the route into the database.
            $self->collection->insertOne([
                'resource' => $request->getParsedBodyParam('resource'),
                'destination' => $request->getParsedBodyParam('destination'),
                'type' => $request->getParsedBodyParam('type'),
                'status' => (int) $request->getParsedBodyParam('status')
            ]);

            return $response->withStatus(200);
        });
    }

    public function registerReadRoutes() {
        parent::registerReadRoutes();

        $self = $this;

        $this->get('', function(Request $request, Response $response) use ($self) {
	        $self->isAuthorized('manage-redirects', $request, $response);

            $routes = $self->collection->find()->toArray();

            return $response->withJson(
                Adapter::getInstance()->toJSON($routes)
            );
        });

        $this->get('/redirects', function(Request $request, Response $response) use ($self) {
	        $self->isAuthorized('manage-redirects', $request, $response);

            $redirects = $self->collection->find([
                'type' => ['$in' => ['simple', 'regex']]
            ])->toArray();

            return $response->withJson(
                Adapter::getInstance()->toJSON($redirects)
            );
        });

        $this->get('/landingpages', function(Request $request, Response $response) use ($self) {
	        $self->isAuthorized('manage-redirects', $request, $response);

            $landingpages = $self->collection->find([
                'type' => 'landingpage'
            ])->toArray();

            return $response->withJson(
                Adapter::getInstance()->toJSON($landingpages)
            );
        });
    }

    public function registerUpdateRoutes() {
        parent::registerUpdateRoutes();

        $self = $this;

        $this->app->post('/{routeID}', function(Request $request, Response $response) use ($self) {
	        $self->isAuthorized('manage-redirects', $request, $response);

            $self->collection->findOneAndUpdate([
                '_id' => new ObjectID($request->getAttribute('routeID'))
            ], [
                '$set' => [
                    'resource' => $request->getParsedBodyParam('resource'),
                    'destination' => $request->getParsedBodyParam('destination'),
                    'type' => $request->getParsedBodyParam('type'),
                    'status' => (int) $request->getParsedBodyParam('status')
                ]
            ]);

            return $response->withStatus(200);
        });
    }

    public function registerDeleteRoutes() {
        parent::registerDeleteRoutes();

        $self = $this;

        $this->app->delete('/{routeID}', function(Request $request, Response $response) use ($self) {
	        $self->isAuthorized('manage-redirects', $request, $response);

            $self->collection->findOneAndDelete([
                '_id' => new ObjectID($request->getAttribute('routeID'))
            ]);

            return $response->withStatus(200);
        });
    }

	public function getGroupMiddleware()
	{
		return [
			new TokenCheck(
				$this->app->getContainer()
			),
			new ApiLocale($this->app->getContainer())
		];
	}
}