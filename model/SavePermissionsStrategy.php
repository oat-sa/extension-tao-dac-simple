<?php

declare(strict_types=1);

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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA;
 *
 */

namespace oat\taoDacSimple\model;

class SavePermissionsStrategy extends PermissionsStrategyAbstract
{
    public function normalizeRequest(array $currentPrivileges, array $privilegesToSet): array
    {
        return $this->getDeltaPermissions($currentPrivileges, $privilegesToSet);
    }

    public function getPermissionsToAdd(array $currentPrivileges, array $addRemove): array
    {
        if (empty($addRemove['add'])) {
            return [];
        }

        $permissionsToAdd = $addRemove['add'];

        foreach ($permissionsToAdd as $userId => &$permissionToAdd) {
            $permissionsCount = count($permissionToAdd);

            if ($permissionsCount < 3) {
                $mandatoryFields = [];

                // check attempt to add only one permission which depend on other (w on r, g on w and r) and add
                // dependent permissions if necessary

                if (in_array(PermissionProvider::PERMISSION_GRANT, $permissionToAdd, true)) {
                    $mandatoryFields = [PermissionProvider::PERMISSION_WRITE, PermissionProvider::PERMISSION_READ];
                } elseif (in_array(PermissionProvider::PERMISSION_WRITE, $permissionToAdd, true)) {
                    $mandatoryFields = [PermissionProvider::PERMISSION_READ];
                }

                $permissionToAdd = array_values(array_diff(
                    array_unique(array_merge($permissionToAdd, $mandatoryFields)),
                    $currentPrivileges[$userId] ?? []
                ));

                if (empty($permissionToAdd)) {
                    unset($permissionsToAdd[$userId]);
                }
            }
        }

        return $permissionsToAdd;
    }

    public function getPermissionsToRemove(array $currentPrivileges, array $addRemove): array
    {
        if (empty($addRemove['remove'])) {
            return [];
        }

        $permissionsToRemove = $addRemove['remove'];

        foreach ($permissionsToRemove as $userId => &$permissionToRemove) {
            $mandatoryFields = [];

            if (in_array(PermissionProvider::PERMISSION_READ, $permissionToRemove, true)) {
                $mandatoryFields = [PermissionProvider::PERMISSION_WRITE, PermissionProvider::PERMISSION_GRANT];
            } elseif (in_array(PermissionProvider::PERMISSION_WRITE, $permissionToRemove, true)) {
                $mandatoryFields = [PermissionProvider::PERMISSION_GRANT];
            }

            $permissionToRemove = array_values(array_intersect(
                array_unique(array_merge($permissionToRemove, $mandatoryFields)),
                $currentPrivileges[$userId] ?? []
            ));
        }

        return $permissionsToRemove;
    }
}
