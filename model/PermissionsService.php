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

    // @todo Fix unit tests

    /**
     * Updates the permissions for a set of resources based on the ACLs, root
     * resource and recursion parameters contained in the provided command.
     *
     * - For recursive commands having a class as the root, updates permissions
     *   for that class and all its descendants (i.e. updates all resources AND
     *   classes using the provided root class as the initial node for a tree
     *   traversal, which may be slow and resource-intensive). This is provided
     *   for backward compatibility purposes.
     *
     * - For non-recursive commands having a class as the root AND including
     *   nested resources, updates permissions for the class and all instances
     *   of that class, but skips all nested classes and instances of them (i.e.
     *   does not go down into nested levels of the resource tree).
     *
     * - Otherwise (i.e. non-class roots), it updates only the resource set as
     *   the root resource for the command.
     */
    public function applyPermissions(ChangePermissionsCommand $command): void
    {
        $resources = $this->getResourcesToUpdate($command);
        $currentPermissions = $this->getResourcesPermissions($resources);
        $permissionsDelta = $this->getDeltaForResourceTree($command, $currentPermissions);

        if (empty($permissionsDelta)) {
            return;
        }

        $actions = $this->getActions($resources, $currentPermissions, $permissionsDelta);
        $this->dryRun($actions, $currentPermissions);
        $this->wetRun($actions);

        $this->triggerEvents(
            $permissionsDelta[$command->getRoot()->getUri()],
            $command->getRoot()->getUri(),
            $command->applyToNestedResources()
        );
    }

    /**
     * @deprecated Use applyPermissions() instead
     * @see applyPermissions
     */
    public function saveResourcePermissionsRecursive(
        core_kernel_classes_Resource $resource,
        array $privilegesToSet
    ): void {
        $this->applyPermissions(
            (new ChangePermissionsCommand($resource, $privilegesToSet))
                ->withRecursion()
                ->withNestedResources()
        );
    }

    /**
     * @deprecated Use applyPermissions() instead
     * @see applyPermissions
     */
    public function savePermissions(
        bool $isRecursive,
        core_kernel_classes_Class $class,
        array $privilegesToSet
    ): void {
        $this->applyPermissions(
            (new ChangePermissionsCommand($class, $privilegesToSet))
                ->withRecursion($isRecursive)
                ->withNestedResources($isRecursive)
        );
    }

    /**
     * @param ChangePermissionsCommand $command
     * @param string[][] $currentResourcePermissions
     *
     * @return string[][][]
     */
    private function getDeltaForResourceTree(
        ChangePermissionsCommand $command,
        array $currentResourcePermissions
    ): array {
        $delta = [];

        foreach ($currentResourcePermissions as $resourceId => $permissions) {
            $permissionsDelta = $this->strategy->normalizeRequest(
                $permissions,
                $command->getPrivilegesPerUser()
            );

            if (!empty($permissionsDelta['add']) || !empty($permissionsDelta['remove'])) {
                $delta[$resourceId] = $permissionsDelta;
            }
        }

        return $delta;
    }

    /**
     * Gets the list of resources to update for a given command.
     *
     * - For recursive requests, lists both instances and classes contained in
     *   the root class pointed out by $command. This is used to provide
     *   backwards-compatible behaviour for savePermissions() and
     *   saveResourcePermissionsRecursive().
     *
     * - For non-recursive requests, returns only resource instances contained
     *   in the provided class, but skips child classes (as well as resources
     *   contained in child classes, etc).
     *
     * @return core_kernel_classes_Resource[]
     */
    private function getResourcesToUpdate(ChangePermissionsCommand $command): array
    {
        $root = $command->getRoot();

        if ($command->isRecursive()) {
            return $this->getResourcesByClassRecursive($root);
        }

        if ($command->applyToNestedResources() && $root->isClass()) {
            return array_merge([$root], $root->getInstances()); // non-recursive
        }

         return [$root];
    }

    private function getActions(
        array $resourcesToUpdate,
        array $currentResourcePermissions,
        array $permissionsDelta
    ): array {
        $addActions = [];
        $removeActions = [];

        foreach ($resourcesToUpdate as $resource) {
            $thisResourcePermissions = $currentResourcePermissions[$resource->getUri()];

            $remove = $this->strategy->getPermissionsToRemove(
                $thisResourcePermissions,
                $permissionsDelta[$resource->getUri()] ?? []
            );

            if (!empty($remove)) {
                $removeActions[] = ['permissions' => $remove, 'resource' => $resource];
            }

            $add = $this->strategy->getPermissionsToAdd(
                $thisResourcePermissions,
                $permissionsDelta[$resource->getUri()] ?? []
            );

            if (!empty($add)) {
                $addActions[] = ['permissions' => $add, 'resource' => $resource];
            }
        }

        return $this->deduplicateActions(['add' => $addActions, 'remove' => $removeActions]);
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
        $this->getLogger()->info(sprintf("Triggering events for %s", $resourceId));

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
