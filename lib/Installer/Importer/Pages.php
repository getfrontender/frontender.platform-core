<?php

namespace Frontender\Core\Installer\Importer;

class Pages extends Generic
{
    public function import($collection, $path)
    {
        $pages = $this->getFiles($path);
        
    }
}
