<?php

namespace Frontender\Core\Routes\Helpers;

use Frontender\Core\App;
use Frontender\Core\Routes\Middleware\TokenCheck;

class CoreRoute {
	protected $app;
	protected $config;
	protected $group = '';

	public function __construct(App $app) {
		$this->app = $app->getApp();
		$this->config = $app->getConfig();

		// Add new variable because it will be lost in the upcoming closure.
		$self = $this;

		$group = $this->app->group($this->group, function() use ($self) {
			$self->registerCreateRoutes();
			$self->registerReadRoutes();
			$self->registerUpdateRoutes();
			$self->registerDeleteRoutes();
		});

		foreach($this->getGroupMiddleware() as $middleware) {
			$group->add($middleware);
		}
	}

	protected function registerCreateRoutes() {

	}

	protected function registerReadRoutes() {

	}

	protected function registerUpdateRoutes() {

	}

	protected function registerDeleteRoutes() {

	}

	protected function getGroupMiddleware() {
		return [];
	}
}