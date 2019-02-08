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