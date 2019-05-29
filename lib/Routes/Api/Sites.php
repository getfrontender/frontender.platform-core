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
use Frontender\Core\Routes\Middleware\ApiLocale;
use Frontender\Core\Utils\Manager;

class Sites extends CoreRoute
{
    protected $group = '/api/sites';

    use Authorizable;

    public function registerReadRoutes()
    {
        parent::registerReadRoutes();

        $self = $this;

        $this->app->get('/settings', function (Request $request, Response $response) use ($self) {
            $self->isAuthorized('manage-site-settings', $request, $response);

            try {
                $manager = Manager::getInstance();
                $manager->setToken($this->token);
                $resp = $manager->get('sites/settings');

                $contents = json_decode($resp->getBody()->getContents());

                if ($contents->status !== 'success') {
                    return $response->withStatus(422);
                }

                // Reset the settings, gnagnagna.
                $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
                $settings = Adapter::getInstance()->toJSON($settings);
                $settings = array_shift($settings);

                Adapter::getInstance()->collection('settings')->findOneAndUpdate([
                    '_id' => $settings->_id
                ], [
                    '$set' => [
                        'scopes' => $contents->data->scopes
                    ]
                ]);

                if (isset($settings->preview_settings)) {
                    $contents->data->preview_settings = $settings->preview_settings;
                }

                return $response->withJson($contents->data);
            } catch (\Exception $e) {
                if (method_exists($e, 'getResponse') && method_exists($e, 'hasResponse') && $e->hasResponse()) {
                    $resp = $e->getResponse();

                    return $response->withStatus(
                        $resp->getStatusCode()
                    );
                }

                return $response->withStatus(403);
            }
        });

        $this->app->get('/users', function (Request $request, Response $response) use ($self) {
            $self->isAuthorized('manage-users', $request, $response);

            try {
                $manager = Manager::getInstance();
                $manager->setToken($this->token);
                $resp = $manager->get('sites/users');

                $contents = json_decode($resp->getBody()->getContents());

                if ($contents->status !== 'success') {
                    return $response->withStatus(422);
                }

                return $response->withJson($contents->data);
            } catch (\Exception $e) {
                if (method_exists($e, 'getResponse') && method_exists($e, 'hasResponse') && $e->hasResponse()) {
                    $resp = $e->getResponse();

                    return $response->withStatus(
                        $resp->getStatusCode()
                    );
                }

                return $response->withStatus(403);
            }
        });

        $this->app->get('/reset_settings', function (Request $request, Response $response) use ($self) {
            $response = $self->isAuthorized('manage-site-settings', $request, $response);

            $client = new \GuzzleHttp\Client();
            $res = $client->get($this->config->fem_host . '/api/sites/?id=' . $request->getQueryParam('site_id'), [
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

        $self = $this;

        $this->app->post('/settings', function (Request $request, Response $response) use ($self) {
            $response = $self->isAuthorized('manage-site-settings', $request, $response);

            // First post the data to the manager.
            // The manager will tell us what to do.
            try {
                Manager::getInstance()->setToken($this->token);
                $resp = Manager::getInstance()->patch('sites/settings', [
                    'json' => $request->getParsedBody()
                ]);

                $contents = json_decode($resp->getBody()->getContents());

                if ($contents->status !== 'success') {
                    return $response->withStatus(422);
                }

                // The contents will only contain the scopes that are allowed by the manager.
                // But we will also need to save the preview settings.
                $data = $request->getParsedBody();
                $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
                $settings = array_shift($settings);

                unset($data['_id']);

                Adapter::getInstance()->collection('settings')->findOneAndUpdate([
                    '_id' => $settings->_id
                ], [
                    '$set' => [
                        'scopes' => $contents->data,
                        'languages' => $request->getParsedBodyParam('languages')
                    ]
                ]);

                return $response->withStatus(200);
            } catch (\Exception $e) {
                if (method_exists($e, 'getResponse') && method_exists($e, 'hasResponse') && $e->hasResponse()) {
                    $resp = $e->getResponse();

                    echo '<pre>';
                    print_r($resp->getBody()->getContents());
                    echo '</pre>';
                    die();

                    return $response->withStatus(
                        $resp->getStatusCode()
                    );
                }

                return $response->withStatus(422);
            }
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
