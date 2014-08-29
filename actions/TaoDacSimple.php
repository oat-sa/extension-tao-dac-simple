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
 * Copyright (c) 2014 (original work) Open Assessment Technologies SA;
 *
 *
 */

namespace oat\taoDacSimple\actions;

use oat\taoDacSimple\model\accessControl\data\implementation\DataBaseAccess;
use oat\tao\model\accessControl\data\AclProxy;

/**
 * Sample controller
 *
 * @author Open Assessment Technologies SA
 * @package taoDacSimple
 * @subpackage actions
 * @license GPL-2.0
 *
 */
class TaoDacSimple extends \tao_actions_CommonModule
{

    private $dataAccess = null;

    /**
     * initialize the services
     */
    public function __construct()
    {
        parent::__construct();
        $this->dataAccess = new DataBaseAccess();
    }

    /**
     * A possible entry point to tao
     */
    public function index() {
        $this->setView('sample.tpl');
        // Mock DATA
        $this->setData('items', array(
            'http://tao.local/mytao.rdf#i140923366823805' => array (
                'resource' => array (
                    'id'  => 'http://tao.local/mytao.rdf#i140923366823805',
                    'label'  => 'my Item'
                ),
                'users' => array (
                    'http://tao.local/mytao.rdf#superUser' => array (
                        'id' => 'http://tao.local/mytao.rdf#superUser',
                        'name' => 'Me',
                        'type' => 'User',
                        'permissions' => array (
                            'GRANT' => true,
                            'OWNER' => true,
                            'WRITE' => true )
                    ),
                    'http://tao.local/mytao.rdf#i23523452345234' => array (
                        'id' => 'http://tao.local/mytao.rdf#i23523452345234',
                        'name' => 'John Doe',
                        'type' => 'User',
                        'permissions' => array (
                            'GRANT' => false,
                            'OWNER' => false,
                            'WRITE' => true )
                    ),
                )
            )
        ));
    }

    /**
     * get the list of users in Tao
     * @return array key => value with key = user Uri and value = user Label
     */
    public function getUserList()
    {
        $userService = \tao_models_classes_UserService::singleton();
        $users = $userService->getAllUsers();
        $returnUsers = array();
        foreach ($users as $u) {
            $returnUsers[$u->getUri()] = $u->getLabel();
        }
        return $returnUsers;
    }

    /**
     * get the list of roles in Tao
     * @return array key => value with key = role Uri and value = role Label
     */
    public function getRoleList()
    {

        $roleService = \tao_models_classes_RoleService::singleton();
        $roles = $roleService->getAllRoles();
        $returnRoles = array();
        foreach ($roles as $r) {
            $returnRoles[$r->getUri()] = $r->getLabel();
        }

        return $returnRoles;
    }

    /**
     * retrieve all privileges of an user on an array of resources
     * @param resourceIds
     * @param resourceClassIds
     * @return array
     */
    public function getPrivileges($resourceIds, $resourceClassIds)
    {
        // get the current user
        $userService = \tao_models_classes_UserService::singleton();
        $user = $userService->getCurrentUser();

        // we will get privileges of a class we we haven't got any resourceIds
        if (empty($resourceIds)) {
            $resourceIds = $resourceClassIds;
        }

        return $this->dataAccess->getPrivileges($user->getUri(), $resourceIds);
    }

    /**
     * TODO : See with thibault the exact definition of this method
     * add privileges for a group of users on resources. It works for add or modify privileges
     * @param usersPrivileges
     * @param resourceIds
     * @param resourceClassIds
     * @return bool
     */
    public function savePrivileges($usersPrivileges, $resourceIds, $resourceClassIds)
    {
        $addCompleted = true;

        // we will add privileges to a class we we haven't got any resourceIds
        if (empty($resourceIds)) {
            $resourceIds = $resourceClassIds;
        }

        $this->dataAccess->removeAllPrivileges($resourceIds);

        foreach ($usersPrivileges as $user => $privileges) {
            foreach ($resourceIds as $resourceId) {
                $addCompleted = $this->dataAccess->addPrivileges($user, $resourceId, $privileges);
                if (!$addCompleted) {
                    break;
                }
            }
        }
        return $addCompleted;
    }

    /**
     * Get a list of users with their privileges for a list of resources
     * @param array $resourceIds
     * @param array $resourceClassIds
     * @return array
     */
    public function getUsersPrivileges($resourceIds, $resourceClassIds)
    {
        if (empty($resourceIds)) {
            $resourceIds = $resourceClassIds;
        }

        $results = $this->dataAccess->getUsersWithPrivilege($resourceIds);

        $returnValue = array();
        foreach ($results as $result) {
            $item = new \core_kernel_classes_Resource($result['resource_id']);
            $user = new \core_kernel_classes_Resource($result['user_id']);
            if (!isset($returnValue[$item->getUri()]['resource'])) {
                $returnValue[$item->getUri()]['resource'] = array(
                    'id' => $item->getUri(),
                    'label' => $item->getLabel()
                );
            }
            if (!isset($returnValue[$item->getUri()]['users'][$user->getUri()])) {
                $returnValue[$item->getUri()]['users'][$user->getUri()] = array(
                    'id' => $user->getUri(),
                    'name' => $user->getUri(),
                    'type' => $result['user_type']
                );
            }
            if (!isset($returnValue[$item->getUri()]['users'][$user->getUri()]['permissions'][$result['privilege']])) {
                $returnValue[$item->getUri()]['users'][$user->getUri()]['permissions'][$result['privilege']] = true;
            }
        }

        foreach ($returnValue as $resourceId => $value) {
            foreach ($value['users'] as $userId => $user) {
                $nonExistingkeys = array_diff(AclProxy::getExistingPrivileges(), array_keys($user['permissions']));
                foreach ($nonExistingkeys as $key) {
                    $returnValue[$resourceId]['users'][$userId]['permissions'][$key] = false;
                }
            }

        }

        return $returnValue;

    }
    public function templateExample(){}
}
