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
use Slim\Http\Uri;
use Doctrine\Common\Inflector\Inflector;

class Markdown extends \Twig_Extension
{
    protected $parser;

    public function __construct()
    {
        $this->parser = new \Parsedown();
    }

    public function getFilters()
    {
        return [
            new \Twig_Filter('markdown', [$this, 'parseMarkdown'])
        ];
    }

    public function parseMarkdown($data)
    {
        if (empty($data)) {
            return $data;
        }

        return $this->parser->text($data);
    }
}
