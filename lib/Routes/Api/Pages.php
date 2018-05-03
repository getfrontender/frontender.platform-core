<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;

class Pages extends CoreRoute {
	protected $group = '/api/pages';

	use Authorizable;
}