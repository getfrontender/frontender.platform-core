<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Template\Filter;

use Slim\Container;

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
        $this->_fallback = $this->_container->settings->get('default_locale');
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
    public function translate($text, $translate = true, $locale = null)
    {
	    if(is_string($text))
	    {
		    if($translate && $this->_translator->hasTranslation($text)) {
			    return $this->_translator->translate($text, $locale);
		    }
	    }
	    else if(is_array($text) || is_object($text))
	    {
		    // We always want an array.
		    $text = (array) $text;

		    if(array_key_exists($locale, $text) && !empty($text[$locale])) {
			    return $text[$locale];
		    }

		    if(array_key_exists($this->_locale, $text) && !empty($text[$this->_locale])) {
			    return $text[$this->_locale];
		    } else if(array_key_exists($this->_fallback, $text)) {
			    return $text[$this->_fallback];
		    }

		    return '';
	    }

	    // No translation found
	    if($this->_debug) {
		    return '??' . $text . '??';
	    }

	    return $text;
    }
}
