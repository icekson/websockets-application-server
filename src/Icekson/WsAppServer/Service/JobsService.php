<?php
/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:50
 */

namespace Icekson\WsAppServer\Service;

use Icekson\WsAppServer\Config\ServiceConfig;

class JobsService extends AbstractService
{

    public function __construct(ServiceConfig $config)
    {

    }

    public function getRunCmd()
    {
        return "php scripts/runner.php app:service --type=job --name='$this->getName()'";
    }


}