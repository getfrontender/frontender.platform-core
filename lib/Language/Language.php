<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Language;

class Language
{
    public $language;

    public function __construct(string $language = '')
    {
        $this->language = $language;
    }

    public function get() : string
    {
        return $this->language;
    }

    public function set(string $language = '')
    {
        $this->language = $language;

        return $this;
    }
}
