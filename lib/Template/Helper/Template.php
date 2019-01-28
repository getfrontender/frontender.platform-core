<?php

namespace Frontender\Core\Template\Helper;

use Slim\Container;

class Template extends \Twig_Extension
{
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('getTemplatePath', [$this, 'getTemplatePath'])
        ];
    }

    /**
     * This function will try to retreive a template path based on proxy domain or not.
     * 
     * This function will check in the template_path if a directory is found with the same name as the proxy_path in the scope configuration.
     * If this directory doesn't exists, or the file name in this path doesn't exist, we will try to find the default file.
     * 
     * If the default file is missing or found, we will return the default template path.
     * 
     * In the event that the file is missing, then twig will trigger an error.
     * 
     * @param string $template_path The directory to look in.
     * @param string $filename The filename to search in the directory.
     * @param string $format The format of the file to find (default: html).
     * @return string The found template path.
     */
    public function getTemplatePath(string $templatePath, string $fileName, string $format = 'html') : string
    {
        $scope = $this->container->scope;
        $proxyPath = null;
        $basePath = $this->container->settings['template']['path'];

        if ($scope && isset($scope['proxy_path']) && !empty($scope['proxy_path'])) {
            $proxyPath = $scope['proxy_path'];
        }

        $path = $this->_joinPaths($templatePath, $proxyPath, $fileName . '.' . $format . '.twig');
        if ($proxyPath && file_exists('/' . $this->_joinPaths($basePath, $path))) {
            return $path;
        }

        return $this->_joinPaths($templatePath, $fileName . '.' . $format . '.twig');
    }

    private function _joinPaths() : string
    {
        $args = func_get_args();
        $paths = array_map(function ($p) {
            return trim($p, "/");
        }, $args);
        $paths = array_filter($paths);
        return join('/', $paths);
    }
}