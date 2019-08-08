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
use Slim\Http\Request;
use Slim\Http\Response;
use Frontender\Core\Routes\Middleware\TokenCheck;

class Keepalive extends CoreRoute
{
    protected $group = '/api/keepalive';

    public function registerReadRoutes()
    {
        parent::registerReadRoutes();

        $this->app->get('', function(Request $request, Response $response) {
            return $response->withJson(['success' => true]);
        });
    }

    public function getGroupMiddleware()
    {
        return [
            new TokenCheck(
                $this->app->getContainer()
            )
        ];
    }
}