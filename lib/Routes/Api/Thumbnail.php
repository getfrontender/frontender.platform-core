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
use Frontender\Core\DB\Adapter;
use Frontender\Core\Routes\Middleware\ApiLocale;

class Thumbnail extends CoreRoute
{
    protected $group = '/api/thumbnail';

    public function registerReadRoutes()
    {
        parent::registerReadRoutes();

        $this->app->get('/homepage', function(Request $req, Response $res) {
            $homepage = '/'; // TODO: Retrieve this from the config.

            $page = Adapter::getInstance()->collection('pages.public')->findOne([
                'definition.route.' . $this->language->get() => $homepage
            ]);

            if(!isset($page->revision->thumbnail->{$this->language->get()})) {
                return $res->withStatus(404);
            }

            $thumbnail = $page->revision->thumbnail->{$this->language->get()};
            $part = explode(':', $thumbnail)[1];
            [$mimetype, $base64Image] = explode(';base64,', $part);

            $res->getBody()->write(base64_decode($base64Image));
            return $res->withHeader('Content-Type', $mimetype);
        });
    }

    public function getGroupMiddleware()
    {
        return [
            new ApiLocale($this->app->getContainer())
        ];
    }
}