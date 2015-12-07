<?php
/**
 *
 * @author: a.itsekson
 * @date: 07.12.2015 23:48
 */

namespace Icekson\Base\Auth;


use Api\Service\IdentityFinderInterface;
use Api\Service\UserIdentity;

class EmptyIdentityFinder implements IdentityFinderInterface
{
    public function getIdentity(\Api\Service\Util\Properties $params)
    {
        $id = new UserIdentity();
        $id->setId(-1);
        $id->setRoles(['guest']);
        return $id;

    }

}