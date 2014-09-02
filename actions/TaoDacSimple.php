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

        $resourceIds = (array)$this->getRequest()->getParameter('resource');
        $resourceClassIds = (array)$this->getRequest()->getParameter('resource_class');

        // we will get privileges of a class we we haven't got any resourceIds
        if (empty($resourceIds)) {
            $resourceIds = $resourceClassIds;
        }

        //Mocked Data
        $this->setData('users', $this->getUserList(array('http://tao.local/mytao.rdf#i140923366823805')));
        $this->setData('roles', $this->getRoleList(array('http://tao.local/mytao.rdf#i140923366823805')));
        $this->setData('items', $this->getUsersPrivileges(array('http://tao.local/mytao.rdf#i140923366823805')));
    }


    /**
     * get the list of users that have no privileges on resources
     * @param array $resourceIds
     * @return array key => value with key = user Uri and value = user Label
     */
    public function getUserList($resourceIds)
    {
        $userService = \tao_models_classes_UserService::singleton();
        $users = $userService->getAllUsers();

        return $this->getCleanedList($users, $resourceIds, 'user');
    }

    /**
     * get the list of roles that have no privileges on resources
     * @param array $resourceIds
     * @return array key => value with key = user Uri and value = user Label
     */
    public function getRoleList($resourceIds)
    {
        $roleService = \tao_models_classes_RoleService::singleton();
        $roles = $roleService->getAllRoles();

        return $this->getCleanedList($roles, $resourceIds, 'role');
    }


    /**
     * Clean a list of users or roles / return only users or roles that have no privileges on resources
     * @param $list
     * @param $resourceIds
     * @param $userType
     * @return array users or roles
     */
    public function getCleanedList($list, $resourceIds, $userType){
        $usersWithPrivileges = $this->dataAccess->getUsersWithPrivilege($resourceIds, $userType);
        foreach($usersWithPrivileges as $row){
            if(array_key_exists($row['user_id'], $list)){
                unset($list[$row['user_id']]);
            }
        }
        $returnList = array();
        foreach ($list as $l) {
            $returnList[$l->getUri()] = $l->getLabel();
        }

        return $returnList;
    }

    /**
     * We can know if the current user have the ownership on resources
     * @return bool
     */
    public function isCurrentUserOwner(){
        $resourceIds = (array)$this->getRequest()->getParameter('resource');
        $resourceClassIds = (array)$this->getRequest()->getParameter('resource_class');

        $privileges = $this->getCurrentUserPrivileges($resourceIds,$resourceClassIds);

        $ownership = true;
        foreach($privileges as $privilege){
            if(!in_array('OWNER', $privilege)){
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
    public function getCurrentUserPrivileges($resourceIds, $resourceClassIds)
    {
        // get the current user
        $userService = \tao_models_classes_UserService::singleton();
        $user = $userService->getCurrentUser();


        return $this->getUserPrivileges($user->getUri(), $resourceIds, $resourceClassIds);
    }

    /**
     * set privileges to some resources to the current user
     * @param $resourceIds
     * @param $resourceClassIds
     * @param $privileges
     * @return array
     */
    public function setCurrentUserPrivileges($resourceIds, $resourceClassIds, $privileges)
    {
        // get the current user
        $userService = \tao_models_classes_UserService::singleton();
        $user = $userService->getCurrentUser();

        // we will get privileges of a class we we haven't got any resourceIds
        if (empty($resourceIds)) {
            $resourceIds = $resourceClassIds;
        }

        return $this->dataAccess->addPrivileges($user->getUri(), $resourceIds, $privileges, 'user');
    }

    /**
     * Get the list of privileges of an user on a list of resources
     * @return mixed
     */
    public function getUserPrivileges(){

        $user = (array)$this->getRequest()->getParameter('user');
        $resourceIds = (array)$this->getRequest()->getParameter('resource');
        $resourceClassIds = (array)$this->getRequest()->getParameter('resource_class');

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

        $users = $this->getRequest()->getParameter('user');
        $resourceIds = (array)$this->getRequest()->getParameter('resource');
        $resourceClassIds = (array)$this->getRequest()->getParameter('resource_class');

        // we check if the current user can add or remove privileges
        $currentUserPrivileges = $this->getCurrentUserPrivileges($resourceIds,$resourceClassIds);

        // we will add privileges to a class we we haven't got any resourceIds
        if (empty($resourceIds)) {
            $resourceIds = $resourceClassIds;
        }

        foreach($resourceIds as $resourceId){
            if(! $currentUserPrivileges[$resourceId]['GRANT'] && !$currentUserPrivileges[$resourceId]['OWNER']){
                throw new \Exception("The user can't give privileges to another");
            }

        }

        try {
            // First of all, let's begin a transaction

            $this->dataAccess->getPersistence()->getDriver()->beginTransaction();

            $this->dataAccess->removeAllPrivilegesExceptOwner($resourceIds);

            foreach ($users as $user_id => $options) {
                $user_type = $options['type'];
                unset($options['type']);
                foreach($resourceIds as $resourceId){
                    if(!empty($options)){
                        $addCompleted = $this->dataAccess->addPrivileges($user_id, $resourceId, array_keys($options), $user_type);
                        if (!$addCompleted) {
                            return false;
                        }

                    }
                }
            }
            // If we arrive here, it means that no exception was thrown
            // i.e. no query has failed, and we can commit the transaction
            $this->dataAccess->getPersistence()->getDriver()->commit();
        } catch (\Exception $e) {
            // An exception has been thrown
            // We must rollback the transaction
            $this->dataAccess->getPersistence()->getDriver()->rollback();
        }

        return true;
    }


    /**
     * Method that allow only the owner to transfer it to another user
     */
    public function transferOwnership(){
        $newOwner = (array)$this->getRequest()->getParameter('user');
        $resourceIds = (array)$this->getRequest()->getParameter('resource');
        $resourceClassIds = (array)$this->getRequest()->getParameter('resource_class');
        $user_type = $this->getRequest()->getParameter('user_type');

        $userService = \tao_models_classes_UserService::singleton();
        $user = $userService->getCurrentUser();

        if (empty($resourceIds)) {
            $resourceIds = $resourceClassIds;
        }

        if($this->isCurrentUserOwner($resourceIds,$resourceClassIds)){
            try{
                $this->dataAccess->getPersistence()->getDriver()->beginTransaction();

                $this->dataAccess->removePrivileges($user->getUri(),$resourceIds, array('OWNER'));
                $this->dataAccess->addPrivileges($newOwner, $resourceIds, array('OWNER'), $user_type);

                $this->dataAccess->getPersistence()->getDriver()->commit();
            }
            catch(\Exception $e){
                $this->dataAccess->getPersistence()->getDriver()->rollback();
            }
        }

    }

    /**
     * Remove all privileges for a list of users on a list of resources
     * @return bool
     */
    public function removePrivileges(){

        $users = (array)$this->getRequest()->getParameter('users');
        $resourceIds = (array)$this->getRequest()->getParameter('resource');
        $resourceClassIds = (array)$this->getRequest()->getParameter('resource_class');

        if (empty($resourceIds)) {
            $resourceIds = $resourceClassIds;
        }

        foreach($users as $user){
            $resourcePrivileges = $this->getUserPrivileges($user,$resourceIds,$resourceClassIds);
            foreach($resourcePrivileges as $resourcePrivilege){
                if(in_array('OWNER',$resourcePrivilege)){
                    return false;
                }
            }
            $this->dataAccess->removeUserPrivileges($user, $resourceIds);
        }

        return true;
    }


    /**
     * Get a list of users with their privileges for a list of resources
     * @param array $resourceIds
     * @return array
     */
    public function getUsersPrivileges($resourceIds)
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
    public function templateExample(){}
}
