<?php

declare(strict_types=1);

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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA;
 *
 */

namespace oat\taoDacSimple\model;

use common_exception_Error;
use common_exception_InconsistentData;
use common_session_Session;
use common_session_SessionManager;
use core_kernel_classes_Class;
use core_kernel_classes_Resource;
use oat\oatbox\service\ConfigurableService;
use oat\oatbox\service\exception\InvalidServiceManagerException;
use RuntimeException;

class PermissionsService extends ConfigurableService
{
    /**
     * @param bool                      $recursive
     * @param core_kernel_classes_Class $class
     * @param array                     $privileges
     * @param string                    $resourceId
     *
     * @throws InvalidServiceManagerException
     * @throws common_exception_Error
     * @throws common_exception_InconsistentData
     */
    public function savePermissions(
        bool $recursive,
        core_kernel_classes_Class $class,
        array $privileges,
        string $resourceId
    ): void {
        $this->validatePermissions($privileges);

        $resources = [$class];

        if ($recursive) {
            $resources = array_merge($resources, $class->getSubClasses(true));
            $resources = array_merge($resources, $class->getInstances(true));
        }

        $processedResources = [];

        try {
            foreach ($resources as $resource) {
                $permissions = $this->getDeltaPermissions($resource->getUri(), $privileges);
                $this->removePermissions($permissions['remove'], $resource, $resourceId);
                $this->addPermissions($permissions['add'], $resource);
                $processedResources[] = $resource;
            }
        } catch (common_exception_InconsistentData $e) {
            $this->rollback($processedResources, $privileges, $resourceId);

            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * get the delta between existing permissions and new permissions
     *
     * @access public
     *
     * @param string $resourceId
     * @param array  $rights associative array $user_id => $permissions
     *
     * @return array
     */
    private function getDeltaPermissions($resourceId, $rights): array
    {
        $privileges = $this->getDatabaseAccess()->getResourcePermissions($resourceId);

        foreach ($rights as $userId => $privilegeIds) {
            //if privileges are in request but not in db we add then
            if (!isset($privileges[$userId])) {
                $add[$userId] = $privilegeIds;
            } // compare privileges in db and request
            else {
                $add[$userId] = array_diff($privilegeIds, $privileges[$userId]);
                $remove[$userId] = array_diff($privileges[$userId], $privilegeIds);
                // unset already compare db variable
                unset($privileges[$userId]);
            }
        }

        //remaining privileges has to be removed
        foreach ($privileges as $userId => $privilegeIds) {
            $remove[$userId] = $privilegeIds;
        }


        return compact('remove', 'add');
    }

    /**
     * @param core_kernel_classes_Class[] $resources
     * @param array                       $privileges
     * @param string                      $resourceId
     *
     * @throws InvalidServiceManagerException
     * @throws common_exception_Error
     * @throws common_exception_InconsistentData
     */
    private function rollback(array $resources, array $privileges, string $resourceId): void
    {
        try {
            foreach ($resources as $resource) {
                $permissions = $this->getDeltaPermissions($resource->getUri(), $privileges);
                $this->removePermissions($permissions['add'], $resource, $resourceId);
                $this->addPermissions($permissions['remove'], $resource);
            }
        } catch (RuntimeException $e) {
            $this->logWarning(
                sprintf('Error occurred during rollback at %s: %s', self::class, $e->getMessage())
            );
        }
    }

    /**
     * Check if the array to save contains a user that has all privileges
     *
     * @param array $usersPrivileges
     */
    protected function validatePermissions($usersPrivileges): void
    {
        $supportedRights = $this->getPermissionProvider()->getSupportedRights();

        foreach ($usersPrivileges as $user => $options) {
            if (array_diff($options, $supportedRights) === array_diff($supportedRights, $options)) {
                return;
            }
        }

        throw new RuntimeException('Cannot save a list without a fully privileged user');
    }

    /**
     * @param array                        $permissions
     * @param core_kernel_classes_Resource $resource
     *
     * @param string                       $resourceId
     *
     * @throws InvalidServiceManagerException
     * @throws common_exception_Error
     * @throws common_exception_InconsistentData
     */
    public function removePermissions(array $permissions, core_kernel_classes_Resource $resource, $resourceId): void
    {
        $permissionProvider = $this->getPermissionProvider();
        $supportedRights = $permissionProvider->getSupportedRights();
        sort($supportedRights);
        $currentUser = $this->getSessionManager()->getUser();
        foreach ($permissions as $userId => $privilegeIds) {
            if (count($privilegeIds) > 0) {
                $this->getDatabaseAccess()->removePermissions($userId, $resource->getUri(), $privilegeIds);
                $currentUserPermissions = current(
                    $permissionProvider->getPermissions(
                        $currentUser,
                        [$resourceId]
                    )
                );
                sort($currentUserPermissions);
                if ($currentUserPermissions !== $supportedRights) {
                    $this->getDatabaseAccess()->addPermissions($userId, $resource->getUri(), $privilegeIds);
                    $this->logWarning(
                        sprintf(
                            'Attempt to revoke access to resource %s for current user: %s',
                            $resource->getUri(),
                            $currentUser->getIdentifier()
                        )
                    );

                    throw new common_exception_InconsistentData(__('Access can not be revoked for the current user.'));
                }
            }
        }
    }

    private function addPermissions(array $permissions, core_kernel_classes_Class $resource): void
    {
        foreach ($permissions as $userId => $privilegeIds) {
            if (count($privilegeIds) > 0) {
                $this->getDatabaseAccess()->addPermissions($userId, $resource->getUri(), $privilegeIds);
            }
        }
    }

    private function getPermissionProvider(): PermissionProvider
    {
        return $this->getServiceLocator()->get(PermissionProvider::SERVICE_ID);
    }

    private function getDatabaseAccess(): DataBaseAccess
    {
        return $this->getServiceLocator()->get(DataBaseAccess::SERVICE_ID);
    }

    /**
     * @return common_session_Session
     * @throws common_exception_Error
     */
    private function getSessionManager(): common_session_Session
    {
        return common_session_SessionManager::getSession();
    }
}
