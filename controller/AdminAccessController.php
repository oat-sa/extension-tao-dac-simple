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
use oat\taoDacSimple\model\AdminService;
use oat\taoDacSimple\model\PermissionProvider;
use oat\taoDacSimple\model\PermissionsService;
use oat\taoDacSimple\model\PermissionsServiceFactory;
use RuntimeException;
use tao_actions_CommonModule;
use tao_models_classes_RoleService;
use function GuzzleHttp\Psr7\stream_for;

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
            } else {
                $users[$uri] = [
                    'label'      => $identity->getLabel(),
                    'privileges' => $privileges,
                ];
            }
        }

        $this->setData('users', $users);
        $this->setData('roles', $roles);
        $this->setData('isClass', $resource->isClass());

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
            $this->validateCsrf();

            $service = $this->getPermissionService();

            $service->savePermissions(
                $recursive,
                $this->getResourceFromRequest(),
                $this->getPrivilegesFromRequest()
            );

            $this->returnJson(
                [
                    'success' => true,
                    'message' => __('Permissions saved')
                ],
                200
            );
        } catch (common_exception_Unauthorized $e) {
            $this->response = $this->getPsrResponse()->withStatus(403, __('Unable to process your request'));
        } catch (RuntimeException $e) {
            $this->response = $this->getPsrResponse()
                ->withStatus(400, $e->getMessage())
                ->withBody(stream_for(json_encode(['success' => false, 'message' => $e->getMessage()])))
                ->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $this->logError($e->getMessage());

            $this->returnJson(['success' => false], 500);
        }
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
     * @return core_kernel_classes_Class
     *
     * @throws common_exception_Error
     */
    private function getResourceFromRequest(): core_kernel_classes_Class
    {
        if ($this->hasRequestParameter('uri')) {
            $resourceId = $this->getRequest()->getParameter('uri');
        } else {
            $resourceId = (string)$this->getRequest()->getParameter('resource_id');
        }

        return new core_kernel_classes_Class($resourceId);
    }
}
