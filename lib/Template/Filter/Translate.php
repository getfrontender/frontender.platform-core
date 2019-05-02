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
            new \Twig_Filter('translate', [$this, 'translate']),
            new \Twig_Filter('i18n', [$this, 'internationalization'])
        ];
    }

    /**
     * @param {String/ Array/ Object} $text The text to translate.
     */
    public function translate($text, $languages = array(), $returnOriginal = false)
    {
        $locale = $this->_locale;
        if ($languages) {
            if (isset($languages[$locale])) {
                $locale = $languages[$locale];
            }
        }

        if (is_array($text) || is_object($text)) {
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

            if ($returnOriginal) {
                return $text;
            }

            return '';
        }

        if ($returnOriginal) {
            return $text;
        }

        // No translation found
        return $this->_debug ? '??' . $text . '??' : $text;
    }

    public function internationalization($text)
    {
        if ($this->_translator->hasTranslation($text)) {
            return $this->_translator->translate($text);
        }

        return $this->_debug ? '??' . $text . '??' : $text;
    }
}
