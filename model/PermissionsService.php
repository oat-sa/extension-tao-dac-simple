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
use oat\taoDacSimple\model\event\DacAffectedUsersEvent;
use oat\taoDacSimple\model\event\DacRootAddedEvent;
use oat\taoDacSimple\model\event\DacRootRemovedEvent;
use Generator;
use SplStack;

class PermissionsService
{
    use LoggerAwareTrait;

    /** @var DataBaseAccess */
    private $dataBaseAccess;

    /** @var PermissionsStrategyInterface */
    private $strategy;

    /** @var EventManager */
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

    /**
     * Returns an opaque array holding the list of actions to be performed
     * (i.e. a permissions diff) to fulfill a user's change permissions request.
     */
    public function getNormalizedRequest(
        core_kernel_classes_Resource $resource,
        array $privilegesToSet
    ): array {
        return $this->strategy->normalizeRequest(
            $this->dataBaseAccess->getResourcePermissions($resource->getUri()),
            $privilegesToSet
        );
    }

    // @todo Check unit tests

    public function savePermissions(
        bool $isRecursive,
        core_kernel_classes_Class $class,
        array $privilegesToSet
    ): void {
        $this->setPermissionsByResourceRootAndTriggerEvents(
            $class,
            $this->getNormalizedRequest($class, $privilegesToSet),
            $isRecursive
        );
    }

    public function saveResourcePermissionsRecursive(
        core_kernel_classes_Resource $resource,
        array $privilegesToSet
    ): void {
        $this->setPermissionsByResourceRootAndTriggerEvents(
            $resource,
            $this->getNormalizedRequest($resource, $privilegesToSet),
            true
        );
    }

    public function savePermissionsForMultipleResources(
        core_kernel_classes_Resource $root,
        array $resourcesToUpdate,
        array $normalizedRequest
    ): void {
        if (empty($normalizedRequest)) {
            $this->debug("Saving permissions for %s: Nothing to do", $root->getUri());

            return;
        }

        $this->applyRequestForResources($resourcesToUpdate, $normalizedRequest);
    }

    public function triggerEventsForRootResource(
        core_kernel_classes_Resource $root,
        array $normalizedRequest,
        bool $isRecursive
    ): void {
        $this->triggerEvents($normalizedRequest, $root->getUri(), $isRecursive);
    }

    /**
     * @return Generator<core_kernel_classes_Resource>
     */
    public function getNestedResources(core_kernel_classes_Resource $resource): Generator
    {
        $stack = new SplStack();
        $stack->push($resource);

        while (!$stack->isEmpty()) {
            /** @var $current core_kernel_classes_Resource */
            $current = $stack->pop();
            yield $current;

            if ($current->isClass()) {
                /** @var $current core_kernel_classes_Class */
                foreach ($current->getSubClasses() as $class) {
                    $stack->push($class);
                }

                foreach ($current->getInstances() as $instance) {
                    yield $instance;
                }
            }
        }
    }

    private function setPermissionsByResourceRootAndTriggerEvents(
        core_kernel_classes_Resource $resource,
        array $normalizedRequest,
        bool $isRecursive
    ): void {
        if (!empty($normalizedRequest)) {
            $this->applyRequestForResources(
                $this->getResourcesToUpdate($resource, $isRecursive),
                $normalizedRequest
            );

            $this->triggerEvents($normalizedRequest, $resource->getUri(), $isRecursive);
        }
    }

    private function applyRequestForResources(array $resources, array $normalizedRequest): void
    {
        $permissionsList = $this->getResourcesPermissions($resources);
        $actions = $this->getActions($resources, $permissionsList, $normalizedRequest);

        $this->dryRun($actions, $permissionsList);

        $this->debug(
            'applyRequestForResources(): Applying %d operations on %d resources',
            count($actions['add'] ?? []) + count($actions['remove'] ?? []),
            count($resources)
        );

        $this->wetRun($actions);
    }

    private function getActions(array $resourcesToUpdate, array $permissionsList, array $addRemove): array
    {
        $this->debug('getActions(): Handling %d resources', count($resourcesToUpdate));

        $actions = ['remove' => [], 'add' => []];

        $i = 0;
        foreach ($resourcesToUpdate as $resource) {
            //$this->debug('getActions(): Handling resource %d/%d', ++$i, count($resourcesToUpdate));

            $currentPrivileges = $permissionsList[$resource->getUri()];

            $remove = $this->strategy->getPermissionsToRemove($currentPrivileges, $addRemove);
            if (count($remove) > 0) {
                $this->debug('getActions(): resource %s count($remove)=%d', $i, count($remove));
            }

            if ($remove) {
                $actions['remove'][] = ['permissions' => $remove, 'resource' => $resource];
            }

            $add = $this->strategy->getPermissionsToAdd($currentPrivileges, $addRemove);
            if (count($add) > 0) {
                $this->debug('getActions(): resource %s count($add)=%d', $i, count($add));
            }

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

        $this->assertGrantExistsForAllResources($resultPermissions);
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

    private function debug(string $format, ...$va_args): void
    {
        static $logger = null;
        if (!$logger) {
            $logger = $this->getLogger();
        }

        $logger->info(self::class . ': ' . vsprintf($format, $va_args));
    }

    private function getResourcesToUpdate(
        core_kernel_classes_Resource $resource,
        bool $isRecursive
    ): array {
        $resources = [$resource];

        if ($isRecursive && $resource->isClass()) {
            return array_merge($resources, $resource->getSubClasses(true), $resource->getInstances(true));
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

    private function triggerEvents(array $addRemove, string $resourceId, bool $isRecursive): void
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
                $isRecursive
            )
        );
    }

    /**
     * Checks that all resources after all actions are applied will have at
     * least one user with GRANT permission.
     *
     * @throws PermissionsServiceException if a resource misses a GRANT ACL
     */
    private function assertGrantExistsForAllResources(array $resultPermissions): void
    {
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
}
