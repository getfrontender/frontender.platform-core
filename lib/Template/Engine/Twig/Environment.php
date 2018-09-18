<?php

namespace Frontender\Core\Template\Engine\Twig;

use Frontender\Core\Wrappers\Core;

class Environment extends \Twig_Environment
{
    public function mergeGlobals(array $context)
    {
        if (!isset($context['template'])) {
            return parent::mergeGlobals($context);
        }

        $loader = $this->getLoader();
        $json = str_replace('html.twig', 'json', $context['template']);

        if (!$loader->exists($json)) {
            return parent::mergeGlobals($context);
        }

        $path = $loader->getCacheKey($json);
        $json = json_decode(file_get_contents($path), true);

		// Loop through the template_config wrappers and check if they are empty.
		// If so we will get the default data, if present

        foreach ($context as $key => $wrapper) {
            if ($wrapper instanceof Core && empty($wrapper->getData())) {
                if (isset($json[$key])) {
                    $wrapper->setData($json[$key]);
                }
            } else if (empty($wrapper)) {
                if (isset($json[$key])) {
                    $context[$key] = $json[$key];
                }
            }
        }

		// we don't use array_merge as the context being generally
		// bigger than globals, this code is faster.
        foreach ($this->getGlobals() as $key => $value) {
            if (!array_key_exists($key, $context)) {
                $context[$key] = $value;
            }
        }

        return $context;
    }
}