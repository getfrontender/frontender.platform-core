<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;

class Spaces extends CoreRoute {
	protected $group = '/api/spaces';

	use Authorizable;
}