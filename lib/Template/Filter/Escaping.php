<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */
namespace Frontender\Core\Template\Filter;

class Escaping extends \Twig_Extension
{
    public function getFilters()
    {
        return [
            new \Twig_Filter('clearEmptyTags', [$this, 'stripEmptyTags']),
            new \Twig_Filter('clearInlineStyles', [$this, 'stripStyleAttribute']),
            new \Twig_Filter('slugify', [$this, 'slugify'])
        ];
    }

    public function stripEmptyTags($string)
    {
        return preg_replace('/<(\w+)\b(?:\s+[\w\-.:]+(?:\s*=\s*(?:"[^"]*"|"[^"]*"|[\w\-.:]+))?)*\s*\/?>\s*<\/\1\s*>/', '', $string);
    }

    public function stripStyleAttribute($string)
    {
        return preg_replace('/style=(["\'])[^\1]*?\1/i', '', $string);
    }

    public function slugify($string) {
        // We will only accept strings for this function.
        if(!is_string($string)) {
            return $string;
        }

        /** Thank you Joomla! **/
        // Replace double byte whitespaces by single byte (East Asian languages)
        $str = preg_replace('/\xE3\x80\x80/', ' ', $string);

        // Remove any '-' from the string as they will be used as concatenator.
        // Would be great to let the spaces in but only Firefox is friendly with this

        $str = str_replace('-', ' ', $str);

        // Replace forbidden characters by whitespaces
        $str = preg_replace('#[:\#\*"@+=;!><&\.%()\]\/\'\\\\|\[]#', "\x20", $str);

        // Delete all '?'
        $str = str_replace('?', '', $str);

        // Trim white spaces at beginning and end of alias and make lowercase
        $str = trim(strtolower($str));

        // Remove any duplicate whitespace and replace whitespaces by hyphens
        $str = preg_replace('#\x20+#', '-', $str);

        return $str;
    }
}