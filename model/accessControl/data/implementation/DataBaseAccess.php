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

namespace oat\taoDacSimple\model\accessControl\data\implementation;

use oat\tao\model\accessControl\data\DataAccessControl;

class DataBaseAccess
    implements DataAccessControl
{
    // --- ASSOCIATIONS ---


    // --- ATTRIBUTES ---


    private $persistence = null;

    const TABLE_PRIVILEGES_NAME = 'data_privileges';

    // --- OPERATIONS ---

    public function __construct()
    {
        $this->persistence = \common_persistence_Manager::getPersistence('default');
    }

    /**
     * We can know which users have a privilege on a resource
     * @param array $resourceIds
     * @param array $userType ('role' or 'user' or both)
     * @return array list of users
     */
    public function getUsersWithPrivilege($resourceIds, $userType = array('user','role'))
    {
        $inQuery = implode(',', array_fill(0, count($resourceIds), '?'));
        $inQueryType = implode(',', array_fill(0, count($userType), '?'));
        $query = "SELECT resource_id, user_id, privilege, user_type FROM " . self::TABLE_PRIVILEGES_NAME . "
        WHERE resource_id IN ($inQuery) AND user_type IN ($inQueryType)";
        /** @var \PDOStatement $statement */
        $params = array_merge($resourceIds, $userType);
        $statement = $this->persistence->query($query, $params);
        $results = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $results;
    }

    /**
     * Find resources that a user can access (write or grant)
     * @param string $user
     * @param array $privileges
     * @return array list of resources
     */
    public function getResourcesForUser($user, $privileges)
    {
        $returnValue = array();
        foreach ($privileges as $privilege) {
            $query = "SELECT resource_id FROM " . self::TABLE_PRIVILEGES_NAME . " WHERE user_id = :user_id AND privilege = :privilege";
            $params = array('privilege' => $privilege, 'user_id' => $user);

            /** @var \PDOStatement $statement */
            $statement = $this->persistence->query($query, $params);
            $returnValue[$privilege] = $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
        }
        return $returnValue;
    }

    /**
     * Short description of method getPrivileges
     *
     * @access public
     * @author Antoine Robin <antoine.robin@vesperiagroup.com>
     * @param  string $user
     * @param  array $resourceIds
     * @return mixed
     */
    public function getPrivileges($user, array $resourceIds)
    {
        // get User roles
//        $userService = \tao_models_classes_UserService::singleton();
//        $userObj = $userService->getOneUser($user);
//        $roles = $userService->getUserRoles($userObj);
        $roles[] = $user;
        // get privileges for a user/roles and a resource
        $returnValue = array();

        $inQueryResource = implode(',', array_fill(0, count($resourceIds), '?'));
        $inQueryUser = implode(',', array_fill(0, count($roles), '?'));
        $query = "SELECT resource_id, privilege FROM " . self::TABLE_PRIVILEGES_NAME . " WHERE resource_id IN ($inQueryResource) AND user_id IN ($inQueryUser)";

        /** @var \PDOStatement $statement */
        $params = array_merge($resourceIds,$roles);
        $statement = $this->persistence->query($query, $params);
        $results = $statement->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($results as $result) {
            $returnValue[$result['resource_id']][] = $result['privilege'];
        }

        return $returnValue;
    }

    /**
     * Short description of method addPrivileges
     *
     * @access public
     * @author Antoine Robin <antoine.robin@vesperiagroup.com>
     * @param  string $user
     * @param  string $resourceId
     * @param array $privileges
     * @param string $user_type
     * @return boolean
     */
    public function addPrivileges($user, $resourceId, $privileges, $user_type)
    {

        foreach ($privileges as $privilege) {
            // add a line with user URI, resource Id and privilege
            $this->persistence->insert(
                self::TABLE_PRIVILEGES_NAME,
                array('user_id' => $user, 'resource_id' => $resourceId, 'privilege' => $privilege, 'user_type' => $user_type)
            );
        }
        return true;
    }

    /**
     * Short description of method removePrivileges
     *
     * @access public
     * @author Antoine Robin <antoine.robin@vesperiagroup.com
     * @param  string $user
     * @param  array $resourceIds
     * @return boolean
     */
    public function removeUserPrivileges($user, $resourceIds)
    {
        //get all entries that match (user,resourceId) and remove them
        $inQuery = implode(',', array_fill(0, count($resourceIds), '?'));
        $query = "DELETE FROM " . self::TABLE_PRIVILEGES_NAME . " WHERE resource_id IN ($inQuery) AND user_id = ?";
        $resourceIds[] = $user;
        $this->persistence->exec($query, $resourceIds);

        return true;
    }

    /**
     * Short description of method removePrivileges
     *
     * @access public
     * @author Antoine Robin <antoine.robin@vesperiagroup.com
     * @param  string $user
     * @param  array $resourceIds
     * @param  array $privileges
     * @return boolean
     */
    public function removePrivileges($user, $resourceIds, $privileges)
    {
        //get all entries that match (user,resourceId) and remove them
        $inQueryResource = implode(',', array_fill(0, count($resourceIds), '?'));
        $inQueryPrivilege = implode(',', array_fill(0, count($resourceIds), '?'));
        $query = "DELETE FROM " . self::TABLE_PRIVILEGES_NAME . " WHERE resource_id IN ($inQueryResource) AND privilege IN ($inQueryPrivilege) AND user_id = ?";
        $params = array_merge($resourceIds,$privileges);
        $params[] = $user;
        $this->persistence->exec($query, $resourceIds);

        return true;
    }

    /**
     * Short description of method removeAllPrivileges
     *
     * @access public
     * @author Antoine Robin <antoine.robin@vesperiagroup.com
     * @param  array $resourceIds
     * @return boolean
     */
    public function removeAllPrivileges($resourceIds)
    {
        //get all entries that match (resourceId) and remove them
        $inQuery = implode(',', array_fill(0, count($resourceIds), '?'));
        $query = "DELETE FROM " . self::TABLE_PRIVILEGES_NAME . " WHERE resource_id IN ($inQuery)";
        $this->persistence->exec($query, $resourceIds);

        return true;
    }

    /**
     * Short description of method removeAllPrivilegesExceptOwner
     *
     * @access public
     * @author Antoine Robin <antoine.robin@vesperiagroup.com
     * @param  array $resourceIds
     * @return boolean
     */
    public function removeAllPrivilegesExceptOwner($resourceIds)
    {
        //get all entries that match (resourceId) and remove them
        $inQuery = implode(',', array_fill(0, count($resourceIds), '?'));
        $query = "DELETE FROM " . self::TABLE_PRIVILEGES_NAME . " WHERE resource_id IN ($inQuery)
        AND user_id NOT IN (SELECT user_id FROM (SELECT * FROM " . self::TABLE_PRIVILEGES_NAME . ") AS d
        WHERE d.privilege = 'OWNER' AND d.resource_id IN ($inQuery))";
        $params = array_merge($resourceIds, $resourceIds);
        return $this->persistence->exec($query, $params);

    }

}

?>