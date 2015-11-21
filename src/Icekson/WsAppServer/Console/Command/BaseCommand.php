<?php
/**
 * @author a.itsekson
 * @createdAt: 05.11.2015 18:38
 */

namespace Icekson\WsAppServer\Console\Command;


use Icekson\WsAppServer\Config\ConfigAwareInterface;
use Icekson\WsAppServer\Config\ConfigureInterface;
use Noodlehaus\ConfigInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Icekson\Utils\Logger;

class BaseCommand extends Command implements ConfigAwareInterface
{

    /**
     * @var ConfigInterface
     */
    private $config;
    /**
     * @var OutputInterface
     */
    protected $output = null;


    /**
     * Get service locator
     *
     * @return ConfigureInterface
     */
    public function getConfiguration()
    {
        return $this->config;
    }

    public function setConfiguration(ConfigureInterface $config)
    {
        $this->config = $config;
    }


    /**
     * @var null|LoggerInterface
     */
    private $_logger = null;

    protected function setLogger(LoggerInterface $logger)
    {
        $this->_logger = $logger;
    }

    /**+
     * @return null|LoggerInterface
     */
    protected function logger()
    {
        if($this->_logger === null) {
            $this->_logger = Logger::createLogger(get_class($this), $this->getConfiguration()->toArray());
        }

        return $this->_logger;

    }


    /**
     * @param $eventName
     * @param $userId
     * @param $data
     */
    protected function publishEvent($eventName, $userId, $data)
    {
//        if(empty($eventName)){
//            return;
//        }
//        $config = $this->getConfiguration();
//        $pubSub = new AMQPPubSub($config->toArray(), "websockets-service");
//
//        $publisherId = $this->getServiceLocator()->get('Config')['cricket']['pubSubAccessKey'];
//        if ($pubSub->isEventRegistered($eventName)) {
//            $this->logger()->info("publish event: " . $eventName . "." . $userId);
//            $pubSub->publish($eventName . ".".$userId, $publisherId, new ParamsBag($data));
//        }
    }

}