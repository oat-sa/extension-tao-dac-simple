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
 * Copyright (c) 2023 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

namespace oat\taoDacSimple\model;

use core_kernel_classes_Resource;
use oat\oatbox\event\EventManager;
use oat\tao\model\event\DataAccessControlChangedEvent;
use oat\taoDacSimple\model\Command\ChangeAccessCommand;
use oat\taoDacSimple\model\Command\ChangePermissionsCommand;
use oat\taoDacSimple\model\event\DacAffectedUsersEvent;
use oat\taoDacSimple\model\event\DacRootChangedEvent;

class ChangePermissionsService
{
    private DataBaseAccess $dataBaseAccess;
    private PermissionsStrategyInterface $strategy;
    private EventManager $eventManager;

    public function __construct(
        DataBaseAccess $dataBaseAccess,
        PermissionsStrategyInterface $strategy,
        EventManager $eventManager
    ) {
        $this->dataBaseAccess = $dataBaseAccess;
        $this->strategy = $strategy;
        $this->eventManager = $eventManager;
    }

    public function change(ChangePermissionsCommand $command): void
    {
        $resources = $this->getResourceToUpdate($command->getRoot(), $command->isRecursive());

        $permissions = $this->dataBaseAccess->getResourcesPermissions(array_column($resources, 'id'));
        $rootPermissions = $permissions[$command->getRoot()->getUri()];
        $permissionsDelta = $this->strategy->normalizeRequest($rootPermissions, $command->getPrivilegesPerUser());

        if (empty($permissionsDelta['remove']) && empty($permissionsDelta['add'])) {
            return;
        }

        $changeAccessCommand = $this->calculateChanges($resources, $permissionsDelta, $permissions);
        $this->dataBaseAccess->changeAccess($changeAccessCommand);

        $this->triggerEvents($command->getRoot(), $permissionsDelta, $command->isRecursive());
    }

    private function getResourceToUpdate(core_kernel_classes_Resource $resource, bool $isRecursive): array
    {
        if ($isRecursive) {
            $resources = [];

            foreach ($resource->getNestedResources() as $result) {
                $resources[$result['id']] = $result;
            }

            return $resources;
        }

        return [
            $resource->getUri() => [
                'id' => $resource->getUri(),
                'isClass' => $resource->isClass(),
                'level' => 1,
            ],
        ];
    }

    private function calculateChanges(
        array $resources,
        array $permissionsDelta,
        array $currentPermissions
    ): ChangeAccessCommand {
        $command = new ChangeAccessCommand();

        foreach ($resources as $resource) {
            $resourcePermissions = $currentPermissions[$resource['id']];

            $remove = $this->strategy->getPermissionsToRemove($resourcePermissions, $permissionsDelta);
            $add = $this->strategy->getPermissionsToAdd($resourcePermissions, $permissionsDelta);

            foreach ($remove as $userId => $permissions) {
                $resourcePermissions[$userId] = array_diff($resourcePermissions[$userId] ?? [], $permissions);

                foreach ($permissions as $permission) {
                    $command->revokeResourceForUser($resource['id'], $permission, $userId);
                }
            }

            foreach ($add as $userId => $permissions) {
                $resourcePermissions[$userId] = array_merge($resourcePermissions[$userId] ?? [], $permissions);

                foreach ($permissions as $permission) {
                    $command->grantResourceForUser($resource['id'], $permission, $userId);
                }
            }

            $this->assertHasUserWithGrantPermission($resource['id'], $resourcePermissions);
        }

        return $command;
    }

    /**
     * Checks if all resources after all actions are applied will have at least
     * one user with GRANT permission.
     */
    private function assertHasUserWithGrantPermission(string $resourceId, array $resourcePermissions): void
    {
        foreach ($resourcePermissions as $permissions) {
            if (in_array(PermissionProvider::PERMISSION_GRANT, $permissions, true)) {
                return;
            }
        }

        throw new PermissionsServiceException(
            sprintf(
                'Resource %s should have at least one user with GRANT access',
                $resourceId
            )
        );
    }

    private function triggerEvents(
        core_kernel_classes_Resource $resource,
        array $permissionsDelta,
        bool $isRecursive
    ): void {
        if (!empty($permissionsDelta['add']) || !empty($permissionsDelta['remove'])) {
            $this->eventManager->trigger(new DacRootChangedEvent($resource, $permissionsDelta));
        }

        $this->eventManager->trigger(
            new DataAccessControlChangedEvent(
                $resource->getUri(),
                $permissionsDelta,
                $isRecursive
            )
        );

        $this->eventManager->trigger(
            new DacAffectedUsersEvent(
                array_keys($permissionsDelta['add']),
                array_keys($permissionsDelta['remove'])
            )
        );
    }
}
