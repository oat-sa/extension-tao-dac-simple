<?php

declare(strict_types=1);

namespace oat\taoDacSimple\model;

use core_kernel_classes_Resource;
use oat\generis\model\OntologyRdfs;
use oat\oatbox\event\EventManager;
use oat\tao\model\event\DataAccessControlChangedEvent;
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

    public function change(core_kernel_classes_Resource $resource, array $permissionsToSet, bool $isRecursive): void
    {
        $resources = $this->getResourceToUpdate($resource, $isRecursive);
        $this->enrichWithPermissions($resources);
        $rootResourcePermissions = $resources[$resource->getUri()]['permissions'];

        $permissionsDelta = $this->strategy->getDeltaPermissions(
            $rootResourcePermissions['current'] ?? [],
            $permissionsToSet
        );

        if (empty($permissionsDelta['remove']) && empty($permissionsDelta['add'])) {
            return;
        }

        $resources = $this->enrichWithAddRemoveActions($resources, $permissionsDelta);
        $this->dryRun($resources);
        $this->wetRun($resources);

        $this->triggerEvents($resource, $permissionsDelta, $isRecursive);
    }

    private function getResourceToUpdate(core_kernel_classes_Resource $resource, bool $isRecursive): array
    {
        if ($isRecursive) {
            $resources = [];

            foreach ($this->dataBaseAccess->getResourceTree($resource) as $result) {
                $resources[$result['subject']] = [
                    'id' => $result['subject'],
                    'isClass' => $result['predicate'] === OntologyRdfs::RDFS_SUBCLASSOF,
                    'level' => $result['level']
                ];
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

    private function wetRun(array $resources): void
    {
        $this->dataBaseAccess->removeResourcesPermissions($resources);
        $this->dataBaseAccess->addResourcesPermissions($resources);
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