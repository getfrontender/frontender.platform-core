<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Template;

use Prototype\Object\Object;
use Prototype\Template\Locator\DefaultFactory as DefaultLocatorFactory;
use Prototype\Template\Engine\DefaultFactory as DefaultEngineFactory;
use Prototype\Template\Engine\EngineInterface;

abstract class AbstractTemplate extends Object
{
    protected $path;

    protected $settings = [];

    protected $default_variables = [];

    protected $engine;

    public function getDefaultVariables()
    {
        return $this->default_variables;
    }

    public function setDefaultVariables(array $variables)
    {
        $this->default_variables = array_merge($this->default_variables, $variables);

        return $this;
    }

    public function loadFile($template)
    {
        $locator = (new DefaultLocatorFactory())
            ->createLocator('file');

        if (!$file = $locator->locate($this->container['settings']['template']['path'] . '/' . $template)) {
            throw new \Exception(sprintf('The template "%s" cannot be located.', $this->container['settings']['template']['path'] . '/' .$template));
        }

        $this->engine = (new DefaultEngineFactory($this->container))
            ->createEngine($file)
            ->loadFile($template);

        return $this;
    }

    public function getEngine()
    {
        return $this->engine;
    }

    public function render(array $data = array())
    {
        $data = array_merge($this->default_variables, $data);

        if ($this->engine instanceof EngineInterface)
        {
            $this->engine = $this->engine->render($data);
        }

        return $this->engine;
    }
}
