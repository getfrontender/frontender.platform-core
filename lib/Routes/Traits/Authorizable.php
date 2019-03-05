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
    public function isAuthorized(string $roleRequired, Request $request, Response $response): void
    {
        // We will specify which role is required for this action.
        // If the role isn't found in the current roles (for this site)
        // then we will not allow the action.

        // By default we will not allow the action.
        $allowed = false;

        // Get the site ID.
        $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
        $settings = array_shift($settings);
        $site_id = $settings->site_id;
        $token = $this->app->getContainer()['token'];
        $roles = $token->getClaim('roles');
        $rolesSites = array_flip(array_column($roles, 'site_id'));

        if (isset($rolesSites[$site_id])) {
            $index = $rolesSites[$site_id];
            $roles = array_column((array)$roles[$index]->roles, 'role_slug');

            $allowed = in_array($roleRequired, $roles);
        }

        if (!$allowed) {
            throw new Unauthorized($request, $response);
        }
    }
}
