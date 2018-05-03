<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;

class Blueprints extends CoreRoute {
	protected $group = '/api/blueprints';

	use Authorizable;
}