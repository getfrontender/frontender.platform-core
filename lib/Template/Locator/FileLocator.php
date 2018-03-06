<?php
/**
 * @package     Dipity
 * @copyright   Copyright (C) 2014 - 2017 Dipity B.V. All rights reserved.
 * @link        http://www.dipity.eu
 */

namespace Frontender\Core\Template\Locator;

use Symfony\Component\Filesystem\Filesystem;

class FileLocator
{
    protected $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function locate($path)
    {
        if (!$this->filesystem->exists($path))
        {
            throw new \Exception(sprintf('File cannot be found at: %s', $path));
        }

        return $path;
    }
}
