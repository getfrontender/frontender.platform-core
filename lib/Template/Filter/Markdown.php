<?php

/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

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
