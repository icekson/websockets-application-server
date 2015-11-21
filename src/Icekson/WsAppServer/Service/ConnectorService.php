<?php
/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:50
 */

namespace Icekson\WsAppServer\Service;

use Icekson\WsAppServer\Config\ServiceConfig;

class ConnectorService extends AbstractService
{

    public function __construct(ServiceConfig $config)
    {
        parent::__construct($config->getName(), $config);
    }

    public function getRunCmd()
    {
        return "php scripts/runner.php app:service --type=connector --name='$this->getName()'";
    }

    public function run()
    {
        while($this->isRun()){
            $this->logger->debug("connector say hello...");
            sleep(4);
        }
    }
}