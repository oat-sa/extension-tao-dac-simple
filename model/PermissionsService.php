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
 * Copyright (c) 2020-2023 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

namespace oat\taoDacSimple\model;

use core_kernel_classes_Class;
use core_kernel_classes_Resource;
use oat\oatbox\event\EventManager;
use oat\oatbox\log\LoggerAwareTrait;
use oat\tao\model\event\DataAccessControlChangedEvent;
use oat\taoDacSimple\model\Command\ChangePermissionsCommand;
use oat\taoDacSimple\model\event\DacAffectedUsersEvent;
use oat\taoDacSimple\model\event\DacRootAddedEvent;
use oat\taoDacSimple\model\event\DacRootRemovedEvent;

class PermissionsService
{
    use LoggerAwareTrait;

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

    public function applyPermissions(ChangePermissionsCommand $command): void
    {
        $root = $command->getRoot();
        $currentPrivileges = $this->dataBaseAccess->getResourcePermissions($root->getUri());
        $permissionsDelta = $this->strategy->normalizeRequest(
            $currentPrivileges,
            $command->getPrivilegesPerUser()
        );

        if (empty($permissionsDelta)) {
            return;
        }

        // Backward compatibility: List resources and classes contained in the current one
        // if the request is recursive, otherwise just get instances for the current class
        //
        if ($command->isRecursive()) {
            $resources = $this->getResourcesByClassRecursive($root);
        } else  {
            $resources = $this->getResourcesToUpdate(
                $root,
                $command->applyToNestedResources()
            );
        }

        $permissionsList = $this->getResourcesPermissions($resources);
        $actions = $this->getActions($resources, $permissionsList, $permissionsDelta);

        $this->dryRun($actions, $permissionsList);
        $this->wetRun($actions);

        $this->triggerEvents(
            $permissionsDelta,
            $root->getUri(),
            $command->applyToNestedResources()
        );
    }

    /**
     * @deprecated Use applyPermissions() instead
     */
    public function saveResourcePermissionsRecursive(
        core_kernel_classes_Resource $resource,
        array $privilegesToSet
    ): void {
        error_log('Called deprecated method ' . __FUNCTION__, E_USER_DEPRECATED);

        //$this->saveResourcePermissions($resource, $privilegesToSet, true);
        $this->applyPermissions(
            new ChangePermissionsCommand($resource, $privilegesToSet, true, true)
        );
    }

    /**
     * @deprecated Use applyPermissions() instead
     */
    public function savePermissions(
        bool $isRecursive,
        core_kernel_classes_Class $class,
        array $privilegesToSet// ,
        //bool $applyToNestedResources = false
    ): void {
        error_log('Called deprecated method ' . __FUNCTION__, E_USER_DEPRECATED);

        $this->applyPermissions(
            new ChangePermissionsCommand($class, $privilegesToSet, $isRecursive, true)
        );

        //$this->saveResourcePermissions($class, $privilegesToSet, $applyToNestedResources);
    }

    // @todo Fix unit tests

    // @todo Remove in favor of applyPermissions
    /*private function saveResourcePermissions(
        core_kernel_classes_Resource $resource,
        array $privilegesToSet,
        bool $applyToNestedResources = false
    ): void {
        $currentPrivileges = $this->dataBaseAccess->getResourcePermissions($resource->getUri());
        $addRemove = $this->strategy->normalizeRequest($currentPrivileges, $privilegesToSet);

        if (empty($addRemove)) {
            return;
        }

        $resourcesToUpdate = $this->getResourcesToUpdate($resource, $applyToNestedResources);
        $permissionsList = $this->getResourcesPermissions($resourcesToUpdate);
        $actions = $this->getActions($resourcesToUpdate, $permissionsList, $addRemove);

        $this->dryRun($actions, $permissionsList);
        $this->wetRun($actions);
        $this->triggerEvents($addRemove, $resource->getUri(), $applyToNestedResources);
    }*/

    private function getActions(array $resourcesToUpdate, array $permissionsList, array $addRemove): array
    {
        $actions = ['remove' => [], 'add' => []];

        foreach ($resourcesToUpdate as $resource) {
            $currentPrivileges = $permissionsList[$resource->getUri()];

            $remove = $this->strategy->getPermissionsToRemove($currentPrivileges, $addRemove);
            if ($remove) {
                $actions['remove'][] = ['permissions' => $remove, 'resource' => $resource];
            }

            $add = $this->strategy->getPermissionsToAdd($currentPrivileges, $addRemove);
            if ($add) {
                $actions['add'][] = ['permissions' => $add, 'resource' => $resource];
            }
        }

        return $this->deduplicateActions($actions);
    }

    private function dryRun(array $actions, array $permissionsList): void
    {
        $resultPermissions = $permissionsList;

        foreach ($actions['remove'] as $item) {
            $this->dryRemove($item['permissions'], $item['resource'], $resultPermissions);
        }
        foreach ($actions['add'] as $item) {
            $this->dryAdd($item['permissions'], $item['resource'], $resultPermissions);
        }

        $this->assertHasUserWithGrantPermission($resultPermissions);
    }

    private function wetRun(array $actions): void
    {
        if (!empty($actions['remove'])) {
            $this->dataBaseAccess->removeMultiplePermissions($actions['remove']);
        }
        if (!empty($actions['add'])) {
            $this->dataBaseAccess->addMultiplePermissions($actions['add']);
        }
    }

    private function dryRemove(array $remove, core_kernel_classes_Resource $resource, array &$resultPermissions): void
    {
        foreach ($remove as $userToRemove => $permissionToRemove) {
            if (!empty($resultPermissions[$resource->getUri()][$userToRemove])) {
                $resultPermissions[$resource->getUri()][$userToRemove] = array_diff(
                    $resultPermissions[$resource->getUri()][$userToRemove],
                    $permissionToRemove
                );
            }
        }
    }

    private function dryAdd(array $add, core_kernel_classes_Resource $resource, array &$resultPermissions): void
    {
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
    }

    private function deduplicateActions(array $actions): array
    {
        foreach ($actions['add'] as &$entry) {
            foreach ($entry['permissions'] as &$grants) {
                $grants = array_unique($grants);
            }
        }

        foreach ($actions['remove'] as &$entry) {
            foreach ($entry['permissions'] as &$grants) {
                $grants = array_unique($grants);
            }
        }

        return $actions;
    }

    /**
     * Provides an array holding the provided resource and, if it is a class, all
     * resources that are instances of the provided class or any of its descendants
     * plus all descendant classes.
     *
     * @return core_kernel_classes_Resource[]
     */
    private function getResourcesByClassRecursive(core_kernel_classes_Resource $resource): array
    {
        $resources = [$resource];

        if ($resource->isClass()) {
            return array_merge($resources, $resource->getInstances(true));
        }

        return $resources;
    }

    private function getResourcesToUpdate(
        core_kernel_classes_Resource $resource,
        bool $updateClassInstances = false
    ): array {
        $resources = [$resource];

        if ($updateClassInstances && $resource->isClass()) {
            return array_merge($resources, $resource->getInstances(true));
        }

        return $resources;
    }

    private function getResourcesPermissions(array $resources): array
    {
        if (empty($resources)) {
            return [];
        }

        $resourceIds = [];
        foreach ($resources as $resource) {
            $resourceIds[] = $resource->getUri();
        }

        return $this->dataBaseAccess->getResourcesPermissions($resourceIds);
    }

    /**
     * Checks if all resources after all actions are applied will have at least
     * one user with GRANT permission.
     */
    private function assertHasUserWithGrantPermission(array $resultPermissions): void
    {
        foreach ($resultPermissions as $resultResources => $resultUsers) {
            $granted = false;
            foreach ($resultUsers as $permissions) {
                $granted = in_array(PermissionProvider::PERMISSION_GRANT, $permissions, true);

                if ($granted) {
                    break;
                }
            }

            if (!$granted) {
                throw new PermissionsServiceException(
                    sprintf(
                        'Resource %s should have at least one user with GRANT access',
                        $resultResources
                    )
                );
            }
        }
    }

    private function triggerEvents(array $addRemove, string $resourceId, bool $applyToNestedResources): void
    {
        if (!empty($addRemove['add'])) {
            foreach ($addRemove['add'] as $userId => $rights) {
                $this->eventManager->trigger(new DacRootAddedEvent($userId, $resourceId, (array)$rights));
            }
        }
        if (!empty($addRemove['remove'])) {
            foreach ($addRemove['remove'] as $userId => $rights) {
                $this->eventManager->trigger(new DacRootRemovedEvent($userId, $resourceId, (array)$rights));
            }
        }
        $this->eventManager->trigger(
            new DacAffectedUsersEvent(
                array_keys($addRemove['add'] ?? []),
                array_keys($addRemove['remove'] ?? [])
            )
        );

        $this->eventManager->trigger(
            new DataAccessControlChangedEvent(
                $resourceId,
                $addRemove,
                $applyToNestedResources
            )
        );
    }
}
