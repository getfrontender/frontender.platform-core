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

use Mimey\MimeMappingBuilder;
use Slim\Container;
use Mimey\MimeTypes;

class Asset extends \Twig_Extension
{
    private $_container;
    private $_template;
    private $_inlineTags;
    private $_tags;

    /**
     * This registry will hold all of our loaded assets.
     * @var array
     */
    private $_assets = [];

    public function __construct(Container $container)
    {
        $this->_container = $container;
        $this->_template = $container->page->getTemplate();
        $this->_mimetypes = new MimeTypes($this->_getMimeMapping());
    }

    public function getFilters()
    {
        return [
            new \Twig_Filter('processAssets', [$this, 'processAssets']),
            new \Twig_Filter('addAsset', [$this, 'addAsset']),
            new \Twig_Filter('addScript', [$this, 'addScript']),
            new \Twig_Filter('addStyle', [$this, 'addStyle'])
        ];
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('renderAssets', [$this, 'renderAssets'])
        ];
    }

    /** ***********************/
    /******** Filters ********/
    /** ***********************/

    /**
     * This method is a little bit of a hack, we need to display block content before the head,
     * this method will help us suppress any HTML so the assets can be shown in the head.
     *
     * @return string Empty string, we don't want to show anything.
     */
    public function processAssets() : string
    {
        return '';
    }

    /**
     * This method will add an asset to a registry.
     * When the file ends with ".twig" it is send to twig for rendering,
     * when a config is given, this config will be send to twig for use in the twig file.
     *
     * When there is no ".twig" at the end, then it is a normal file and added within its corresponding tag,
     * when a config is given, these will become the attributes of the rendered tag.
     *
     * In any case, if the code finds that there is a duplicate, the last code added will not be added!
     *
     * @param string $file The file to load or twig template to parse.
     * @param array $config The config for the asset.
     * @param bool $defer To defer the asset or not.
     */
    public function addAsset(string $file, string $scope, $config = [])
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $config = $this->_mergeArrayDistinct([
            'attributes' => [],
            'config' => []
        ], $config);

        if ($extension === 'twig') {
            $key = $this->_template->loadFile($file)->render($config);
            $config['attributes']['content'] = $key;
        } else {
            $key = $file;
        }

        $key = md5($key);

		// No we will get the scope.
		// First check if we are deferred or not.
        $this->_assets[$scope] = $this->_assets[$scope] ?? [];

        if (!array_key_exists($key, $this->_assets[$scope])) {
            $this->_assets[$scope][$key] = $this->_getTag($config['config'], $config['attributes']);
        }

		// We are now done.
        return;
    }

    public function addScript($file, $scope, $config = [])
    {
        $sanitized = $this->_sanitizeFilePath($file);

        $extension = pathinfo($sanitized, PATHINFO_EXTENSION);
        $isTwig = $extension === 'twig';
        $extension = $isTwig ? pathinfo(str_replace('.twig', '', $sanitized), PATHINFO_EXTENSION) : $extension;

        $config = $this->_mergeArrayDistinct([
            'attributes' => [
                'type' => $this->_mimetypes->getMimeType($extension),
                'src' => $isTwig ? null : $file
            ],
            'config' => [
                'tag' => 'script'
            ]
        ], $config);

        return $this->addAsset($file, $scope, $config);
    }

    public function addStyle($file, $scope, $config = [])
    {
        $sanitized = $this->_sanitizeFilePath($file);

        $extension = pathinfo($sanitized, PATHINFO_EXTENSION);
        $isTwig = $extension === 'twig';
        $extension = $isTwig ? pathinfo(str_replace('.twig', '', $sanitized), PATHINFO_EXTENSION) : $extension;

        $config = $this->_mergeArrayDistinct([
            'attributes' => [
                'type' => $this->_mimetypes->getMimeType($extension),
                'href' => $isTwig ? null : $file,
                'rel' => 'stylesheet'
            ],
            'config' => [
                'tag' => 'link'
            ]
        ], $config);

        return $this->addAsset($file, $scope, $config);
    }

    /***************************/
    /******** Functions ********/
    /***************************/

    /**
     * This twig function will return a string that contains all the tags and assets that where registered.
     *
     * @return string The rendered assets string.
     */
    public function renderAssets(string $scope) : string
    {
        $result = [];

        if (array_key_exists($scope, $this->_assets)) {
            foreach ($this->_assets[$scope] as $value) {
                $result[] = $value;
            }
        }

        return implode(PHP_EOL, $result);
    }

    /*********************************/
    /******** Private methods ********/
    /*********************************/

    /**
     * This method will return the tag that is for the required (extension or mimetype).
     *
     * @return string The tag for the requested mimetype.
     */
    private function _getTag($config, $attributes) : string
    {
		// There is one special case, and that is content, if content is in there duplicate it, and remove it from the attributes.
        if (!array_key_exists('tag', $config)) {
			// we will return only the content if there.
            return $attributes['content'] ?? '';
        }

        $content = '';
        if (array_key_exists('content', $attributes)) {
            $content = $attributes['content'];
            unset($attributes['content']);
        }

        $attribs = [];
        foreach ($attributes as $key => $value) {
            if ($value) {
                $attribs[] = $key . '="' . $value . '"';
            }
        }

        return sprintf('<%1$s %2$s>%3$s</%1$s>', $config['tag'], implode(' ', $attribs), $content);
    }

    /**
     * This method will allow to add custom mimetypes to the current instance of mimey,
     * these will aid us in using the correct mimetypes for the specific extensions (application/javascript isn't correct in HTML).
     */
    private function _getMimeMapping()
    {
        $builder = MimeMappingBuilder::create();
        $builder->add('text/javascript', 'js');

        return $builder->getMapping();
    }

    /**
     * This method will strip any information off from the file name.
     * In some cases it is possible that a question mark or any other string follows the extension,
     * in this case the pathinfo function cuts short and needs a little help.
     *
     * This method will do just that, remove all the clutter and non-path information from the filepath.
     *
     * @param string $file The sanitized file name.
     */
    private function _sanitizeFilePath(string $file)
    {
        $result = parse_url($file);

        return $result['path'];
    }

    private function _mergeArrayDistinct($arr1, $arr2)
    {
        $merged = $arr1;

        foreach ($arr2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->_mergeArrayDistinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}