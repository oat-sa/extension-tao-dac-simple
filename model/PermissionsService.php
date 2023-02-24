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
use Generator;
use oat\oatbox\event\EventManager;
use oat\oatbox\log\LoggerAwareTrait;
use oat\tao\model\event\DataAccessControlChangedEvent;
use oat\taoDacSimple\model\event\DacAffectedUsersEvent;
use oat\taoDacSimple\model\event\DacRootAddedEvent;
use oat\taoDacSimple\model\event\DacRootRemovedEvent;
use SplQueue;

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
        $isHugeClass = ($isRecursive && $this->isHugeClass($resource, 500));
        $this->debug(
            "Saving permissions for %s, isHugeClass=%s",
            $resource->getUri(),
            $isHugeClass ? 'true' : 'false'
        );

        $currentPrivileges = $this->dataBaseAccess->getResourcePermissions($resource->getUri());
        $addRemove = $this->strategy->normalizeRequest($currentPrivileges, $privilegesToSet);

        if (empty($addRemove)) {
            $this->debug(
                "Saving permissions for %s: Nothing to do",
                $resource->getUri()
            );

            return;
        }

        // Note here we are already on the (single) task used to update the permissions,
        // maybe we want to spawn additional tasks instead
        //
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

        /* @fixme The memory issue seems caused at first because of requesting the full array of resources to be updated
         *          Once a worker goes up in its memory usage, it seems it never gives the memory back to the OS
         *          (next runs start with a memory usage close to the previous peak)
         *
         * 19:09:30 About to update ACLs for http://www.tao.lu/Ontologies/TAOItem.rdf#Item (recursive=true, memUsage=6.144/6.144 kB)
         * 19:09:30 addRemove.add size = 47 addRemove.remove size =0 (memUsage=6.144 / 6.144 kB)
         * 19:09:30 resourcesToUpdate contains 5781 resources (memUsage=10.240 / 10.240 kB)
         */
        $resourcesToUpdate = $this->getResourcesToUpdate($resource, $isRecursive);

        $this->debug(
            "resourcesToUpdate contains %d resources (memUsage=%u/%u kB)",
            count($resourcesToUpdate),
            round(memory_get_usage(true) / 1024),
            round(memory_get_peak_usage(true) / 1024)
        );

        /* @fixme
         * 19:09:31 permissionsList contains 5781 resources (recursive=578.100, memUsage=421.888/438.276 kB)
         *          ----> Memory has raised from 6M to 440 M
         *
         *                  *********THIS IS ALREADY PROBLEMATIC********
         *                  We may spawn subtasks in case we have 1K resources (at that point we are just using 1M)
         *                  @fixme Keep in mind this is a service and not a task, better not to spawn subtasks from here
         *                         Maybe we can have a helper here that instructs the task to spawn subtasks
         *
         * 19:09:31 ::getActions(): Handling 5781 resources
         * 19:09:31 ::getActions(): Handling resource 1/5781
         * ...
         * 19:09:31 ::deduplicateActions(): Handling 5781 + 0 actions
         * 19:09:31 actions contains 2 actions (memUsage=555.008/555.008 kB)
         *          ----> Memory has raised from 440 M to 555 M
         *
         * 19:09:32 dryRun completed (memUsage=555008/555008 kB)
         * 19:09:32 Processing chunk 1/27 with 20000 ACL entries
         * ...
         * 19:11:58 Processed 537633 inserts {"count":537633}
         * 19:11:58 Triggering 537633 events
         * 19:11:59 Triggered 1000 / 537.633 events (memUsage=712.708 / 761.624 kB)
         *          ------> Memory has raised from 550 M to 760 M
         *
         * 19:12:00 Triggered 2000 / 537.633 events (memUsage=712.708 / 761.624 kB)
         * 19:12:00 Triggered 3000 / 537.633 events (memUsage=712.708 / 761.624 kB)
         * ... Memory usage (actual and peak) remains constant while events are triggered ....
         *
         * 19:19:58 Triggered all events (3)
         * 19:19:58 oat\taoDacSimple\model\PermissionsService: wetRun completed (memUsage=679.936/761.624 kB)
         *
         * 19:19:58 oat\taoDacSimple\model\PermissionsService: Will trigger events now
         * 19:20:02 triggering index update on DataAccessControlChanged event
         * 19:20:02 oat\taoDacSimple\model\PermissionsService: events triggered (memUsage=1.019.904/1.078.156 kB)
         *          -------> Memory has raised from 760 M to 1.08 GB after the events are triggered
         *
         * 19:20:02 Task https://adf-1320-dac-simple-batches.docker.localhost/ontologies/tao.rdf#i63e536d538a168121b8d50bbd8f314 has been processed.
         *      {"PID":9745,"Iteration":13,"QueueName":"queue"} {"uid":"45e8130e66b8b584ad94f4ea"}
         * ---
         * --------------------------
         *  17:51:45 addRemove.add size = 32 addRemove.remove size =0 (memUsage=6144/6144 kB)
         *
         *  17:51:45 resourcesToUpdate contains 5781 resources (memUsage=10240/10240 kB)
         *        10M / 10M
         *  17:51:46 permissionsList contains 5781 resources (recursive=398889, memUsage=296960/305156 kB)
         *        296MM / 305M
         *  18:17:35 actions contains 2 actions (memUsage=854016/924732 kB)
         *        854M / 924M
         *
         * Note once the worker requests memory to the OS, it seems it never releases it.
         */
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
        $this->triggerEvents($addRemove, $resource->getUri(), $isRecursive);

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

    /**
     * @fixme Maybe should not be public, and/or we should move this responsibility to the task
     *        and/or we should make the threshold configurable
     */
    public function isHugeClass(core_kernel_classes_Resource $resource, int $threshold): bool
    {
        if ($resource->isClass()) {
            // Ensure we have a core_kernel_classes_Class instance
            $class = $resource->getClass($resource->getUri());

            return ($class->countInstances([], ['recursive' => true]) >= $threshold);
        }

        return false;
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
        $queue = new SplQueue();
        $queue->push($resource);

        while (!$queue->isEmpty()) {
            /** @var $current core_kernel_classes_Resource */
            $current = $queue->pop();
            yield $current;

            if ($current->isClass()) {
                /** @var $current core_kernel_classes_Class */
                foreach ($current->getSubClasses() as $class) {
                    $queue->push($class);
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
