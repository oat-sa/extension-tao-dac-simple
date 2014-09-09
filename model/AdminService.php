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
use oat\tao\model\accessControl\data\AclProxy;

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
    public static function setOwner($resourceUri, $userUri) {
        
        $db = new DataBaseAccess();
        
        // Needs better abstraction
        $dbRow = $db->getUsersWithPrivilege(array($resourceUri));
        foreach ($dbRow as $row) {
            if ($row['resource_id'] == $resourceUri && $row['privilege'] == 'OWNER') {
                $db->removePrivileges($row['user_id'], array($resourceUri), array('OWNER'));
            }
        }
        
        return $db->addPrivileges($userUri, $resourceUri, array('OWNER'));
    }
    
    /**
     * Get a list of users with their privileges for a resource
     * with userid as key and an array of privileges as value
     * 
     * @param string $resourceIds
     * @return array
     */
    public static function getUsersPrivileges($resourceUri)
    {
        $db = new DataBaseAccess();
        $results = $db->getUsersWithPrivilege(array($resourceUri));
    
        $privileges = array();
        foreach ($results as $result) {
            $user = $result['user_id'];
            
            if (!isset($privileges[$user])) {
                $privileges[$user] = array();
            }
            $privileges[$user][] = $result['privilege'];
        }
        
        foreach (array_keys($privileges) as $userId) {
            $privileges[$userId] = array_unique(array_intersect(AclProxy::getExistingPrivileges(), $privileges[$userId]));
        }
        
        return $privileges;
    }
}