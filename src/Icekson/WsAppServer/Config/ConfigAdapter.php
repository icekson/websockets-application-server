<?php
/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:19
 */

namespace Icekson\WsAppServer\Config;


use Icekson\Utils\IArrayExchange;
use Icekson\WsAppServer\Config\Exception;
use Noodlehaus\ConfigInterface;
use Noodlehaus\FileParser\Ini;
use Noodlehaus\FileParser\Json;
use Noodlehaus\FileParser\Php;
use Noodlehaus\FileParser\Yaml;

class ConfigAdapter implements ConfigureInterface
{

    private $supportedExtensions = [
        'php',
        'json',
        'yml',
        'ini'
    ];
    /**
     * @var \ArrayObject
     */
    private $conf = null;

    public function __construct($config, $index = null)
    {
        $config = $this->parse($config, $index);
        $this->conf = new \ArrayObject($config);
    }

    private function parse($config, $index){
        $resConf = [];
        if (is_string($config)) {
            $config = [$config];
        }
        $isArrayConfig = false;
        foreach ($config as $item) {
            if(!is_string($item)){
                $isArrayConfig = true;
                break;
            }
        }

        if (!$isArrayConfig) {
            foreach ($config as $conf) {
                if(is_string($conf)) {
                    if (is_dir($conf)) {
                        $iterator = new \DirectoryIterator($conf);
                        $res = [];
                        foreach ($iterator as $file) {
                            if ($file->isFile()) {
                                $res = $this->merge($res, $this->_parse($file->getRealPath()));
                            }
                        }
                        $resConf = $this->merge($resConf, $res);
                    } else {
                        $conf = $this->_parse($conf);
                        $resConf = $this->merge($resConf, $conf);
                    }
                }else{
                    $resConf = $this->merge($resConf, $conf);
                }
            }
        }else{
            $resConf = $config;
        }

        if ($index !== null && isset($resConf[$index])) {
            $resConf = $resConf[$index];
        }
        return $resConf;
    }

    private function _parse($config)
    {
        if (!file_exists($config)) {
            throw new Exception\ConfigFileNotFoundException("Config file '$config' is not found");
        }
        $pathInfo = pathinfo($config);
        $parser = null;
        if(!in_array($pathInfo['extension'], $this->supportedExtensions)){
            return [];
        }
        switch ($pathInfo['extension']) {
            case "php":
                $parser = new Php();
                break;
            case "yml":
                $parser = new Yaml();
                break;
            case "json":
                $parser = new Json();
                break;
            case "ini":
                $parser = new Ini();
                break;
            default:
                throw new Exception\UnsupportedFormatException("Unsupported config file format");
        }
        $localConfigFile = $pathInfo['dirname'] . "/" . preg_replace("/\.global/", "", $pathInfo['filename']) . ".local." . $pathInfo['extension'];
        if (file_exists($localConfigFile)) {
            $localConf = $parser->parse($localConfigFile);
        } else {
            $localConf = [];
        }
        $conf = $parser->parse($config);
        $config = $this->merge($conf, $localConf);
        return $config;
    }

    private function merge($conf, $localConf)
    {
        $res = array_replace_recursive($conf, $localConf);
        return $res;

    }


    /**
     * @param string $key
     * @param null|mixed $default
     * @return mixed|null
     */
    public function get($key, $default = null)
    {
        if (isset($this->conf[$key])) {
            return $this->conf[$key];
        }
        return $default;
    }

    public function toArray()
    {
        return $this->conf->getArrayCopy();
    }

    public function fromArray(array $data)
    {
        $this->conf = new \ArrayObject($data);
    }


}