<?php
/**
 * @author a.itsekson
 * @createdAt: 07.11.2015 15:41
 */

namespace Icekson\WsAppServer\Queue;


interface JobInterface
{
    const JOB_MATCH = 'match';
    const JOB_TOURNAMENT_GENERATE = 'tournaments-generate';
    const JOB_TOURNAMENT_RUN = 'tournaments-run';

    public function perform($params);

}