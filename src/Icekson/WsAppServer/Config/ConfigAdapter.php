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

class ConfigAdapter  implements ConfigureInterface
{
    /**
     * @var \ArrayObject
     */
    private $conf = null;

    public function __construct($config, $index = null)
    {
        if (is_string($config)) {
            if (!file_exists($config)) {
                throw new Exception\ConfigFileNotFoundException("Config file '$config' is not found");
            }
            $pathInfo = pathinfo($config);
            $parser = null;
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
            $conf = $parser->parse($config);
            $config = $conf;
        }
        if ($index !== null && isset($config[$index])) {
            $config = $config[$index];
        }
        $this->conf = new \ArrayObject($config);
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