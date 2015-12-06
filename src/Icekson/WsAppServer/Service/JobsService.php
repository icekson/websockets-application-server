<?php
/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:50
 */

namespace Icekson\WsAppServer\Service;

use Icekson\WsAppServer\Application;
use Icekson\WsAppServer\Config\ServiceConfig;
use Icekson\WsAppServer\Jobs\Amqp\Worker;


class JobsService extends AbstractService
{
    private $routingKey = "#";

    public function __construct(Application $app, ServiceConfig $config)
    {
        parent::__construct($config->getName(), $config, $app);
    }

    public function getRunCmd()
    {
        return sprintf("%s scripts/runner.php app:service --type=job --name='%s' --routing-key=%s", $this->getConfiguration()->get("php_path"), $this->getName(), $this->getConfiguration()->get("routing_key"));
    }

    public function setRoutingKey($routingKey)
    {
        $this->routingKey = $routingKey;
    }

    public function run()
    {

        $key = $this->routingKey;
        $config = $this->getConfiguration();
        $worker = new Worker($config, $key . "-jobs");
        $worker->consume($key);
    }


}