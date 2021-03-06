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
use Coduo\PHPHumanizer\StringHumanizer;
use Coduo\PHPHumanizer\NumberHumanizer;
use Coduo\PHPHumanizer\CollectionHumanizer;
use Coduo\PHPHumanizer\DateTimeHumanizer;

class Humanize extends \Twig_Extension
{
    protected $_locale;
    protected $_container;

    public function __construct(Container $container)
    {
        $this->_container = $container;
        $this->_locale = substr($container['language']->get(), 0, 2);
    }

    public function getFilters()
    {
        return [
            new \Twig_Filter('humanize', [$this, 'humanizer']),
            new \Twig_Filter('stringHumanize', [$this, 'stringHumanizer']),
            new \Twig_Filter('numberHumanize', [$this, 'numberHumanizer']),
            new \Twig_Filter('collectionHumanize', [$this, 'collectionHumanizer']),
            new \Twig_Filter('dateHumanize', [$this, 'dateTimeHumanizer'])
        ];
    }

    public function humanizer($data, $type)
    {
        switch($type) {
            case 'string':
            default:
                return $this->stringHumanizer($data);
            case 'number':
                return $this->numberHumanizer($data);
            case 'collection':
            case 'list':
                return $this->collectionHumanizer($data);
            case 'date':
            case 'time':
            case 'datetime':
                return $this->dateTimeHumanizer($data);
        }
    }

    public function stringHumanizer($data)
    {
        return StringHumanizer::humanize($data);
    }

    public function numberHumanizer($data)
    {
        return NumberHumanizer::ordinalize($data);
    }

    public function collectionHumanizer($data)
    {
        return CollectionHumanizer::oxford($data);
    }

    public function dateTimeHumanizer($data)
    {
        return dateTimeHumanizer::difference(new \DateTime(), new \DateTime($data), $this->_locale);
    }
}
