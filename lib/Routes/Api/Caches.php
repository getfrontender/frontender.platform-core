<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;
use Frontender\Core\Routes\Middleware\TokenCheck;

class Caches extends CoreRoute {
	protected $group = '/api/caches';

	use Authorizable;

	public function getGroupMiddleware() {
		return [
			new TokenCheck(
				$this->app->getContainer()
			)
		];
	}
}