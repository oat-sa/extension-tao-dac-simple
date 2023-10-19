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
        $this->enrichWithPermissions($resources);
        $rootResourcePermissions = $resources[$command->getRoot()->getUri()]['permissions'];

        $permissionsDelta = $this->strategy->normalizeRequest(
            $rootResourcePermissions['current'] ?? [],
                $command->getPrivilegesPerUser()
        );

        if (empty($permissionsDelta['remove']) && empty($permissionsDelta['add'])) {
            return;
        }

        $resources = $this->enrichWithAddRemoveActions($resources, $permissionsDelta);
        $this->dryRun($resources);

        $this->dataBaseAccess->changeResourcePermissions($resources);

        $this->triggerEvents($command->getRoot(), $permissionsDelta, $command->isRecursive());
    }

    private function getResourceToUpdate(core_kernel_classes_Resource $resource, bool $isRecursive): array
    {
        if ($isRecursive) {
            $resources = [];

            foreach ($this->dataBaseAccess->getResourceTree($resource) as $result) {
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

    private function enrichWithPermissions(array &$resources): void
    {
        if (empty($resources)) {
            return;
        }

        $permissions = $this->dataBaseAccess->getResourcesPermissions(array_column($resources, 'id'));

        foreach ($resources as &$resource) {
            $resource['permissions']['current'] = $permissions[$resource['id']];
        }
    }

    private function enrichWithAddRemoveActions(array $resources, array $permissionsDelta): array
    {
        foreach ($resources as &$resource) {
            $resource['permissions']['remove'] = $this->strategy->getPermissionsToRemove(
                $resource['permissions']['current'],
                $permissionsDelta
            );

            $resource['permissions']['add'] = $this->strategy->getPermissionsToAdd(
                $resource['permissions']['current'],
                $permissionsDelta
            );
        }

        return $this->deduplicateChanges($resources);
    }

    private function deduplicateChanges(array $resources): array
    {
        foreach ($resources as &$resource) {
            foreach ($resource['permissions']['remove'] as &$removePermissions) {
                $removePermissions = array_unique($removePermissions);
            }

            foreach ($resource['permissions']['add'] as &$addPermissions) {
                $addPermissions = array_unique($addPermissions);
            }
        }

        return $resources;
    }

    private function dryRun(array $resources): void
    {
        foreach ($resources as $resource) {
            $newPermissions = $resource['permissions']['current'];

            $this->dryRemove($resource, $newPermissions);
            $this->dryAdd($resource, $newPermissions);

            $this->assertHasUserWithGrantPermission($resource['id'], $newPermissions);
        }
    }

    private function dryRemove(array $resource, array &$newPermissions): void
    {
        foreach ($resource['permissions']['remove'] as $user => $removePermissions) {
            $newPermissions[$user] = array_diff(
                $newPermissions[$user] ?? [],
                $removePermissions
            );
        }
    }

    private function dryAdd(array $resource, array &$newPermissions): void
    {
        foreach ($resource['permissions']['add'] as $user => $addPermissions) {
            $newPermissions[$user] = array_merge(
                $resource['permissions']['current'][$user] ?? [],
                $addPermissions
            );
        }
    }

    /**
     * Checks if all resources after all actions are applied will have at least
     * one user with GRANT permission.
     */
    private function assertHasUserWithGrantPermission(string $resourceId, array $newPermissions): void
    {
        foreach ($newPermissions as $permissions) {
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
            $this->eventManager->trigger(new DacRootChangedEvent($resource->getUri(), $permissionsDelta));
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
