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
