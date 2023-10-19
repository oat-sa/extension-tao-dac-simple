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
 * Copyright (c) 2023 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

namespace oat\taoDacSimple\model\Command;

class ChangeAccessCommand
{
    /**
     * An array in the form ['resourceId' => ['userId1', 'userId2']]
     *
     * @var string[][]
     */
    private array $removeAccessMap = [];

    /**
     * An array in the form ['resourceId' [ 'READ' => ['userId1', 'userId2']]]
     *
     * @var string[][][]
     */
    private array $giveAccessMap = [];

    /**
     * An array in the form ['resourceId' [ 'READ' => ['userId1', 'userId2']]]
     *
     * @var string[][][]
     */
    private array $revokeAccessMap = [];

    public function __construct() {
    }

    public function removeResourceForUser(string $resourceId, string $userId): void
    {
        $this->removeAccessMap[$resourceId] = $this->removeAccessMap[$resourceId] ?? [];
        $this->removeAccessMap[$resourceId] = array_unique(array_merge($this->removeAccessMap[$resourceId], [$userId]));
    }

    public function cancelRemoveResourceForUser(string $resourceId, string $userId): void
    {
        $this->removeAccessMap[$resourceId] = $this->removeAccessMap[$resourceId] ?? [];

        $key = array_search($userId, $this->removeAccessMap[$resourceId]);

        if ($key === false) {
            return;
        }

        unset($this->removeAccessMap[$resourceId][$key]);
    }

    public function getResourceIdsToRemove(): array
    {
        return array_keys($this->removeAccessMap);
    }

    public function getUserIdsToRemove(string $resourceId): array
    {
        return $this->removeAccessMap[$resourceId] ?? [];
    }

    public function grantResourceForUser(string $resourceId, string $permission, string $userId): void
    {
        $this->giveAccessMap[$resourceId] = $this->giveAccessMap[$resourceId] ?? [];
        $this->giveAccessMap[$resourceId][$permission] = $this->giveAccessMap[$resourceId][$permission] ?? [];
        $this->giveAccessMap[$resourceId][$permission] = array_unique(
            array_merge(
                $this->giveAccessMap[$resourceId][$permission],
                [$userId]
            )
        );
    }

    public function getResourceIdsToGrant(): array
    {
        return array_keys($this->giveAccessMap);
    }

    public function getUserIdsToGrant(string $resourceId, string $permission): array
    {
        return $this->giveAccessMap[$resourceId][$permission] ?? [];
    }

    public function revokeResourceForUser(string $resourceId, string $permission, string $userId): void
    {
        $this->revokeAccessMap[$resourceId] = $this->revokeAccessMap[$resourceId] ?? [];
        $this->revokeAccessMap[$resourceId][$permission] = $this->revokeAccessMap[$resourceId][$permission] ?? [];
        $this->revokeAccessMap[$resourceId][$permission] = array_unique(
            array_merge(
                $this->revokeAccessMap[$resourceId][$permission],
                [$userId]
            )
        );
    }

    public function getResourceIdsToRevoke(): array
    {
        return array_keys($this->revokeAccessMap);
    }

    public function getUserIdsToRevoke(string $resourceId, string $permission): array
    {
        return $this->revokeAccessMap[$resourceId][$permission] ?? [];
    }
}
