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

class Pagination extends \Twig_Extension
{
    public function getFilters()
    {
        return [
            new \Twig_Filter('pagination', [$this, 'getPagination'])
        ];
    }

    public function getPagination($entries, $total, $model)
    {
        // We only accept an array.
        if (!is_array($entries)) {
            return false;
        }

        $states = $model->getState()->getValues();
        $states['limit'] = $states['limit'] == 0 ? 1 : $states['limit'];

        $current = ($states['skip'] ?? 0) / $states['limit'];
        $current = $current > 0 ? $current : 0;
        $pages = floor($total / $states['limit']);
        $links = [];

        $links[] = $this->_getFirstPage($current, $pages, $states, $total);
        $links[] = $this->_getPreviousPage($current, $pages, $states, $total);

        $links = array_merge($links, $this->_getPages($current, $pages, $states, $total));

        $links[] = $this->_getNextPage($current, $pages, $states, $total);
        $links[] = $this->_getLastPage($current, $pages, $states, $total);

        return $links;
    }

    protected function _getPages($current, $pages, $states, $total)
    {
        $links = [];

        if (($states['skip'] ?? 0) >= 0) {
            $start = $current - 2;

            while ($start < $current) {
                if ($start >= 0) {
                    $links[] = [
                        'title' => $start + 1,
                        'query' => '?skip=' . $states['limit'] * $start,
                        'page' => $start + 1
                    ];
                }

                $start++;
            }
        }

        // Current page
        $links[] = [
            'title' => $current + 1,
            'query' => '?skip=' . ($states['skip'] ?? 0),
            'page' => $current + 1,
            'state' => 'is-current'
        ];

        $end = $current + 1;
        while ($end * $states['limit'] < $total && $end < $current + 3) {
            $links[] = [
                'title' => $end + 1,
                'query' => '?skip=' . $states['limit'] * $end,
                'page' => $end + 1
            ];

            $end++;
        }

        return $links;
    }

    protected function _getFirstPage($current, $pages, $states, $total)
    {
        return [
            'title' => 'First',
            'query' => '?skip=0',
            'page' => '0',
            'state' => $current == '0' ? 'is-disabled' : ''
        ];
    }

    protected function _getPreviousPage($current, $pages, $states, $total)
    {
        return [
            'title' => 'Previous',
            'query' => '?skip=' . ($current - 1 < 0 ? 0 : $current - 1) * $states['limit'],
            'page' => $current - 1,
            'state' => ''
        ];
    }

    protected function _getNextPage($current, $pages, $states, $total)
    {
        $next = ($current + 1) * $states['limit'];

        if ($next > $total) {
            return $this->_getLastPage($current, $pages, $states, $total);
        }

        // We have the current, just add one and times the limit.
        return [
            'title' => 'Next',
            'query' => '?skip=' . $next,
            'page' => $current + 1,
            'state' => ''
        ];
    }

    protected function _getLastPage($current, $pages, $states, $total)
    {
        $pages = floor($total / $states['limit']);
        $pages = $total % $states['limit'] > 0 ? $pages : $pages - 1;

        return [
            'title' => 'Last',
            'query' => '?skip=' . $states['limit'] * $pages,
            'page' => $pages,
            'state' => $pages == $current ? 'is-disabled' : ''
        ];
    }
}