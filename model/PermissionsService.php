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
use SplQueue;
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

    public function savePermissions(
        bool $isRecursive,
        core_kernel_classes_Class $class,
        array $privilegesToSet
    ): void {
        $this->saveResourcePermissions($class, $privilegesToSet, $isRecursive);
    }

    public function saveResourcePermissionsRecursive(
        core_kernel_classes_Resource $resource,
        array $privilegesToSet
    ): void {
        $this->saveResourcePermissions($resource, $privilegesToSet, true);
    }

    private function saveResourcePermissions(
        core_kernel_classes_Resource $resource,
        array $privilegesToSet,
        bool $isRecursive
    ): void {
        $currentPrivileges = $this->dataBaseAccess->getResourcePermissions($resource->getUri());
        $addRemove = $this->strategy->normalizeRequest($currentPrivileges, $privilegesToSet);

        if (empty($addRemove)) {
            $this->debug("Saving permissions for %s: Nothing to do", $resource->getUri());

            return;
        }

        $this->debug(
            "About to update ACLs for %s (recursive=%s, memUsage=%u/%u kB)",
            $resource->getUri(),
            $isRecursive ? 'true' : 'false',
            round(memory_get_usage(true) / 1024),
            round(memory_get_peak_usage(true) / 1024)
        );

        $this->debug(
            "addRemove.add size = %u addRemove.remove size =%u (memUsage=%u/%u kB)",
            count($addRemove['add'] ?? []),
            count($addRemove['remove'] ?? []),
            round(memory_get_usage(true) / 1024),
            round(memory_get_peak_usage(true) / 1024)
        );

        // Will have just a single one if $isRecursive == false
        $resourcesToUpdate = $this->getResourcesToUpdate($resource, $isRecursive);

        $this->debug(
            "resourcesToUpdate contains %d resources (memUsage=%u/%u kB)",
            count($resourcesToUpdate),
            round(memory_get_usage(true) / 1024),
            round(memory_get_peak_usage(true) / 1024)
        );

        $this->setPermissionsForMultipleResources(
            $resource,
            $resourcesToUpdate,
            $privilegesToSet
        );
    }

    public function setPermissionsForMultipleResources(
        core_kernel_classes_Resource $root,
        array $resourcesToUpdate,
        array $privilegesToSet,
        bool $isRecursive
    ): void {
        $currentPrivileges = $this->dataBaseAccess->getResourcePermissions($root->getUri());
        $addRemove = $this->strategy->normalizeRequest($currentPrivileges, $privilegesToSet);

        // @fixme !!!!! $addRemove will be non-empty only in the first subtask: That one will
        //        find the root with the old permissions, but next ones will find the root with
        //        the permissions already set
        //        The value for $addRemove is likely to be computed by the root task and then
        //        just reused from subtasks

        // We don't want to just check it there are permissions to be changed in the "root"
        // resource, as this will be used for multiple resources: The root may have been already
        // changed by a previous call (from another "subtask") associated with the same user request.

        /*if (empty($addRemove)) {
            $this->debug("Saving permissions for %s: Nothing to do", $root->getUri());

            return;
        }*/

        $this->doSaveResourcePermissions(
            $root,
            $resourcesToUpdate,
            $addRemove,
            $isRecursive,
        );
    }

    private function doSaveResourcePermissions(
        core_kernel_classes_Resource $root,
        array $resourcesToUpdate,
        array $addRemove,
        bool $isRecursive
    ): void {
        $permissionsList = $this->getResourcesPermissions($resourcesToUpdate);
        $this->debug(
            "permissionsList contains %d resources (recursive=%d, memUsage=%u/%u kB)",
            count($permissionsList),
            count($permissionsList, COUNT_RECURSIVE),
            round(memory_get_usage(true) / 1024),
            round(memory_get_peak_usage(true) / 1024)
        );

        $actions = $this->getActions($resourcesToUpdate, $permissionsList, $addRemove);
        $this->debug(
            "actions contains %d actions (memUsage=%u/%u kB)",
            count($actions),
            round(memory_get_usage(true) / 1024),
            round(memory_get_peak_usage(true) / 1024)
        );

        $this->dryRun($actions, $permissionsList);
        $this->debug(
            "dryRun completed (memUsage=%u/%u kB)",
            round(memory_get_usage(true) / 1024),
            round(memory_get_peak_usage(true) / 1024)
        );

        $this->wetRun($actions);
        $this->debug(
            "wetRun completed (memUsage=%u/%u kB)",
            round(memory_get_usage(true) / 1024),
            round(memory_get_peak_usage(true) / 1024)
        );

        $this->debug("Will trigger events now");
        $this->triggerEvents($addRemove, $root->getUri(), $isRecursive);
        $this->debug('events triggered (memUsage=%u/%u kB)',
            self::class,
            round(memory_get_usage(true) / 1024),
            round(memory_get_peak_usage(true) / 1024)
        );
    }

    private function getActions(array $resourcesToUpdate, array $permissionsList, array $addRemove): array
    {
        $this->debug('getActions(): Handling %d resources', count($resourcesToUpdate));

        $actions = ['remove' => [], 'add' => []];

        $i = 0;
        foreach ($resourcesToUpdate as $resource) {
            $this->debug('getActions(): Handling resource %d/%d', ++$i, count($resourcesToUpdate));

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

        $this->validateResources($resultPermissions);
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
        $this->debug(
            'deduplicateActions(): Handling %d + %d actions',
            count($actions['add']),
            count($actions['remove'])
        );

        //$i = 0;
        foreach ($actions['add'] as &$entry) {
            /*$logger->info(
                sprintf(
                    '%s::deduplicateActions(): Handling add action: %d/%d',
                    self::class,
                    ++$i,
                    count($actions['add'])
                )
            );*/

            foreach ($entry['permissions'] as &$grants) {
                $grants = array_unique($grants);
            }
        }

        //$i = 0;
        foreach ($actions['remove'] as &$entry) {
            /*$logger->info(
                sprintf(
                    '%s::deduplicateActions(): Handling remove action: %d/%d',
                    self::class,
                    ++$i,
                    count($actions['add'])
                )
            );*/

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
     * @param array $addRemove
     * @param string $resourceId
     * @param bool $isRecursive
     */

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
}
