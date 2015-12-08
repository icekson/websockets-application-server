<?php
/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:50
 */

namespace Icekson\WsAppServer\Service;

use Icekson\WsAppServer\Application;
use Icekson\WsAppServer\Config\ServiceConfig;
use Icekson\WsAppServer\Rpc\RequestInterface;
use Icekson\WsAppServer\Rpc\RPC;
use Icekson\WsAppServer\Service\Support\BackendDispatcherInterface;
use Icekson\WsAppServer\Service\Support\PostDispatchInterface;

abstract class BackendService extends AbstractService implements BackendDispatcherInterface
{
    /**
     * @var \ArrayObject|null
     */
    protected $postDispatchCallbacks = null;

    public function __construct(Application $app, ServiceConfig $config)
    {
        parent::__construct($config->getName(), $config, $app);
        $this->postDispatchCallbacks = new \ArrayObject();
    }
    public function getRunCmd()
    {
        return sprintf("%s scripts/runner.php app:service --type=backend --name='%s' --config-path='%s'", $this->getConfiguration()->get("php_path"), $this->getName(), $this->getConfigPath());
    }

    public function run(){}

    abstract public function dispatch(RequestInterface $request);

    /**
     * @param PostDispatchInterface $post
     * @return $this
     */
    public function registerPostDispatcher(PostDispatchInterface $post)
    {
        $this->postDispatchCallbacks->append($post);
        return $this;
    }
}