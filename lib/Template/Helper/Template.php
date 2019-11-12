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
            new \Twig_SimpleFunction('getTemplatePath', [$this, 'getTemplatePath']),
            new \Twig_SimpleFunction('isPreview', [$this, 'isPreview']),
            new \Twig_SimpleFunction('isFrontender', [$this, 'isFrontender'])
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
        $scope = $this->container->scope ?? false;
        $proxyPath = null;
        $basePath = $this->container->settings['template']['path'];

        if ($scope && isset($scope['path']) && !empty($scope['path'])) {
            $proxyPath = $scope['path'];
        }

        $path = $this->_joinPaths($templatePath, $proxyPath, $fileName . '.' . $format . '.twig');
        if ($proxyPath && file_exists('/' . $this->_joinPaths($basePath, $path))) {
            return $path;
        }

        return $this->_joinPaths($templatePath, $fileName . '.' . $format . '.twig');
    }

    /**
     * This method will check if the current request isn't from frontender desktop but is a preview url.
     * The preview url is identified by ?preview. If this is located in the query then it is a preview link.
     * However the fromFrontender must not be in the url else it will be assumed it is a request from frontender.
     */
    public function isPreview()
    {
        $query = $this->container->request->getQueryParams();
        return !isset($query['fromFrontender']) && isset($query['preview']);
    }

    /**
     * This method will check if the url is requested via frontender.
     * A frontender preview url is identified by ?fromFrontender.
     */
    public function isFrontender()
    {
        $query = $this->container->request->getQueryParams();
        return isset($query['fromFrontender']);
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