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
use Symfony\Component\Finder\Finder;
use Frontender\Core\Routes\Middleware\TokenCheck;
use Frontender\Core\DB\Adapter;
use MongoDB\BSON\ObjectId;
use Frontender\Core\Routes\Middleware\ApiLocale;

class Lots extends CoreRoute
{
    protected $group = '/api/lots';

    use Authorizable;

    public function registerReadRoutes()
    {
        parent::registerReadRoutes();

        $this->app->get('/{lot_id}', function (Request $request, Response $response) {
            $lot = Adapter::getInstance()->collection('lots')->findOne([
                '_id' => new ObjectId($request->getAttribute('lot_id'))
            ]);
            $lot = Adapter::getInstance()->toJSON($lot);

            return $response->withJson(
                $lot
            );
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
