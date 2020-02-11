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

use core_kernel_classes_Class;
use core_kernel_classes_Resource;
use oat\oatbox\log\LoggerAwareTrait;
use RuntimeException;

class PermissionsService
{
    use LoggerAwareTrait;

    /** @var DataBaseAccess */
    private $dataBaseAccess;
    /** @var PermissionsStrategyInterface */
    private $strategy;

    public function __construct(
        DataBaseAccess $dataBaseAccess,
        PermissionsStrategyInterface $strategy
    ) {
        $this->dataBaseAccess = $dataBaseAccess;
        $this->strategy = $strategy;
    }

    public function savePermissions(
        bool $recursive,
        core_kernel_classes_Class $class,
        array $privilegesToSet
    ): void {
        $currentPrivileges = $this->dataBaseAccess->getResourcePermissions($class->getUri());

        $addRemove = $this->strategy->normalizeRequest($currentPrivileges, $privilegesToSet);

        if (empty($addRemove)) {
            return;
        }

        /** @var core_kernel_classes_Resource[] $resources */
        $resources = [$class];

        if ($recursive) {
            $resources = array_merge($resources, $class->getSubClasses(true));
            $resources = array_merge($resources, $class->getInstances(true));
        }

        $resourceIds = [];
        foreach ($resources as $resource) {
            $resourceIds[] = $resource->getUri();
        }
        $userIds = array_keys($privilegesToSet);
        $permissionsList = $this->dataBaseAccess->getResourcesPermissions($userIds, $resourceIds);

        $resultPermissions = $permissionsList;

        $actions = [];

        foreach ($resources as $resource) {
            $currentPrivileges = $permissionsList[$resource->getUri()];

            $remove = $this->strategy->getPermissionsToRemove($currentPrivileges, $addRemove);
            if ($remove) {
                foreach ($remove as $userToRemove => $permissionToRemove) {
                    $resultPermissions[$resource->getUri()][$userToRemove] = array_diff(
                        $resultPermissions[$resource->getUri()][$userToRemove],
                        $permissionToRemove
                    );
                }

                $actions[] = function () use ($remove, $resource) {
                    $this->removePermissions($remove, $resource);
                };
            }

            $add = $this->strategy->getPermissionsToAdd($currentPrivileges, $addRemove);
            if ($add) {
                foreach ($add as $userToAdd => $permissionToAdd) {
                    $resultPermissions[$resource->getUri()][$userToAdd] = array_merge(
                        (array)$resultPermissions[$resource->getUri()][$userToAdd],
                        $permissionToAdd
                    );
                }

                $actions[] = function () use ($add, $resource) {
                    $this->addPermissions($add, $resource);
                };
            }
        }

        // check if all resources after all actions are applied will have al least one user with GRANT permission
        foreach ($resultPermissions as $resultResources => $resultUsers) {
            $grunt = false;
            foreach ($resultUsers as $resultPermissions) {
                if (in_array(PermissionProvider::PERMISSION_GRANT, $resultPermissions, true)) {
                    $grunt = true;
                    break;
                }
            }

            if (!$grunt) {
                throw new RuntimeException(
                    sprintf(
                        'Resource %s should have at least one user with GRANT access',
                        $resultResources
                    )
                );
            }
        }

        foreach ($actions as $processedResource) {
            $processedResource();
        }
    }

    /**
     * @param array                        $permissions
     * @param core_kernel_classes_Resource $resource
     *
     */
    private function removePermissions(array $permissions, core_kernel_classes_Resource $resource): void
    {
        foreach ($permissions as $userId => $privilegeIds) {
            if (!empty($privilegeIds)) {
                $this->dataBaseAccess->removePermissions($userId, $resource->getUri(), $privilegeIds);
            }
        }
    }

    private function addPermissions(array $permissions, core_kernel_classes_Class $resource): void
    {
        foreach ($permissions as $userId => $privilegeIds) {
            if (!empty($privilegeIds)) {
                $this->dataBaseAccess->addPermissions($userId, $resource->getUri(), $privilegeIds);
            }
        }
    }
}
