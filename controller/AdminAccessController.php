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
 * Copyright (c) 2014-2017 (original work) Open Assessment Technologies SA;
 *
 *
 */

namespace oat\taoDacSimple\controller;

use oat\tao\model\security\xsrf\TokenService;
use oat\taoDacSimple\model\DataBaseAccess;
use oat\taoDacSimple\model\AdminService;
use oat\taoDacSimple\model\PermissionProvider;
use oat\oatbox\log\LoggerAwareTrait;


/**
 * This controller is used to manage permission administration
 *
 * @author Open Assessment Technologies SA
 * @package taoDacSimple
 * @subpackage actions
 * @license GPL-2.0
 *
 */
class AdminAccessController extends \tao_actions_CommonModule
{
    use LoggerAwareTrait;

    /** @var DataBaseAccess  */
    private $dataAccess;

    /**
     * initialize the services
     */
    public function __construct()
    {
        parent::__construct();
        $this->dataAccess = $this->getServiceManager()->get(DataBaseAccess::SERVICE_ID);
    }

    /**
     * Manage permissions
     * @requiresRight id GRANT
     */
    public function adminPermissions()
    {
        $resource = new \core_kernel_classes_Resource($this->getRequestParameter('id'));

        $accessRights = AdminService::getUsersPermissions($resource->getUri());

        $this->setData('privileges', PermissionProvider::getRightLabels());

        $users = array();
        $roles = array();
        foreach ($accessRights as $uri => $privileges) {
            $identity = new \core_kernel_classes_Resource($uri);
            if ($identity->isInstanceOf(\tao_models_classes_RoleService::singleton()->getRoleClass())) {
                $roles[$uri] = array(
                    'label' => $identity->getLabel(),
                    'privileges' => $privileges,
                );
            } else {
                $users[$uri] = array(
                    'label' => $identity->getLabel(),
                    'privileges' => $privileges,
                );
            }
        }

        $this->setData('users', $users);
        $this->setData('roles', $roles);
        $this->setData('isClass', $resource->isClass());

        $this->setData('uri', $resource->getUri());
        $this->setData('label', _dh($resource->getLabel()));

        // Add csrf token
        $tokenService = $this->getServiceLocator()->get(TokenService::SERVICE_ID);
        $this->setData('xsrf-token-name',  $tokenService->getTokenName());
        $this->setData('xsrf-token-value', $tokenService->createToken());

        $this->setView('AdminAccessController/index.tpl');
    }

    /**
     * add privileges for a group of users on resources. It works for add or modify privileges
     * @return bool
     * @requiresRight resource_id GRANT
     * @throws
     */
    public function savePermissions()
    {
        $recursive = ($this->getRequest()->getParameter('recursive') === "1");

        $clazz = $this->getResourceFromRequest();
        $privileges = $this->getPrivilegesFromRequest();

        // Csrf token validation
        $token = $this->validateCsrfToken();

        // Check if there is still a owner on this resource
        if (!$this->validatePermissions($privileges)) {
            \common_Logger::e('Cannot save a list without a fully privileged user');
            return $this->returnJson(array(
            	'success' => false
            ), 500);
        }

        $resources = array($clazz);
        if($recursive){
            $resources = array_merge($resources, $clazz->getSubClasses(true));
            $resources = array_merge($resources, $clazz->getInstances(true));
        }

        $success = true;
        $code = 200;
        $message = __('Permissions saved');
        $processedResources = [];
        try {
            foreach ($resources as $resource) {
                $permissions = $this->dataAccess->getDeltaPermissions($resource->getUri(), $privileges);
                $this->removePermissions($permissions['remove'], $resource);
                $this->addPermissions($permissions['add'], $resource);
                $processedResources[] = $resource;
            }
        } catch (\common_exception_InconsistentData $e) {
            $success = false;
            $message = $e->getMessage();
            $this->rollback($processedResources, $privileges);
            $code = 500;
        }

        $tokenService = $this->getServiceLocator()->get(TokenService::SERVICE_ID);

        return $this->returnJson([
            'success'   => $success,
            'message'   => $message,
            'token'     => $token,
            'tokenName' => $tokenService->getTokenName()
        ], $code);
    }

    /**
     * @param array $permissions
     * @param \core_kernel_classes_Resource $resource
     * @throws \common_exception_Error
     * @throws \common_exception_InconsistentData
     */
    private function removePermissions(array $permissions, \core_kernel_classes_Resource $resource)
    {
        $permissionProvider = $this->getPermissionProvider();
        $supportedRights = $permissionProvider->getSupportedRights();
        sort($supportedRights);
        $currentUser = \common_session_SessionManager::getSession()->getUser();
        foreach ($permissions as $userId => $privilegeIds) {
            if (count($privilegeIds) > 0) {
                $this->dataAccess->removePermissions($userId, $resource->getUri(), $privilegeIds);
                $currentUserPermissions = current($permissionProvider->getPermissions(
                    $currentUser,
                    [$this->getRequest()->getParameter('resource_id')]
                ));
                sort($currentUserPermissions);
                if ($currentUserPermissions !== $supportedRights) {
                    $this->dataAccess->addPermissions($userId, $resource->getUri(), $privilegeIds);
                    $this->logWarning('Attempt to revoke access to resource ' . $resource->getUri()
                        . ' for current user: ' . $currentUser->getIdentifier());
                    throw new \common_exception_InconsistentData(__('Access can not be revoked for the current user.'));
                }
            }
        }
    }

    /**
     * @param $permissions
     * @param $resource
     */
    private function addPermissions($permissions, $resource)
    {
        foreach ($permissions as $userId => $privilegeIds) {
            if (count($privilegeIds) > 0) {
                $this->dataAccess->addPermissions($userId, $resource->getUri(), $privilegeIds);
            }
        }
    }

    /**
     * @param $resources
     * @param $privileges
     * @throws
     */
    private function rollback($resources, $privileges)
    {
        try {
            foreach ($resources as $resource) {
                $permissions = $this->dataAccess->getDeltaPermissions($resource->getUri(), $privileges);
                $this->removePermissions($permissions['add'], $resource);
                $this->addPermissions($permissions['remove'], $resource);
            }
        } catch (\Exception $e) {
            $this->logWarning('Error occurred during rollback at ' . self::class . ': ' . $e->getMessage());
        }
    }

    /**
     * Check if the array to save contains a user that has all privileges
     *
     * @param array $usersPrivileges
     * @return bool
     */
    protected function validatePermissions($usersPrivileges)
    {
        $permissionProvider = $this->getPermissionProvider();
        foreach ($usersPrivileges as $user => $options) {
            if (array_diff($options, $permissionProvider->getSupportedRights()) === array_diff($permissionProvider->getSupportedRights(), $options)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validates CSRF token and returns a fresh token on success
     *
     * @return string
     * @throws \common_exception_Unauthorized
     */
    protected function validateCsrfToken()
    {
        $tokenService = $this->getServiceLocator()->get(TokenService::SERVICE_ID);
        $tokenName    = $tokenService->getTokenName();
        $token        = $this->getRequestParameter($tokenName);

        if($tokenService->checkToken($token)) {
            $tokenService->revokeToken($token);
            return $tokenService->createToken();
        }

        \common_Logger::e('CSRF token validation failed');
        throw new \common_exception_Unauthorized();
    }

    /**
     * @return PermissionProvider
     */
    private function getPermissionProvider()
    {
        return $this->getServiceLocator()->get(PermissionProvider::SERVICE_ID);
    }

    /**
     * Get privileges from request
     * @return array
     */
    private function getPrivilegesFromRequest()
    {
        if ($this->hasRequestParameter('privileges')) {
            $privileges = $this->getRequestParameter('privileges');
        } else {
            $privileges = [];
            foreach ($this->getRequest()->getParameter('users') as $userId => $data) {
                unset($data['type']);
                $privileges[$userId] = array_keys($data);
            }
        }
        return $privileges;
    }

    /**
     * Get resource fro request
     * @return \core_kernel_classes_Class
     */
    private function getResourceFromRequest()
    {
        if ($this->hasRequestParameter('uri')) {
            $resourceId = $this->getRequest()->getParameter('uri');
        } else {
            $resourceId = (string)$this->getRequest()->getParameter('resource_id');
        }
        return new \core_kernel_classes_Class($resourceId);
    }
}
