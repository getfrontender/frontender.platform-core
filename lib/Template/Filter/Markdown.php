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
    	if(empty($data)) {
    		return $data;
	    }

        /*
         * First, retrieve the images and replace them with a figure tag of the image
         * Regex: (!\[.*?\]\(\/\/(.*\..*)\/(.{12})\/(.{12,26})\/(.*)\/(.*)\))
         * $matches[1] is the alt text
         * $matches[2] is the full asset url
         * $matches[5] is the Contentful asset id
         */
		$data = explode(PHP_EOL, $data);
		foreach ($data as $key => &$value) {
			if(preg_match('(!\[(.*?)\]\((\/\/(.*\..*)\/(.{12})\/(.{12,26})\/(.*)\/(.*))\))', $value, $matches)) {
				$value = preg_replace('(!\[(.*?)\]\((\/\/(.*\..*)\/(.{12})\/(.{12,26})\/(.*)\/(.*))\))', $this->_parseImage($matches), $value);
			}
		}
	    
        return $this->parser->text(implode(PHP_EOL, $data));
    }

    private function _parseImage($matches)
    {
        /*
         * To get the real asset, we need to first retrieve the entry id from
         * the media path, then query teh contentful api for the media object,
         * and then format the responsive object. Too much work and too intensive.
         * Best approach now, is to run the assed path through cloudinary.
         */

        $img = '<img ';
        $img.= 'alt="'.$matches[1].'" ';
        $img.= 'src="https://res.cloudinary.com/brickson/image/fetch/w_320/http:'.$matches[2].'" ';
        $img.= 'srcset="';
        $img.= 'https://res.cloudinary.com/brickson/image/fetch/w_480/http:'.$matches[2].' 480w,';
        $img.= 'https://res.cloudinary.com/brickson/image/fetch/w_600/http:'.$matches[2].' 600w,';
        $img.= 'https://res.cloudinary.com/brickson/image/fetch/w_800/http:'.$matches[2].' 800w,';
        $img.= 'https://res.cloudinary.com/brickson/image/fetch/w_1200/http:'.$matches[2].' 1200w,';
        $img.= 'https://res.cloudinary.com/brickson/image/fetch/w_1600/http:'.$matches[2].' 1600w';
        $img.= '" ';
        $img.= 'sizes="(min-width: 64em) 66.7vw, (min-width: 80em) 55.6vw, 100vw" ';
        $img.= '/>';

        return $img;
    }
}
