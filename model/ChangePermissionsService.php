<?php

namespace oat\taoDacSimple\model;

use core_kernel_classes_Resource;

class ChangePermissionsService
{
    private DataBaseAccess $dataBaseAccess;
    private PermissionsStrategyInterface $strategy;

    public function __construct(DataBaseAccess $dataBaseAccess, PermissionsStrategyInterface $strategy)
    {
        $this->dataBaseAccess = $dataBaseAccess;
        $this->strategy = $strategy;
    }

    public function __invoke(core_kernel_classes_Resource $resource, array $permissionsToSet, bool $isRecursive): void
    {
        $resourceIds = $this->getResourceIdsToUpdate($resource, $isRecursive);
        $currentPermissions = $this->getResourcesPermissions($resourceIds);
        $permissionsDelta = $this->strategy->getDeltaPermissions(
            $currentPermissions[$resource->getUri()] ?? [],
            $permissionsToSet
        );

        if (empty($permissionsDelta)) {
            return;
        }

        $actions = $this->getActions($resourceIds, $currentPermissions, $permissionsDelta);
        $this->dryRun($actions, $currentPermissions);
        $this->wetRun($actions);




        // >>> UDIR

        // <<< UDIR
    }

    /*
    private function triggerEvents(
        string $resourceId,
        string $rootResourceId,
        array $addRemove,
        bool $isRecursive,
        bool $applyToNestedResources
    ): void {
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
                $isRecursive,
                $applyToNestedResources,
                $rootResourceId
            )
        );
    }*/

    private function getResourceIdsToUpdate(core_kernel_classes_Resource $resource, bool $isRecursive): array
    {
        if ($isRecursive) {
            return $this->dataBaseAccess->getTreeIds($resource);
        }

        return [$resource->getUri()];
    }

    private function getResourcesPermissions(array $resourceIds): array
    {
        if (empty($resourceIds)) {
            return [];
        }

        return $this->dataBaseAccess->getResourcesPermissions($resourceIds);
    }

    private function getActions(
        array $resourceIdsToUpdate,
        array $currentResourcePermissions,
        array $permissionsDelta
    ): array {
        $addActions = [];
        $removeActions = [];

        foreach ($resourceIdsToUpdate as $resourceId) {
            $resourcePermissions = $currentResourcePermissions[$resourceId];

            $remove = $this->strategy->getPermissionsToRemove($resourcePermissions, $permissionsDelta);

            if (!empty($remove)) {
                $removeActions[] = ['permissions' => $remove, 'resourceId' => $resourceId];
            }

            $add = $this->strategy->getPermissionsToAdd($resourcePermissions, $permissionsDelta);

            if (!empty($add)) {
                $addActions[] = ['permissions' => $add, 'resourceId' => $resourceId];
            }
        }

        return $this->deduplicateActions(['add' => $addActions, 'remove' => $removeActions]);
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

    private function dryRun(array $actions, array $permissionsList): void
    {
        $resultPermissions = $permissionsList;

        foreach ($actions['remove'] as $item) {
            $this->dryRemove($item['permissions'], $item['resourceId'], $resultPermissions);
        }
        foreach ($actions['add'] as $item) {
            $this->dryAdd($item['permissions'], $item['resourceId'], $resultPermissions);
        }

        $this->assertHasUserWithGrantPermission($resultPermissions);
    }

    private function wetRun(array $actions): void
    {
        if (!empty($actions['remove'])) {
            $this->dataBaseAccess->removeMultiplePermissionsNew($actions['remove']);
        }
        if (!empty($actions['add'])) {
            $this->dataBaseAccess->addMultiplePermissionsNew($actions['add']);
        }
    }

    private function dryRemove(array $remove, string $resourceId, array &$resultPermissions): void
    {
        foreach ($remove as $userToRemove => $permissionToRemove) {
            if (!empty($resultPermissions[$resourceId][$userToRemove])) {
                $resultPermissions[$resourceId][$userToRemove] = array_diff(
                    $resultPermissions[$resourceId][$userToRemove],
                    $permissionToRemove
                );
            }
        }
    }

    private function dryAdd(array $add, string $resourceId, array &$resultPermissions): void
    {
        foreach ($add as $userToAdd => $permissionToAdd) {
            if (empty($resultPermissions[$resourceId][$userToAdd])) {
                $resultPermissions[$resourceId][$userToAdd] = $permissionToAdd;
            } else {
                $resultPermissions[$resourceId][$userToAdd] = array_merge(
                    $resultPermissions[$resourceId][$userToAdd],
                    $permissionToAdd
                );
            }
        }
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
}