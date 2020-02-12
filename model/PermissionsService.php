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
use oat\oatbox\event\EventManager;
use oat\oatbox\log\LoggerAwareTrait;
use oat\taoDacSimple\model\event\DacAffectedUsersEvent;

class PermissionsService
{
    use LoggerAwareTrait;

    /** @var DataBaseAccess */
    private $dataBaseAccess;
    /** @var PermissionsStrategyInterface */
    private $strategy;
    /**
     * @var EventManager
     */
    private $eventManager;

    public function __construct(
        DataBaseAccess $dataBaseAccess,
        PermissionsStrategyInterface $strategy,
        EventManager $eventManager
    ) {
        $this->dataBaseAccess = $dataBaseAccess;
        $this->strategy = $strategy;
        $this->eventManager = $eventManager;
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

        $permissionsList = $this->dataBaseAccess->getResourcesPermissions($resourceIds);

        $resultPermissions = $permissionsList;

        $actions = [];

        foreach ($resources as $resource) {
            $currentPrivileges = $permissionsList[$resource->getUri()];

            $remove = $this->strategy->getPermissionsToRemove($currentPrivileges, $addRemove);
            if ($remove) {
                foreach ($remove as $userToRemove => $permissionToRemove) {
                    if (!empty($resultPermissions[$resource->getUri()][$userToRemove])) {
                        $resultPermissions[$resource->getUri()][$userToRemove] = array_diff(
                            $resultPermissions[$resource->getUri()][$userToRemove],
                            $permissionToRemove
                        );
                    }
                }

                $actions[] = function () use ($remove, $resource) {
                    $this->removePermissions($remove, $resource);
                };
            }

            $add = $this->strategy->getPermissionsToAdd($currentPrivileges, $addRemove);
            if ($add) {
                foreach ($add as $userToAdd => $permissionToAdd) {
                    if (empty($resultPermissions[$resource->getUri()][$userToAdd])) {
                        $resultPermissions[$resource->getUri()][$userToAdd] = $permissionToAdd;
                    } else {
                        $resultPermissions[$resource->getUri()][$userToAdd] = array_merge(
                            $resultPermissions[$resource->getUri()][$userToAdd],
                            $permissionToAdd
                        );
                    }
                }

                $actions[] = function () use ($add, $resource) {
                    $this->addPermissions($add, $resource);
                };
            }
        }

        $this->validateResources($resultPermissions);

        foreach ($actions as $processedResource) {
            $processedResource();
        }

        $this->eventManager->trigger(
            new DacAffectedUsersEvent(
                array_keys($addRemove['add'] ?? []),
                array_keys($addRemove['remove'] ?? [])
            )
        );
    }

    private function validateResources(array $resultPermissions): void
    {
        // check if all resources after all actions are applied will have al least one user with GRANT permission
        foreach ($resultPermissions as $resultResources => $resultUsers) {
            $grunt = false;
            foreach ($resultUsers as $permissions) {
                if (in_array(PermissionProvider::PERMISSION_GRANT, $permissions, true)) {
                    $grunt = true;
                    break;
                }
            }

            if (!$grunt) {
                throw new PermissionsServiceException(
                    sprintf(
                        'Resource %s should have at least one user with GRANT access',
                        $resultResources
                    )
                );
            }
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

    private function addPermissions(array $permissions, core_kernel_classes_Resource $resource): void
    {
        foreach ($permissions as $userId => $privilegeIds) {
            if (!empty($privilegeIds)) {
                $this->dataBaseAccess->addPermissions($userId, $resource->getUri(), $privilegeIds);
            }
        }
    }
}
