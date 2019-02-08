<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;
use Frontender\Core\Routes\Middleware\TokenCheck;

class Spaces extends CoreRoute {
	protected $group = '/api/spaces';

	use Authorizable;

	public function getGroupMiddleware() {
		return [
			new TokenCheck(
				$this->app->getContainer()
			)
		];
	}
}