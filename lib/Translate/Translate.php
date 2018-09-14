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
use Frontender\Core\DB\Adapter;

class Translate
{
    protected $_translator;
    protected $_language;

    public function __construct($container)
    {
        $language = $container['language']->language;
        $translations_path = $container['settings']['project']['path'] . '/translations/';
        $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
        $setting = array_shift($settings);

        // Create the translator based on symfony.
        $this->_translator = new Translator($language, new MessageSelector());

        // We will always fall back to en.
        // TODO: Recheck this file.
        $this->_translator->setFallbackLocales([$setting->scopes[0]->locale]);

        // Add the yaml file locator (translations files are in yml.
        $this->_translator->addLoader('yml', new YamlFileLoader());

        // Add the files.
        foreach ($setting->scopes as $scope) {
            $this->_translator->addResource('yml', $translations_path . $scope->locale . '.yml', $scope->locale);
        }

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
