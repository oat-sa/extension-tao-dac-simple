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

use core_kernel_classes_Resource;

class RevokeAccessCommand
{
    private core_kernel_classes_Resource $root;

    private string $masterRequest;

    /**
     * An array in the form ['resourceId' => ['userId1', 'userId2']]
     *
     * @var string[][]
     */
    private array $resourceMap = [];

    public function __construct() {
    }

    public function revokeResourceForUser(string $resourceId, string $userId): void
    {
        $this->resourceMap[$resourceId] = $this->resourceMap[$resourceId] ?? [];
        $this->resourceMap[$resourceId] = array_unique(array_merge($this->resourceMap[$resourceId], [$userId]));
    }

    public function cancelRevokeResourceForUser(string $resourceId, string $userId): void
    {
        $this->resourceMap[$resourceId] = $this->resourceMap[$resourceId] ?? [];

        $key = array_search($userId, $this->resourceMap[$resourceId]);

        if ($key === false) {
            return;
        }

        unset($this->resourceMap[$resourceId][$key]);
    }

    public function getResourceIdsToRevoke(): array
    {
        return array_keys($this->resourceMap);
    }

    public function getUserIdsToRevoke(string $resourceId): array
    {
        return $this->resourceMap[$resourceId] ?? [];
    }
}
