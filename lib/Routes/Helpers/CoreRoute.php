<?php

namespace Frontender\Core\Routes\Helpers;

class CoreRoute {
	protected $app;
	protected $config;
	protected $group = '/';

	public function __construct($app) {
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

	public function getJson($file, $assoc = false) {
		return json_decode(file_get_contents(str_replace('//', '/', $file)), $assoc);
	}

	protected function registerRoutes() {

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