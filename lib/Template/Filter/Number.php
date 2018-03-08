<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Template\Filter;

use Slim\Container;

class Number extends \Twig_Extension
{
    protected $_locale;
    protected $_container;

    public function __construct(Container $container)
    {
        $this->_container = $container;
        $this->_locale = $container['language']->get();
    }

    public function getFilters()
    {
        return [
            new \Twig_Filter('formatCurrency', [$this, 'formatCurrency'])
        ];
    }

    public function formatCurrency($number) {
        if(!is_int($number) && !is_float($number)) {
            return $number;
        }

        $locale = strtolower($this->_locale) . '_' . strtoupper($this->_locale);
        $mutations = [
            $locale,
            $locale . '.utf-8',
            $locale . '.UTF8',
            $locale . '.utf8',
            $locale . '.UTF-8'
        ];

        setlocale(LC_MONETARY, $mutations);
        return money_format('%!.0n', floor($number));
    }
}