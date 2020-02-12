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

use RuntimeException;

abstract class PermissionsStrategyAbstract implements PermissionsStrategyInterface
{
    protected function arrayDiffRecursive(array $array1, array $array2): array
    {
        $outputDiff = [];

        if (!($this->is_assoc($array1) || $this->is_assoc($array2))) {
            return array_values(array_diff($array1, $array2));
        }

        foreach ($array1 as $array1key => $array1value) {
            if (array_key_exists($array1key, $array2)) {
                if (is_array($array1value) && is_array($array2[$array1key])) {
                    $outputDiff[$array1key] = $this->arrayDiffRecursive($array1value, $array2[$array1key]);
                } else  {
                    throw new RuntimeException('Inconsistent data');
                }
            } else {
                $outputDiff[$array1key] = $array1value;
            }
        }

        $result = [];

        foreach ($outputDiff as $i => $key) {
            if (!empty($key)) {
                $result[$i] = $key;
            }
        }

        return $result;
    }

    protected function arrayIntersectRecursive(array $array1, array $array2): array
    {
        $return = [];

        if (!($this->is_assoc($array1) || $this->is_assoc($array2))) {
            return array_intersect($array1, $array2);
        }

        $commonKeys = array_intersect(array_keys($array1), array_keys($array2));

        foreach ($commonKeys as $key) {
            if (is_array($array1[$key]) && is_array($array2[$key])) {
                $intersection = $this->arrayIntersectRecursive($array1[$key], $array2[$key]);

                if ($intersection) {
                    $return[$key] = $intersection;
                }
            } else if ($array1[$key] === $array2[$key]) {
                $return[$key] = $array1[$key];
            }
        }

        return $return;
    }

    private function is_assoc(array $array): bool
    {
        return count(array_filter(array_keys($array), 'is_string')) > 0;
    }

    /**
     * get the delta between existing permissions and new permissions
     *
     * @param array $currentPrivileges
     * @param array $privilegesToSet associative array $user_id => $permissions
     *
     * @return array
     */
    public function getDeltaPermissions(array $currentPrivileges, array $privilegesToSet): array
    {
        foreach ($privilegesToSet as $userId => $privilegeIds) {
            //if privileges are in request but not in db we add then
            if (!isset($currentPrivileges[$userId])) {
                if ($privilegeIds) {
                    $add[$userId] = $privilegeIds;
                }
            } // compare privileges in db and request
            else {
                $tmp = array_values(array_diff($privilegeIds, $currentPrivileges[$userId]));
                if ($tmp) {
                    $add[$userId] = $tmp;
                }

                $tmp = array_values(array_diff($currentPrivileges[$userId], $privilegeIds));

                if ($tmp) {
                    $remove[$userId] = $tmp;
                }
                // unset already compare db variable
                unset($currentPrivileges[$userId]);
            }
        }

        //remaining privileges has to be removed
        foreach ($currentPrivileges as $userId => $privilegeIds) {
            $remove[$userId] = $privilegeIds;
        }

        return compact('remove', 'add');
    }
}
