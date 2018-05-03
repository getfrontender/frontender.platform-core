<?php

namespace Frontender\Core\Routes;

use Frontender\Core\Routes\Helpers\CoreRoute;
use Slim\Http\Request;
use Slim\Http\Response;

class App extends CoreRoute {
	protected function registerReadRoutes() {
		parent::registerReadRoutes();

		$this->app->get('/', function(Request $request, Response $response) {
			return $response->withRedirect('/en/');
		});

		$this->app->get('/{locale}/', function (Request $request, Response $response) {
			$locale = $request->getAttribute('locale');

			$this->language->set($locale);

			$data = getFileJson($this->settings['project']['path'] . '/pages/published/home.json', true);

			$page = $this->page;
			$page->setParameters(['locale' => $locale, 'debug' => $this->settings['debug'], 'query' => $request->getQueryParams()]);
			$page->setData($data);
			$page->setRequest($request);

			$response->getBody()->write($page->render());

			return $response;
		})->setName('home');

		$this->app->get('/{locale}/{page:.*}/{slug:.*}' . $this->config->id_separator . '{id}', function (Request $request, Response $response) {
			$attributes = $request->getAttributes();

			$this->language->set($attributes['locale']);

			$data = $this->getJson($this->settings['project']['path'] . '/pages/published/' . $attributes['page'] . '.json', true);

			$page = $this->page;
			$page->setName($attributes['page']);
			$page->setParameters(['page' => $request->getAttribute('page'), 'locale' => $attributes['locale'], 'id' => $attributes['id'], 'debug' => $this->settings['debug'], 'query' => $request->getQueryParams()]);
			$page->setData($data);
			$page->setRequest($request);

			$page->parseData();

			$response->getBody()->write($page->render());

			return $response;
		})->setName('details');

		$this->app->get('/{locale}/{page:.*}', function (Request $request, Response $response) {
			$locale = $request->getAttribute('locale');
			$page = $request->getAttribute('page');

			$this->language->set($locale);

			$data = $this->getJson($this->settings['project']['path'] . '/pages/published/' . $page . '.json', true);

			$page = $this->page;
			$page->setParameters(['page' => $request->getAttribute('page'), 'locale' => $locale, 'debug' => $this->settings['debug'], 'query' => $request->getQueryParams()]);
			$page->setData($data);
			$page->setRequest($request);
			$response->getBody()->write($page->render());

			return $response;
		})->setName('list');
	}
}