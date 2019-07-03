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
            $locale = $this->language->get();
            $settings = Adapter::getInstance()->toJSON(Adapter::getInstance()->collection('settings')->find()->toArray(), true);
            $settings = array_shift($settings);
            $scope = array_shift($settings['scopes']);

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
    }

    public function getGroupMiddleware()
    {
        return [
            new ApiLocale($this->app->getContainer())
        ];
    }
}