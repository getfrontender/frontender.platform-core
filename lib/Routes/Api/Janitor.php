<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;

class Janitor extends CoreRoute {
	protected $group = '/api/janitor';

	use Authorizable;
}