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