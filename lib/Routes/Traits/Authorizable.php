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

namespace Frontender\Core\Routes\Traits;

use Slim\Exception\MethodNotAllowedException;
use Frontender\Core\DB\Adapter;
use Frontender\Core\Routes\Helpers\Tokenize;
use Slim\Http\Request;
use Slim\Http\Response;
use Frontender\Core\Routes\Exceptions\Unauthorized;

trait Authorizable
{
    public function isAuthorized(string $requiredPermission, Request $request, Response &$response): Response
    {
        // By default we will not allow the action.
        $allowed = false;

        // Get the site ID.
        $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
        $settings = array_shift($settings);
        $site_id = $settings->site_id;
        $token = $this->app->getContainer()['token'];

        if ($token->getClaim('site_id') != $site_id) {
            try {
                $client = new \GuzzleHttp\Client();
                $res = $client->get('http://manager.getfrontender.com/api/users/index.php?id=' . $token->getClaim('sub') . '&site=' . $site_id, [
                    'headers' => [
                        'X-Token' => $request->getHeader('X-Token')[0]
                    ]
                ]);

                $token = $res->getHeader('X-Token');

                if ($token) {
                    $token = $token[0];
                }

                // I know not best practice.
                $response = $response->withAddedHeader('X-Token', $token);
                $token = Tokenize::getInstance()->parse($token);
            } catch (\Exception $e) {
                echo $e->getMessage();
                die();
            } catch (\Error $e) {
                echo $e->getMessage();
                die();
            }
        }

        $permissions = $token->getClaim('permissions');
        $allowed = in_array($requiredPermission, $permissions);

        if (!$allowed) {
            throw new Unauthorized($request, $response);
        }

        return $response;
    }
}
