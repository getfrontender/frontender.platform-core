<?php
/*******************************************************
 * @copyright 2017-2019 Dipity B.V., The Netherlands
 * @package Frontender
 * @subpackage Frontender Platform Core
 *
 * Frontender is a web application development platform consisting of a
 * desktop application (Frontender Desktop) and a web application which
 * consists of a client component (Frontender Platform) and a core
 * component (Frontender Platform Core).
 *
 * Frontender Desktop, Frontender Platform and Frontender Platform Core
 * may not be copied and/or distributed without the express
 * permission of Dipity B.V.
 *******************************************************/

namespace Frontender\Core\Page;

use Frontender\Core\Object\MapperTrait;
use Frontender\Core\Parameters\ParametersTrait;
use Frontender\Core\Template\Template;
use Frontender\Core\Wrappers;
use Slim\Container;
use Slim\Http\Request;
use Frontender\Core\DB\Adapter;
use MongoDB\BSON\ObjectId;
use Frontender\Core\Controllers\Pages;
use Frontender\Core\Object\AbstractObject;

class DefaultPage extends AbstractObject
{
    use ParametersTrait;
    use MapperTrait;

    protected $template;

    protected $name;

    protected $request;

    private $_parsed = false;

    public $data;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->template = new Template($container);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function setRequest($request)
    {
        $this->request = $request;

        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    // TODO: Allow for flexiable injection!
    public function getLocale(): string
    {
        return $this->container->language->get();
    }

    public function parseData()
    {
        if ($this->_parsed === true) {
            return $this->data;
        }

        $this->_parseArray($this->data);

        $this->_parsed = true;
    }

    private function getId()
    {
        // Check if we have a frontender request, if so return the post id, else return null;
        $parameters = $this->getParameters();
        $id = $this->parameters['default']['id'] ?? 'unexisting-id';

        // Needed to get things from nested values.
        if (strpos($id, '.') !== false) {
            $parts = explode('.', $id);
            $id = array_reduce($parts, function ($carry, $item) {
                return $carry[$item] ?? null;
            }, $parameters);
        }

        if (isset($_GET['id'])) {
            $id = $_GET['id'];
        }

        return $id;
    }

    public function getFormValue($form)
    {
        return is_object($form) ? $form->value : $form['value'];
    }

    public function render(array $data = array()): string
    {
        $this->parseData();

        // Have still to figure out what this does, will most likely leave it in.
        $vars = array_merge($this->getParameters(), (array_merge($this->getData(), $data))) ?? [];
        $this->getTemplate()->setDefaultVariables($vars);

        $pageData = $this->getData();

        return $this->template->loadFile($pageData['template'])->render($pageData);
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function camalize($word)
    {
        $word = str_replace(' ', '', ucwords(strtolower(str_replace('_', ' ', $word))));

        return $word;
    }

    private function _parseArray(&$array)
    {
        /**
         * Before we start to sanitize the current "container"
         * we have to check if we have no template_config and a blueprint, if so we need to take the
         * data from the blueprint.
         */
        if (isset($array['blueprint'])) {
            if (!isset($array['template_config']) || isset($array['template_config']) && $array['template_config'] === null) {
                // Get the blueprint.
                $blueprint = Adapter::getInstance()->collection('blueprints')->findOne([
                    '_id' => new ObjectId($array['blueprint'])
                ]);
                $blueprint = Adapter::getInstance()->toJSON($blueprint, true);

                $array['template_config'] = $blueprint['definition']['template_config'] ?? [];
                $array['containers'] = $blueprint['definition']['containers'] ?? [];

                $array = Pages::sanitize($array);
            }
        }

        foreach ($array as $key => &$values) {
            if ($key === 'template_config') {
                foreach ($values as $name => $value) {
                    if ($name === 'model') {
                        $value = $this->mapValues($value);

                        if (isset($value['data'])) {
                            $value['data'] = $this->mapValues($value['data']);
                        }

                        $array[$name] = new Wrappers\Model($value, $this->container);
                    } else {
                        $array[$name] = new Wrappers\Config($value);
                    }
                }
            }

            if (is_array($values)) {
                $this->_parseArray($values);
            }
        }
    }
}
