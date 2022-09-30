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
 * Copyright (c) 2020-2022 (original work) Open Assessment Technologies SA.
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

class PermissionsService
{
    use LoggerAwareTrait;

    private const ACTIONS_ADD = 'add';

    private const ACTIONS_REMOVE = 'remove';

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
        $resourceURI = $resource->getUri();

        $this->debug(
            'saveResourcePermissions resource=%s recursive=%s: privileges=%s',
            $resourceURI,
            $isRecursive ? 'true' : 'false',
            var_export($privilegesToSet, true)
        );

        $currentPrivileges = $this->dataBaseAccess->getResourcePermissions($resourceURI);
        $deltaPermissions = $this->strategy->normalizeRequest(
            $currentPrivileges,
            $privilegesToSet
        );

        if (empty($deltaPermissions)) {
            $this->debug('No changes needed in ACLs for %s', $resourceURI);
            return;
        }

        $this->debug(
            'Permissions to add or remove: %s',
            var_export($deltaPermissions, true)
        );

        $resourcesToUpdate = $this->getResourcesToUpdate($resource, $isRecursive);
        $permissionsList = $this->getResourcesPermissions($resourcesToUpdate);
        $actions = $this->getActions($resourcesToUpdate, $permissionsList, $deltaPermissions);

        $this->logResourcesToUpdate($resourcesToUpdate);
        $this->debug(
            'Current resources\' permissions: %s',
            var_export($permissionsList, true)
        );
        $this->logActions('remove', $actions);
        $this->logActions('add', $actions);

        $this->dryRun($actions, $permissionsList);
        $this->wetRun($actions);
        $this->triggerEvents($deltaPermissions, $resourceURI, $isRecursive);
    }

    private function getActions(
        array $resourcesToUpdate,
        array $permissionsList,
        array $addRemove
    ): array {
        $actions = [
            self::ACTIONS_REMOVE => [],
            self::ACTIONS_ADD => []
        ];

        foreach ($resourcesToUpdate as $resource) {
            $currentPrivileges = $permissionsList[$resource->getUri()];

            $remove = $this->strategy->getPermissionsToRemove($currentPrivileges, $addRemove);
            if (!empty($remove)) {
                $actions[self::ACTIONS_REMOVE][] = [
                    'permissions' => $remove,
                    'resource' => $resource
                ];
            }

            $add = $this->strategy->getPermissionsToAdd($currentPrivileges, $addRemove);
            if (!empty($add)) {
                $actions[self::ACTIONS_ADD][] = [
                    'permissions' => $add,
                    'resource' => $resource
                ];
            }
        }

        return $this->deduplicateActions($actions);
    }

    private function dryRun(array $actions, array $permissionsList): void
    {
        $resultPermissions = $permissionsList;

        foreach ($actions[self::ACTIONS_REMOVE] as $item) {
            $this->dryRemove($item['permissions'], $item['resource'], $resultPermissions);
        }
        foreach ($actions[self::ACTIONS_ADD] as $item) {
            $this->dryAdd($item['permissions'], $item['resource'], $resultPermissions);
        }

        $this->validateResources($resultPermissions);
    }

    private function wetRun(array $actions): void
    {
        if (!empty($actions[self::ACTIONS_REMOVE])) {
            $this->dataBaseAccess->removeMultiplePermissions($actions['remove']);
        }
        if (!empty($actions[self::ACTIONS_ADD])) {
            $this->dataBaseAccess->addMultiplePermissions($actions['add']);
        }
    }

    private function dryRemove(
        array $remove,
        core_kernel_classes_Resource $resource,
        array &$resultPermissions
    ): void {
        foreach ($remove as $userToRemove => $permissionToRemove) {
            if (!empty($resultPermissions[$resource->getUri()][$userToRemove])) {
                $resultPermissions[$resource->getUri()][$userToRemove] = array_diff(
                    $resultPermissions[$resource->getUri()][$userToRemove],
                    $permissionToRemove
                );
            }
        }
    }

    private function dryAdd(
        array $add,
        core_kernel_classes_Resource $resource,
        array &$resultPermissions
    ): void {
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

    /**
     * It is likely the strategy has not checked for duplicate permissions;
     * therefore, we need an additional step to remove them.
     *
     * A duplicate ACL may surface in cases the same grant exists for a user
     * both at the class and item level, and is needed here in case the strategy
     * used doesn't handle them on its own.
     */
    private function deduplicateActions(array $actions): array
    {
        foreach ($actions[self::ACTIONS_ADD] as &$entry) {
            foreach ($entry['permissions'] as $_uid => &$grants) {
                $grants = array_unique($grants);
            }
        }

        foreach ($actions[self::ACTIONS_REMOVE] as &$entry) {
            foreach ($entry['permissions'] as $_uid => &$grants) {
                $grants = array_unique($grants);
            }
        }

        return $actions;
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
        if (!empty($addRemove[self::ACTIONS_ADD])) {
            foreach ($addRemove[self::ACTIONS_ADD] as $userId => $rights) {
                $this->eventManager->trigger(
                    new DacRootAddedEvent($userId, $resourceId, (array)$rights)
                );
            }
        }
        if (!empty($addRemove[self::ACTIONS_REMOVE])) {
            foreach ($addRemove[self::ACTIONS_REMOVE] as $userId => $rights) {
                $this->eventManager->trigger(
                    new DacRootRemovedEvent($userId, $resourceId, (array)$rights)
                );
            }
        }
        $this->eventManager->trigger(
            new DacAffectedUsersEvent(
                array_keys($addRemove[self::ACTIONS_ADD] ?? []),
                array_keys($addRemove[self::ACTIONS_REMOVE] ?? [])
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

    private function debug(string $format, ...$va_args): void
    {
        $this->getLogger()->debug(
            self::class . ': ' . vsprintf($format, $va_args)
        );
    }

    private function logActions(string $what, $actions): void
    {
        $this->debug(
            "{$what}=%s",
            implode(
                ',',
                array_map(
                    function ($r) {
                        return var_export([
                            'resource'  => $r['resource']->getUri(),
                            'permissions' => $r['permissions'],
                        ], true);
                    },
                    $actions[$what]
                )
            )
        );
    }

    private function logResourcesToUpdate(array $resourcesToUpdate): void
    {
        $this->debug(
            'Resources to be updated: %s',
            implode(
                ', ',
                array_map(
                    function ($r) {
                        return $r->getUri();
                    },
                    $resourcesToUpdate
                )
            )
        );
    }
}
