<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Template\Filter;

use Slim\Container;

class Text extends \Twig_Extension
{
    public function getFilters()
    {
        return [
            new \Twig_Filter('truncate', [$this, 'truncate']),
            new \Twig_Filter('appendBlankTarget', [$this, 'appendTarget'])
        ];
    }

    public function appendTarget($text) {
        return preg_replace("/<a.*?href=\"(.*?)\".*?>/", "<a href=\"$1\" target=\"_blank\">", $text);
    }

    public function truncate($text, $length = 50) {
        if(!is_string($text)) {
            return $text;
        }

        if(strlen($text) <= $length) {
            return $text;
        }
        
        return trim(strip_tags(substr($text, 0, $length))) . '&hellip;';
    }
}