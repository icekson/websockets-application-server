<?php
/**
 * @author a.itsekson
 * @createdAt: 20.11.2015 10:51
 */

namespace Icekson\WsAppServer\Jobs;


interface QueueInterface
{

    /**
     * @param $jobClassName
     * @param array $params
     * @param string $routingKey
     * @return mixed
     */
    public function enqueue($jobClassName, $params = [], $routingKey = JobInterface::JOB_MATCH);

}