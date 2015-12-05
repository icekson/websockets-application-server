<?php
/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:50
 */

namespace Icekson\WsAppServer\Service;

use Icekson\WsAppServer\Application;
use Icekson\WsAppServer\Config\ServiceConfig;
use Icekson\WsAppServer\Rpc\Handler\RequestHandlerInterface;
use Icekson\WsAppServer\Rpc\RequestInterface;
use Icekson\WsAppServer\Rpc\RPC;

class BackendService extends AbstractService implements RequestHandlerInterface
{

    /**
     * @var null|RPC
     */
    private $rpc = null;
    public function __construct(Application $app, ServiceConfig $config)
    {
        parent::__construct($config->getName(), $config, $app);
    }
    public function getRunCmd()
    {
        return sprintf("%s scripts/runner.php app:service --type=backend --name='%s'", $this->getConfiguration()->get("php_path"), $this->getName());
    }

    public function run()
    {
        $this->rpc = new RPC(RPC::TYPE_RESPONSE, $this->getLoop(), $this, $this->getName(), $this->getConfiguration()->toArray());
        $this->getLoop()->run();

    }

    public function onRequest(RequestInterface $req)
    {
        $this->getLogger()->debug("onRequest" . $req->serialize());

        $this->rpc->sendResponse($req->getRequestId(), $req->getReplyTo(), ["someresp" => uniqid()]);


    }
}