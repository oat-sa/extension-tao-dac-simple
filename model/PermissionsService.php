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
use oat\oatbox\log\LoggerAwareTrait;
use oat\oatbox\service\exception\InvalidServiceManagerException;
use RuntimeException;

class PermissionsService
{
    use LoggerAwareTrait;

    /** @var PermissionProvider */
    private $permissionProvider;
    /** @var DataBaseAccess */
    private $dataBaseAccess;
    /** @var PermissionStrategyInterface */
    private $strategy;

    public function __construct(
        PermissionProvider $permissionProvider,
        DataBaseAccess $dataBaseAccess,
        PermissionStrategyInterface $strategy
    ) {
        $this->permissionProvider = $permissionProvider;
        $this->dataBaseAccess = $dataBaseAccess;
        $this->strategy = $strategy;
    }

    /**
     * @param bool                      $recursive
     * @param core_kernel_classes_Class $class
     * @param array                     $privilegesToSet
     * @param string                    $resourceId
     *
     * @throws InvalidServiceManagerException
     * @throws common_exception_Error
     * @throws common_exception_InconsistentData
     */
    public function savePermissions(
        bool $recursive,
        core_kernel_classes_Class $class,
        array $privilegesToSet,
        string $resourceId
    ): void {
        $currentPrivileges = $this->dataBaseAccess->getResourcePermissions($class->getUri());

        $addRemove = $this->strategy->normalizeRequest($currentPrivileges, $privilegesToSet, $class);

        if (empty($addRemove)) {
            return;
        }

        $this->validatePermissions($privilegesToSet);

        $resources = [$class];

        if ($recursive) {
            $resources = array_merge($resources, $class->getSubClasses(true));
            $resources = array_merge($resources, $class->getInstances(true));
        }

        $processedResources = [];

        try {
            foreach ($resources as $resource) {
                $currentPrivileges = $this->dataBaseAccess->getResourcePermissions($resource->getUri());

                $remove = $this->strategy->getItemsToRemove($currentPrivileges, $addRemove);
                if ($remove) {
                    $this->removePermissions($remove, $resource, $resourceId);
                }

                $add = $this->strategy->getItemsToAdd($currentPrivileges, $addRemove);
                if ($add) {
                    $this->addPermissions($add, $resource);
                }

                $processedResources[] = $resource;
            }
        } catch (common_exception_InconsistentData $e) {
            $this->rollback($processedResources, $privilegesToSet, $resourceId);

            throw new RuntimeException($e->getMessage());
        }
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
                $permissions = $this->strategy->getDeltaPermissions($resource->getUri(), $privileges);
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
        $supportedRights = $this->permissionProvider->getSupportedRights();

        foreach ($usersPrivileges as $user => $privileges) {
            if (array_diff($privileges, $supportedRights) === array_diff($supportedRights, $privileges)) {
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
        $supportedRights = $this->permissionProvider->getSupportedRights();
        sort($supportedRights);
        $currentUser = $this->getSessionManager()->getUser();
        foreach ($permissions as $userId => $privilegeIds) {
            if (count($privilegeIds) > 0) {
                $this->dataBaseAccess->removePermissions($userId, $resource->getUri(), $privilegeIds);
                $currentUserPermissions = current(
                    $this->permissionProvider->getPermissions(
                        $currentUser,
                        [$resourceId]
                    )
                );
                sort($currentUserPermissions);
                if ($currentUserPermissions !== $supportedRights) {
                    $this->dataBaseAccess->addPermissions($userId, $resource->getUri(), $privilegeIds);
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
                $this->dataBaseAccess->addPermissions($userId, $resource->getUri(), $privilegeIds);
            }
        }
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
