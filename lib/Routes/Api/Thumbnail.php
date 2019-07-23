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
use Frontender\Core\Utils\Scopes;

class Thumbnail extends CoreRoute
{
    protected $group = '/api/thumbnail';

    public function registerReadRoutes()
    {
        parent::registerReadRoutes();

        $this->app->get('/homepage', function(Request $req, Response $res) {
            $homepage = '/'; // TODO: Retrieve this from the config.
            $locale = $this->language->get();
            $scopes = Scopes::get();
            $scope = array_shift($scopes);

            if(!$locale) {
                $locale = $scope['locale'];
            }

            $page = Adapter::getInstance()->collection('pages.public')->findOne([
                'definition.route.' . $locale => $homepage
            ]);

            if(!isset($page->revision->thumbnail->{$locale})) {
                return $res->withStatus(404);
            }

            return $res->getBody()->write($page->revision->thumbnail->{$locale});
        });

        $this->app->get('/{base64_page}', function(Request $request, Response $response) {
            list($locale, $homepage) = explode('#', \base64_decode($request->getAttribute('base64_page')));

            $page = Adapter::getInstance()->collection('pages.public')->findOne([
                'definition.route.' . $locale => $homepage
            ]);

            if(!isset($page->revision->thumbnail->{$locale})) {
                return $response->withStatus(404);
            }

            $response->write(base64_decode(str_replace('data:image/png;base64,', '', $page->revision->thumbnail->{$locale})));
            return $response->withHeader('Content-Type', 'image/png');

            // return $response->getBody()->write($page->revision->thumbnail->{$locale});
        });
    }

    public function getGroupMiddleware()
    {
        return [
            new ApiLocale($this->app->getContainer())
        ];
    }
}