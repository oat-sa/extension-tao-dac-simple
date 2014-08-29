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
     * @return array list of users
     */
    public function getUsersWithPrivilege($resourceIds)
    {
        $inQuery = implode(',', array_fill(0, count($resourceIds), '?'));
        $query = "SELECT resource_id, privilege, user_id, user_type FROM " . self::TABLE_PRIVILEGES_NAME . " WHERE resource_id IN ($inQuery)";

        /** @var \PDOStatement $statement */
        $statement = $this->persistence->query($query, $resourceIds);
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
     * @param  array $resourceId
     * @return mixed
     */
    public function getPrivileges($user, array $resourceIds)
    {
        // get User roles
        // get privileges for a user/roles and a resource
        $returnValue = array();

        $inQuery = implode(',', array_fill(0, count($resourceIds), '?'));
        $query = "SELECT resource_id, privilege FROM " . self::TABLE_PRIVILEGES_NAME . " WHERE resource_id IN ($inQuery) AND user_id = ?";

        /** @var \PDOStatement $statement */
        $resourceIds[] = $user;
        $statement = $this->persistence->query($query, $resourceIds);
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
     * @return boolean
     */
    public function addPrivileges($user, $resourceId, $privileges)
    {
        // remove all user privileges on this resource to have a clean start
        $this->removePrivileges($user, $resourceId);

        foreach ($privileges as $privilege) {
            // add a line with user URI, resource Id and privilege
            $this->persistence->insert(
                self::TABLE_PRIVILEGES_NAME,
                array('user_id' => $user, 'resource_id' => $resourceId, 'privilege' => $privilege, 'user_type' => 'user')
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
     * @param  string resourceId
     * @return boolean
     */
    public function removePrivileges($user, $resourceId)
    {
        //get all entries that match (user,resourceId) and remove them
        $query = "DELETE FROM " . self::TABLE_PRIVILEGES_NAME . " WHERE user_id = ? AND resource_id = ?";
        $params = array($user, $resourceId);
        $this->persistence->exec($query, $params);

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
        //get all entries that match (user,resourceId) and remove them
        $inQuery = implode(',', array_fill(0, count($resourceIds), '?'));
        $query = "DELETE FROM " . self::TABLE_PRIVILEGES_NAME . " WHERE resource_id IN ($inQuery)";
        $this->persistence->exec($query, $resourceIds);

        return true;
    }

}

?>