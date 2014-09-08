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

namespace oat\taoDacSimple\controller;

use oat\taoDacSimple\model\accessControl\data\implementation\DataBaseAccess;
use oat\tao\model\accessControl\data\AclProxy;
use oat\taoDacSimple\model\AdminService;

/**
 * Sample controller
 *
 * @author Open Assessment Technologies SA
 * @package taoDacSimple
 * @subpackage actions
 * @license GPL-2.0
 *
 */
class AccessController extends \tao_actions_CommonModule
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
     * @todo enable requiresPrivilege uri GRANT
     */
    public function index()
    {
        
        $resourceUri = $this->hasRequestParameter('uri') 
            ? \tao_helpers_Uri::decode($this->getRequestParameter('uri'))
            : \tao_helpers_Uri::decode($this->getRequestParameter('classUri'));
        $resource = new \core_kernel_classes_Resource($resourceUri);
        
        $accessRights = AdminService::getUsersPrivileges($resourceUri);
        $userList = $this->getUserList();
        $roleList = $this->getRoleList();
        
        $this->setData('privileges', AclProxy::getPrivilegeOptions());
        
        $userData = array();
        foreach (array_keys($accessRights) as $uri) {
            if (isset($userList[$uri])) {
                $userData[$uri] = array(
                    'label' => $userList[$uri],
                    'isRole' => false
                );
                unset($userList[$uri]);
            } elseif (isset($roleList[$uri])) {
                $userData[$uri] = array(
                    'label' => $roleList[$uri],
                    'isRole' => true
                );
                unset($roleList[$uri]);
            } else {
                \common_Logger::d('unknown user '.$uri);
            }
        }
        
        $this->setData('users', $userList);
        $this->setData('roles', $roleList);
        
        $this->setData('userPrivileges', $accessRights);
        $this->setData('userData', $userData);
        
        
        $this->setData('uri', $resourceUri);
        $this->setData('label', $resource->getLabel());
        
        $this->setView('AccessController/index.tpl');
    }


    /**
     * get the list of users
     * @param array $resourceIds
     * @return array key => value with key = user Uri and value = user Label
     */
    protected function getUserList()
    {
        $userService = \tao_models_classes_UserService::singleton();
        $users = array();
        foreach ($userService->getAllUsers() as $user) {
            $users[$user->getUri()] = $user->getLabel();
        }
        
        return $users;
    }

    /**
     * get the list of roles
     * @param array $resourceIds
     * @return array key => value with key = user Uri and value = user Label
     */
    protected function getRoleList()
    {
        $roleService = \tao_models_classes_RoleService::singleton();
        
        $roles = array();
        foreach ($roleService->getAllRoles() as $role) {
            $roles[$role->getUri()] = $role->getLabel();
        }

        return $roles;
    }

    /**
     * We can know if the current user have the ownership on resources
     * @param $resourceIds
     * @param $resourceClassIds
     * @return bool
     */
    protected function isCurrentUserOwner($resourceIds, $resourceClassIds)
    {
        $privileges = $this->getCurrentUserPrivileges($resourceIds, $resourceClassIds);

        $ownership = true;
        foreach ($privileges as $privilege) {
            if (!in_array('OWNER', $privilege)) {
                $ownership = false;
            }
        }
        return $ownership;
    }

    /**
     * retrieve all privileges of the current user on an array of resources
     * @param resourceIds
     * @param resourceClassIds
     * @return array
     */
    protected function getCurrentUserPrivileges($resourceIds, $resourceClassIds)
    {
        // get the current user
        $userService = \tao_models_classes_UserService::singleton();
        $user = $userService->getCurrentUser();

        return $this->getUserPrivileges($user->getUri(), $resourceIds, $resourceClassIds);
    }

    /**
     * Get the list of privileges of an user on a list of resources
     * @param $user
     * @param $resourceIds
     * @param $resourceClassIds
     * @return mixed
     */
    protected function getUserPrivileges($user, $resourceIds, $resourceClassIds)
    {
        // we will get privileges of a class we we haven't got any resourceIds
        if (empty($resourceIds)) {
            $resourceIds = $resourceClassIds;
        }

        return $this->dataAccess->getPrivileges($user, $resourceIds);
    }

    /**
     * add privileges for a group of users on resources. It works for add or modify privileges
     * @return bool
     */
    public function savePrivileges()
    {

        $users = $this->getRequest()->getParameter('users');
        $resourceIds = (array)$this->getRequest()->getParameter('resource_id');
        $resourceClassIds = (array)$this->getRequest()->getParameter('resource_class');

        // Check if there is still a owner on this resource
        if ($this->resourceHasOwner($users)) {
            \common_Logger::e('Cannot save a list of privilege without owner');
            return false;
        }

        // we will add privileges to a class we we haven't got any resourceIds
        if (empty($resourceIds)) {
            $resourceIds = $resourceClassIds;
        }

        $this->dataAccess->removeAllPrivilegesExceptOwner($resourceIds);

        foreach ($users as $user_id => $options) {
            $user_type = $options['type'];
            unset($options['type']);
            foreach ($resourceIds as $resourceId) {
                if (!empty($options)) {
                    $privileges = array_intersect(AclProxy::getExistingPrivileges(),array_keys($options));
                    try{
                        $this->dataAccess->addPrivileges($user_id, $resourceId, $privileges, $user_type);
                    }
                    catch (\PDOException $e) {
                        \common_Logger::e('Unable to add privileges : '. $e->getMessage());
                    }
                }
            }
        }

        $this->redirect(_url('index', null, null, array('uri' => reset($resourceIds))));
        //$this->index();
    }


    /**
     * Method that allow only the owner to transfer it to another user
     * @requiresPrivilege uri OWNER
     */
    public function transferOwnership()
    {
        $resourceUri = $this->getRequestParameter('uri');
        $newOwner = $this->getRequest()->getParameter('user');
        $user_type = $this->getRequest()->getParameter('user_type');
    
        $success = AdminService::setOwner($resourceUri, $newOwner, $user_type);
    
        $this->returnJson(array(
            'success' => $success
        ), $success ? 200 : 403);
    }

    /**
     * Get a list of users with their privileges for a list of resources
     * @param array $resourceIds
     * @return array
     */
    protected function getUsersPrivileges($resourceIds)
    {

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
                    'name' => $user->getLabel(),
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

    /**
     * Check if the array to save contains a owner
     * @param array $usersPrivileges
     * @return bool
     */
    protected function resourceHasOwner($usersPrivileges)
    {

        foreach ($usersPrivileges as $user => $options) {
            if (in_array('OWNER', $options)) {
                return true;
            }
        }
        return false;
    }

}
