<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Page;

use Doctrine\Common\Inflector\Inflector;
use Prototype\Model\AbstractModel;
use Frontender\Core\Object\MapperTrait;
use Frontender\Core\Object\Object;
use Frontender\Core\Parameters\ParametersTrait;
use Frontender\Core\Template\Template;
use Frontender\Core\Wrappers;

use Slim\Container;
use Slim\Http\Request;

class DefaultPage extends Object
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

    public function getName() : string
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    public function getRequest() : Request
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
    public function getLocale() : string
    {
        return $this->container->language->get();
    }

    public function parseData()
    {
        if($this->_parsed === true) {
            return $this->data;
        }

        $this->_parseArray($this->data);

        $this->_parsed = true;
    }

    private function getId()
    {
	    // Check if we have a frontender request, if so return the post id, else return null;
	    $parameters = $this->getParameters();
	    $id = $this->parameters['default']['id'] ?? null;

	    // Needed to get things from nested values.
	    if ( strpos( $id, '.' ) !== false ) {
		    $parts = explode( '.', $id );
		    $id = array_reduce( $parts, function ( $carry, $item ) {
			    return $carry[ $item ] ?? null;
		    }, $parameters );

	    }

	    return $id;
    }

    public function getFormValue($form)
    {
        return is_object($form) ? $form->value : $form['value'];
    }

    public function render(array $data = array()) : string
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

	private function _parseArray(&$array) {
		foreach ( $array as $key => &$values ) {
			if ( $key === 'template_config' ) {
				foreach ( $values as $name => $value ) {
					if ( $name === 'model' ) {
						if(isset($value['id']) && strpos($value['id'], '{') !== false) {
							$value['id'] = $this->getId();
						}

						$array[ $name ] = new Wrappers\Model( $value, $this->container );
					} else {
						$array[ $name ] = new Wrappers\Config( $value );
					}
				}
			}

			if ( is_array( $values ) ) {
				$this->_parseArray( $values);
			}
		}
	}
}
