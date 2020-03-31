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

class SyncPermissionsStrategy extends PermissionsStrategyAbstract
{
    public function normalizeRequest(array $currentPrivileges, array $privilegesToSet): array
    {
        // we are going to add everything what current item has and remove the rest
        return [
            'add' => $privilegesToSet
        ];
    }

    public function getPermissionsToAdd(array $currentPrivileges, array $addRemove): array
    {
        if (empty($addRemove['add'])) {
            return [];
        }

        // we are adding everything except we already have
        return $this->arrayDiffRecursive($addRemove['add'], $currentPrivileges);
    }

    public function getPermissionsToRemove(array $currentPrivileges, array $addRemove): array
    {
        if (empty($addRemove['add'])) {
            return [];
        }

        // we are removing everything we do not need
        return $this->arrayDiffRecursive($currentPrivileges, $addRemove['add']);
    }
}
