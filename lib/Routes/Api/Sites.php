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

use Frontender\Core\DB\Adapter;
use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;
use Slim\Http\Request;
use Slim\Http\Response;
use Frontender\Core\Routes\Middleware\TokenCheck;

class Sites extends CoreRoute
{
    protected $group = '/api/sites';

    public function registerReadRoutes()
    {
        parent::registerReadRoutes();

        $this->app->get('/settings', function (Request $request, Response $response) {
            $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
            $setting = Adapter::getInstance()->toJSON(array_shift($settings));

            return $response->withJson($setting ?? new \stdClass());
        });

        $this->app->get('/reset_settings', function (Request $request, Response $response) {
            $client = new \GuzzleHttp\Client();
            $res = $client->get('http://manager.getfrontender.com/api/sites/?id=' . $request->getQueryParam('site_id'), [
                'headers' => [
                    'X-Token' => $request->getHeader('X-Token')
                ]
            ]);

            $content = json_decode($res->getBody()->getContents(), true);

            $content['scopes'] = json_decode($content['scopes']);
            $content['site_id'] = $content['_id'];
            $content['preview_settings'] = json_decode($content['preview_settings']);
            unset($content['_id']);

            Adapter::getInstance()->collection('settings')->drop();
            Adapter::getInstance()->collection('settings')->insertOne($content);

            return $response->withStatus(200);
        });
    }

    public function registerUpdateRoutes()
    {
        parent::registerUpdateRoutes();

        $this->app->post('/settings', function (Request $request, Response $response) {
            $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
            $setting = array_shift($settings);
            $data = $request->getParsedBody();
            unset($data['_id']);

            Adapter::getInstance()->collection('settings')->findOneAndReplace([
                '_id' => $setting->_id
            ], $data);

            return $response->withStatus(200);
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