<?php

namespace Frontender\Core\Routes\Api;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Frontender\Core\Routes\Traits\Authorizable;

class Caches extends CoreRoute {
	protected $group = '/api/caches';

	use Authorizable;
}