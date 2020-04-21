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
 * Copyright (c) 2014-2020 (original work) Open Assessment Technologies SA;
 */

namespace oat\taoDacSimple\controller;

use common_exception_Error;
use common_exception_Unauthorized;
use core_kernel_classes_Class;
use core_kernel_classes_Resource;
use Exception;
use oat\oatbox\log\LoggerAwareTrait;
use oat\tao\model\taskQueue\QueueDispatcher;
use oat\tao\model\taskQueue\TaskLogActionTrait;
use oat\taoDacSimple\model\AdminService;
use oat\taoDacSimple\model\PermissionProvider;
use oat\taoDacSimple\model\PermissionsService;
use oat\taoDacSimple\model\PermissionsServiceException;
use oat\taoDacSimple\model\PermissionsServiceFactory;
use tao_actions_CommonModule;
use tao_models_classes_RoleService;
use oat\taoDacSimple\model\tasks\ChangePermissionsTask;
use function GuzzleHttp\Psr7\stream_for;
use oat\oatbox\user\UserService;
use oat\generis\model\OntologyRdfs;

/**
 * This controller is used to manage permission administration
 *
 * @author     Open Assessment Technologies SA
 * @package    taoDacSimple
 * @subpackage actions
 * @license    GPL-2.0
 *
 */
class AdminAccessController extends tao_actions_CommonModule
{
    use TaskLogActionTrait;
    use LoggerAwareTrait;

    /**
     * Manage permissions
     *
     * @requiresRight id GRANT
     *
     * @throws common_exception_Error
     */
    public function adminPermissions(): void
    {
        $resource = new core_kernel_classes_Resource($this->getRequestParameter('id'));

        $accessRights = AdminService::getUsersPermissions($resource->getUri());
        $this->setData('privileges', PermissionProvider::getRightLabels());
        $users = [];
        $roles = [];
        foreach ($accessRights as $uri => $privileges) {
            $identity = new core_kernel_classes_Resource($uri);
            if ($identity->isInstanceOf(tao_models_classes_RoleService::singleton()->getRoleClass())) {
                $roles[$uri] = [
                    'label'      => $identity->getLabel(),
                    'privileges' => $privileges,
                ];
                unset($accessRights[$uri]);
            }
        }
        if (!empty($accessRights)) {
            $userService = $this->getServiceLocator()->get(UserService::SERVICE_ID);
            $usersInfo = $userService->getUsers(array_keys($accessRights));
            foreach ($usersInfo as $uri => $user) {
                $labels = $user->getPropertyValues(OntologyRdfs::RDFS_LABEL);
                $users[$uri] = [
                    'label'      => empty($labels) ? 'unknown user' : reset($labels),
                    'privileges' => $accessRights[$uri],
                ];
            }
        }
        $this->setData('users', $users);
        $this->setData('roles', $roles);
        $this->setData('isClass', $resource->isClass());

        $permissionsServiceFactory = $this->getServiceLocator()->get(PermissionsServiceFactory::SERVICE_ID);
        $this->setData('recursive', $permissionsServiceFactory->getOption(PermissionsServiceFactory::OPTION_RECURSIVE_BY_DEFAULT));

        $this->setData('uri', $resource->getUri());
        $this->setData('label', _dh($resource->getLabel()));

        $this->setView('AdminAccessController/index.tpl');
    }

    /**
     * Add privileges for a group of users on resources. It works for add or modify privileges
     *
     * @requiresRight resource_id GRANT
     */
    public function savePermissions(): void
    {
        $recursive = ($this->getRequest()->getParameter('recursive') === '1');

        try {

            $taskParameters = [
                ChangePermissionsTask::PARAM_RECURSIVE  => $recursive,
                ChangePermissionsTask::PARAM_RESOURCE   => $this->getResourceFromRequest(),
                ChangePermissionsTask::PARAM_PRIVILEGES => $this->getPrivilegesFromRequest()
            ];
            /** @var QueueDispatcher $queueDispatcher */
            $queueDispatcher = $this->getServiceLocator()->get(QueueDispatcher::SERVICE_ID);
            $task = $queueDispatcher->createTask(new ChangePermissionsTask(), $taskParameters, 'Processing permissions');
            $this->returnTaskJson($task);
        } catch (common_exception_Unauthorized $e) {
            $this->response = $this->getPsrResponse()->withStatus(403, __('Unable to process your request'));
        } catch (PermissionsServiceException $e) {
            $this->response = $this->getPsrResponse()
                ->withStatus(400, $e->getMessage())
                ->withBody(stream_for(json_encode(['success' => false, 'message' => $e->getMessage()])))
                ->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $this->logError($e->getMessage());

            $this->returnJson(['success' => false], 500);
        }
    }

    /**
     * Find users to assign access rights
     */
    public function findUser()
    {
        $params = $this->getGetParameter('params');
        $query = $params['query'];
        $userService = $this->getServiceLocator()->get(UserService::SERVICE_ID);
        $data = [];
        foreach ($userService->findUser($query) as $user) {
            $labels = $user->getPropertyValues(OntologyRdfs::RDFS_LABEL);
            $data[] = [
                'id'                     => $user->getIdentifier(),
                OntologyRdfs::RDFS_LABEL => empty($labels) ? 'unknown user' : reset($labels)
            ];
        }
        $response = [
            'success' => true,
            'page'    => 1,
            'total'   => 1,
            'records' => count($data),
            'data'    => $data,
        ];
        return $this->returnJson($response);
    }

    private function getPermissionService(): PermissionsService
    {
        return $this->serviceLocator->get(PermissionsServiceFactory::SERVICE_ID)->create();
    }

    private function getPrivilegesFromRequest(): array
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
     * @return string
     *
     * @throws common_exception_Error
     */
    private function getResourceFromRequest(): string
    {
        if ($this->hasRequestParameter('uri')) {
            $resourceId = $this->getRequest()->getParameter('uri');
        } else {
            $resourceId = (string)$this->getRequest()->getParameter('resource_id');
        }

        return $resourceId;
    }
}
