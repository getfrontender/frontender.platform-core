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

namespace Frontender\Core\Template;

use Frontender\Core\Template\Locator\DefaultFactory as DefaultLocatorFactory;
use Frontender\Core\Template\Engine\DefaultFactory as DefaultEngineFactory;
use Frontender\Core\Template\Engine\EngineInterface;
use Frontender\Core\Object\AbstractObject;

abstract class AbstractTemplate extends AbstractObject
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
            throw new \Exception(sprintf('The template "%s" cannot be located.', $this->container['settings']['template']['path'] . '/' . $template));
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

        if ($this->engine instanceof EngineInterface) {
            $this->engine = $this->engine->render($data);
        }

        return $this->engine;
    }
}
