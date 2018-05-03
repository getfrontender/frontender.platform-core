<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;

class Adapters extends CoreRoute {
	protected $group = '/api/adapters';

	use Authorizable;
}