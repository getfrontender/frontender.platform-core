<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;

class Sites extends CoreRoute {
	protected $group = '/api/sites';

	use Authorizable;
}