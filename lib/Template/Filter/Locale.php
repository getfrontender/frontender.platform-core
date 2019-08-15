<?php
declare(strict_types=1);

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

class Locale extends \Twig_Extension
{
    /**
     * @see: https://www.ietf.org/rfc/rfc3066.txt
     * 
     * tl;dr
     * 
     * en-GB is composed out of 2 (or more) parts.
     * 
     * The first part (en) is the iso language
     * The second part (GB) is the country.
     * 
     * Thus the names arrive at isoLangauge end isoCountry
     */

    public function getFilters(): array {
        return [
            new \Twig_Filter('isoLanguage', [$this, 'getLanguage']),
            new \Twig_Filter('isoCountry', [$this, 'getCountry'])
        ];
    }

    /**
     * This method returns the first part of the locale iso.
     * 
     * @param string $locale The locale to get the language from.
     * @return string The language code retrieved.
     */
    public function getLanguage(string $locale): string
    {    
        $parts = explode('-', $locale);

        return $parts[0] ?? '';
    }

    /**
     * This method returns the second part of the locale iso.
     * 
     * @param string $locale The locale to get the language from.
     * @return string The language code retrieved.
     */
    public function getCountry(string $locale): string
    {
        $parts = explde('-', $locale);

        return $parts[1] ?? '';
    }
}
