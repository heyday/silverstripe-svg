<?php

namespace SilverStripeSVG;


use SilverStripe\View\ViewableData;
use DOMDocument;


/**
 * Class SVGTemplate
 * @package SilverStripeSVG
 */
class SVGTemplate extends ViewableData
{
    /**
     * The base path to your SVG location
     *
     * @config
     * @var string
     */
    private static $base_path = 'mysite/svg/';

    /**
     * @config
     * @var string
     */
    private static $extension = 'svg';

    /**
     * @config
     * @var array
     */
    private static $default_extra_classes = array();

    /**
     * @config
     * @var array
     */
    private static $default_extra_attribute = array();

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $fill;

    /**
     * @var string
     */
    private $stroke;

    /**
     * @var string
     */
    private $width;

    /**
     * @var string
     */
    private $height;

    /**
     * @var string
     */
    private $custom_base_path;

    /**
     * @var array
     */
    private $extraClasses;

    /**
     * @var array
     */
    private $extraAttribute;

    /**
     * @var array
     */
    private $subfolders;

    /**
     * @var string
     */
     private $isDev;

    /**
     * @param string $name
     * @param string $id
     */
    public function __construct($name, $id = '')
    {
        $this->name = $name;
        $this->id = $id;
        $this->extra_classes = $this->stat('default_extra_classes');
        $this->extra_classes[] = 'svg-' . $this->name;
        $this->extra_attribute = $this->stat('default_extra_attribute');
        $this->subfolders = array();
        $this->out = new DOMDocument();
        $this->out->formatOutput = true;
        // this is used to avoid SSL checking for self assigned certificate
        $this->isLive = (getenv('SS_ENVIRONMENT_TYPE') !== 'live') ? FALSE : TRUE;

    }

    /**
     * @param $color
     * @return $this
     */
    public function fill($color)
    {
        $this->fill = $color;
        return $this;
    }

    /**
     * @param $color
     * @return $this
     */
    public function stroke($color)
    {
        $this->stroke = $color;
        return $this;
    }

    /**
     * @param $width
     * @return $this
     */
    public function width($width)
    {
        $this->width = $width;
        return $this;
    }

    /**
     * @param $height
     * @return $this
     */
    public function height($height)
    {
        $this->height = $height;
        return $this;
    }

    /**
     * @param $width
     * @param $height
     * @return $this
     */
    public function size($width, $height)
    {
        $this->width($width);
        $this->height($height);
        return $this;
    }

    /**
     * @param $class
     * @return $this
     */
    public function customBasePath($path)
    {
        $this->custom_base_path = trim($path, DIRECTORY_SEPARATOR);
        return $this;
    }

    /**
     * @param $class
     * @return $this
     */
    public function extraClass($class)
    {
        $this->extra_classes[] = $class;
        return $this;
    }

    /**
     * @param $class
     * @return $this
     */
    public function extraAttribute($attrs)
    {
        $this->extra_attribute = $attrs;
        return $this;
    }

    /**
     * @param $class
     * @return $this
     */
    public function addSubfolder($folder)
    {
        $this->subfolders[] = trim($folder, DIRECTORY_SEPARATOR);
        return $this;
    }

    /**
     * @param $url
     * @return string
     */
     private function getResourceAsString($url)
     {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        // specify that expected result should be a string
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // if in dev environement don't check ssl certificate
        if(!$this->isLive) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        }
        $resource = curl_exec($curl);
        curl_close ($curl);

        return $resource;
     }

    /**
     * @param $filePath
     * @return string
     */
    private function process($filePath, $external)
    {
        if (!file_exists($filePath) && !$external) {
            return false;
        }

        // create dom container
        $out = new DOMDocument();

        // this is if the passed url is a remote url
        if($external == true) {
            // request the distant url and get the result as a string
            $resource = $this->getResourceAsString($filePath);
            // if there is no result just stop the process
            if(!$resource) return false;
            // load the sended resource as XML otherwise
            @$out->loadXML($resource);
        } else {
            $out->load($filePath);
        }

        if (!is_object($out) || !is_object($out->documentElement)) {
            return false;
        }

        $root = $out->documentElement;

        if ($this->fill) {
            $root->setAttribute('fill', $this->fill);
        }

        if ($this->stroke) {
            $root->setAttribute('stroke', $this->stroke);
        }

        if ($this->width) {
            $root->setAttribute('width', $this->width . 'px');
        }

        if ($this->height) {
            $root->setAttribute('height', $this->height . 'px');
        }

        if ($this->extra_classes) {
            $root->setAttribute('class', implode(' ', $this->extra_classes));
        }

        if ($this->extra_attribute) {
            $attribute = explode('/', $this->extra_attribute);
            $root->setAttribute($attribute[0], $attribute[1]);
        }

        foreach ($out->getElementsByTagName('svg') as $element) {
            if ($this->id) {
                $element->setAttribute('id', $this->id);
            } else {
                if ($element->hasAttribute('id')) {
                    $element->removeAttribute('id');
                }
            }
        }

        $out->normalizeDocument();
        return $out->saveHTML();
    }

    /**
     * @return string
     */
    public function forTemplate()
    {
        $path = '';
        $isExternal = false;

        // this check if the url is distant (start with http^)
        if (strpos($this->name, 'http') === 0) {
           $path = $this->name;
           $isExternal = true;
        } else {
            $path = BASE_PATH . DIRECTORY_SEPARATOR;
            $path .= ($this->custom_base_path) ? $this->custom_base_path : $this->stat('base_path');
            $path .= DIRECTORY_SEPARATOR;

            foreach ($this->subfolders as $subfolder) {
                $path .= $subfolder . DIRECTORY_SEPARATOR;
            }
            $path .= (strpos($this->name, ".") === false) ? $this->name . '.' . $this->stat('extension') : $this->name;
        }

        return $this->process($path, $isExternal);
    }
}
