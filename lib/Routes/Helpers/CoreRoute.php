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

namespace Frontender\Core\Routes\Helpers;

use Frontender\Core\App;
use Frontender\Core\Routes\Middleware\TokenCheck;

class CoreRoute
{
    protected $app;
    protected $config;
    protected $group = '';

    public function __construct(App $app)
    {
        $this->app = $app->getApp();
        $this->config = $app->getConfig();

        // Add new variable because it will be lost in the upcoming closure.
        $self = $this;

        $group = $this->app->group($this->group, function () use ($self) {
            $self->registerCreateRoutes();
            $self->registerReadRoutes();
            $self->registerUpdateRoutes();
            $self->registerDeleteRoutes();
        });

        foreach ($this->getGroupMiddleware() as $middleware) {
            $group->add($middleware);
        }
    }

    protected function registerCreateRoutes()
    { }

    protected function registerReadRoutes()
    { }

    protected function registerUpdateRoutes()
    { }

    protected function registerDeleteRoutes()
    { }

    protected function getGroupMiddleware()
    {
        return [];
    }

    public function get(string $route, \Closure $closure) {
        // $closure = $closure->bindTo($this);

        return $this->app->get($route, $closure);
    }
}
