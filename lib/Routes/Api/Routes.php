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
use Frontender\Core\Routes\Traits\Authorizable;
use Slim\Http\Request;
use Slim\Http\Response;
use Frontender\Core\App;
use Frontender\Core\DB\Adapter;

class Routes extends CoreRoute {
    protected $group = '/api/routes';

    use Authorizable;

    public function __construct(App $container) {
        parent::__construct($container);

        $this->collection = Adapter::getInstance()->collection('routes');
    }

    public function registerReadRoutes() {
        parent::registerReadRoutes();

        $self = $this;

        $this->get('/redirects', function(Request $request, Response $response) use ($self) {
            $redirects = $self->collection->find([
                'type' => ['$in' => ['simple', 'regex']]
            ])->toArray();

            return $response->withJson(
                Adapter::getInstance()->toJSON($redirects)
            );
        });

        $this->get('/landingpages', function(Request $request, Response $response) use ($self) {
            $landingpages = $self->collection->find([
                'type' => 'landingpage'
            ])->toArray();

            return $response->withJson(
                Adapter::getInstance()->toJSON($landingpages)
            );
        });
    }
}