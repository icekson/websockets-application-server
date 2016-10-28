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
use Icekson\WsAppServer\Service\Support\BackendDispatcherInterface;
use Icekson\WsAppServer\Service\Support\PostDispatchInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class SimpleBackendService extends BackendService implements RequestHandlerInterface
{
    /** @var null|ServiceLocatorInterface */
    private $sm = null;
    /** @var null|RPC */
    private $rpc = null;

    private $publisherIds = [];


    private $poolSize = 10;
    /**
     * @var null|\SplQueue
     */
    private $pool = null;

    /**
     * @var null|\SplQueue
     */
    private $waiting = null;

    /** @var \ArrayObject|null  */
    private $waitingIndexes = null;

    private $timeout = 90;

    /**
     * @param Application $app
     * @param ServiceConfig $config
     */
    public function __construct(Application $app, ServiceConfig $config) {
        parent::__construct($app, $config);
        $this->pool = new \SplQueue();
        $this->waiting = new \SplQueue();
        $this->poolSize = $this->getConfiguration()->get('processes_limit', 10);
    }

    public function run() {
        $loop = $this->getLoop();
        $this->rpc = new RPC(RPC::TYPE_RESPONSE, $this->getLoop(), $this, $this->getName(), $this->getConfiguration()->toArray());

        $this->getLoop()->addPeriodicTimer(0.01, function(){
            $this->checkWaitingWaitingQueue();
        });

        $this->getLoop()->addPeriodicTimer(0.1, function(){
            $this->processRequestFromPool();
        });

        $loop->run();
    }


    public function onRequest(RequestInterface $req) {
        $this->getLogger()->debug("onRequest" . $req->serialize());
        $this->dispatch($req);
    }


    public function dispatch(RequestInterface $req)
    {
        if($this->pool->count() > $this->poolSize){
            $this->waiting->enqueue($req);
        }else{
            $this->pool->enqueue($req);
        }
    }

    private function processRequestFromPool()
    {
        try {
            $req = $this->pool->dequeue();
        }catch (\Exception $ex){
            $req = null;
        }
        if($req instanceof RequestInterface){
            $cmd = $this->getConfiguration()->get('php_path', 'php') .  ' scripts/runner.php backend:rpc --backend-service='.$this->getName().' --request=' . base64_encode($req->serialize());
            $process = new \React\ChildProcess\Process($cmd);
            $process->start($this->getLoop());
        }
    }

    private function checkWaitingWaitingQueue()
    {
        //$this->getLogger()->debug("check waiting queue, count of waiting is " . $this->waiting->count() . ", pool: " . $this->pool->count());
        if($this->pool->count() > $this->poolSize){
            return false;
        }
        if($this->waiting->count() == 0){
            return false;
        }
        /** @var RequestInterface $req */
        $req = $this->waiting->dequeue();
        $this->pool->enqueue($req);

        return true;
    }
}