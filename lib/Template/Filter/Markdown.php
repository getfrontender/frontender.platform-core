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

        /*
         * First, retrieve the images and replace them with a figure tag of the image
         * Regex: (!\[.*?\]\(\/\/(.*\..*)\/(.{12})\/(.{12,26})\/(.*)\/(.*)\))
         * $matches[1] is the alt text
         * $matches[2] is the full asset url
         * $matches[5] is the Contentful asset id
         */

        return $this->parser->text($data);
    }
}
