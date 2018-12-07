<?php

/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Template\Filter;

use Slim\Container;

class Date extends \Twig_Extension
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
            new \Twig_Filter('tdate', [$this, 'translateDate']),
            new \Twig_Filter('yearStatus', [$this, 'getYearStatus']),
            new \Twig_Filter('elapsed', [$this, 'elapsedString'])
        ];
    }

    public function elapsedString($datetime, $full = false)
    {
        $now = new \DateTime;
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }

    public function getFunctions()
    {
        return [
            new \Twig_Function('yearPassedPercentage', [$this, 'getYearPassedPercentage']),
        ];
    }

    /**
     * This method will use the setLocale and strftime to translate the month names.
     *
     * @param {String} $format The format of the date to translate
     */
    public function translateDate($date, $format, $timezone = null)
    {
        $locale = str_replace('-', '_', $this->_locale);
        $mutations = [
            $locale,
            $locale . '.utf-8',
            $locale . '.UTF8',
            $locale . '.utf8',
            $locale . '.UTF-8'
        ];

        $date = new \DateTime($date, new \DateTimeZone('UTC'));

        if ($timezone) {
            $date->setTimeZone(new \DateTimeZone($timezone));
        }

        setlocale(LC_TIME, $mutations);
        return strftime($format, strtotime($date->format('Y-m-d')));
    }

    public function getYearStatus($year, $completedClass, $currentClass)
    {
        $currentYear = date('Y');

        if ($currentYear == $year) {
            return $currentClass;
        } else if ($currentYear > $year) {
            return $completedClass;
        }

        return '';
    }

    public function getYearPassedPercentage()
    {
        $begin = new \DateTime('01-01-' . date('Y'));
        $now = new \DateTime();
        $diff = $begin->diff($now)->days;

        return floor(($diff / 365) * 10) * 10;
    }
}