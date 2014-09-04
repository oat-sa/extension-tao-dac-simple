<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2009-2012 (original work) Public Research Centre Henri Tudor (under the project TAO-SUSTAIN & TAO-DEV);
 *
 *
 */

namespace oat\taoDacSimple\model;

use oat\taoDacSimple\model\accessControl\data\implementation\DataBaseAccess;

/**
 * Service to administer the privileges
 * 
 * @author Joel Bout <joel@taotesting.com>
 */
class AdminService
{
    /**
     * Set a new Owener, removing the old owner(s)
     * 
     * @param string $resourceUri
     * @param string $userUri
     * @param string $userType
     * @return boolean
     */
    public static function setOwner($resourceUri, $userUri, $userType) {
        
        $db = new DataBaseAccess();
        
        // should remove all other owners
        $users = $db->getUsersWithPrivilege(array($resourceUri));
        foreach ($users as $oldOwner) {
            $db->removePrivileges($oldOwner, array($resourceUri), array('OWNER'));
        }
        
        return $db->addPrivileges($userUri, array($resourceUri), array('OWNER'), $userType);
    }
}