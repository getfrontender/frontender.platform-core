<?php

namespace Frontender\Core\Routes\Traits;

use Slim\Exception\MethodNotAllowedException;

trait Authorizable {
	public function isAuthorized($action, $request, $response) {
		if(false) {
			throw new MethodNotAllowedException($request, $response, []);
		}

		return true;
	}
}