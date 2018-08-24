<?php

/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Translate;

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Loader\YamlFileLoader;

class Translate
{
    protected $_translator;
    protected $_language;

    public function __construct($container)
    {
        $language = $container['language']->language;
        $translations_path = $container['settings']['project']['path'] . '/translations/';

        // Create the translator based on symfony.
        $this->_translator = new Translator($language, new MessageSelector());

        // We will always fall back to en.
        // TODO: Recheck this file.
        // $this->_translator->setFallbackLocales(['en']);

        // Add the yaml file locator (translations files are in yml.
        $this->_translator->addLoader('yml', new YamlFileLoader());

        // Add the files.
        // $this->_translator->addResource('yml', $translations_path . 'en.yml', 'en');
        // $this->_translator->addResource('yml', $translations_path . $language . '.yml', $language);

        $this->_language = $language;
    }

    public function translate($string)
    {
        return $this->_translator->trans(strtolower($string));
    }

    public function hasTranslation($string)
    {
        return $this->_translator->getCatalogue($this->_language)->has(strtolower($string));
    }
}
