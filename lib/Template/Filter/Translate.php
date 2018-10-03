<?php

/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Template\Filter;

use Slim\Container;
use Frontender\Core\DB\Adapter;

class Translate extends \Twig_Extension
{
    protected $_locale;
    protected $_container;
    protected $_translator;
    protected $_debug;
    protected $_fallback;

    public function __construct(Container $container)
    {
        $this->_container = $container;
        $this->_locale = $container['language']->get();
        $this->_translator = $container['translate'];
        $this->_debug = $this->_container->settings->get('translation_debug');

        $settings = Adapter::getInstance()->collection('settings')->find()->toArray();
        $setting = count($settings) > 0 ? array_shift($settings) : [];

        $this->_fallback = $setting['fallback_locale'] ?? false;
    }

    public function getFilters()
    {
        return [
            new \Twig_Filter('t', [$this, 'translate']),
            new \Twig_Filter('translate', [$this, 'translate'])
        ];
    }

    /**
     * @param {String/ Array/ Object} $text The text to translate.
     */
    public function translate($text, $languages = array())
    {
        $locale = $this->_locale;
        if ($languages) {
            if (isset($languages[$locale])) {
                $locale = $languages[$locale];
            }
        }

        if (is_string($text)) {
            if ($this->_translator->hasTranslation($text)) {
                return $this->_translator->translate($text);
            }
        } else if (is_array($text) || is_object($text)) {
		    // We always want an array.
            $text = (array)$text;

            if (array_key_exists($locale, $text) && !empty($text[$locale])) {
                return $text[$locale];
            }

            if (array_key_exists($this->_locale, $text) && !empty($text[$this->_locale])) {
                return $text[$this->_locale];
            } else if ($this->_fallback && array_key_exists($this->_fallback, $text)) {
                return $text[$this->_fallback];
            }

            return '';
        }

	    // No translation found
        if ($this->_debug) {
            return '??' . $text . '??';
        }

        return $text;
    }
}
