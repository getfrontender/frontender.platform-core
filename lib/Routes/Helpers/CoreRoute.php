<?php

namespace Frontender\Core\Routes\Helpers;

use Frontender\Core\App;

class CoreRoute {
	protected $app;
	protected $config;
	protected $group = '';

	public function __construct(App $app) {
		$this->app = $app->getApp();
		$this->config = $app->getConfig();

		// Add new variable because it will be lost in the upcoming closure.
		$self = $this;

		$this->app->group($this->group, function() use ($self) {
			$self->registerCreateRoutes();
			$self->registerReadRoutes();
			$self->registerUpdateRoutes();
			$self->registerDeleteRoutes();
		});
	}

	protected function registerCreateRoutes() {

	}

	protected function registerReadRoutes() {

	}

	protected function registerUpdateRoutes() {

	}

	protected function registerDeleteRoutes() {

	}
}