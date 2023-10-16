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

namespace oat\taoDacSimple\model\event;

use JsonSerializable;
use oat\oatbox\event\Event;

class DacRootChangedEvent implements Event, JsonSerializable
{
    private string $resourceUri;
    private array $permissionsDelta;

    public function __construct(string $resourceUri, array $permissionsDelta)
    {
        $this->resourceUri = $resourceUri;
        $this->permissionsDelta = $permissionsDelta;
    }

    public function getResourceUri(): string
    {
        return $this->resourceUri;
    }

    public function getPermissionsDelta(): array
    {
        return $this->permissionsDelta;
    }

    public function getName(): string
    {
        return __CLASS__;
    }

    public function jsonSerialize(): array
    {
        return [
            'resourceUri' => $this->resourceUri,
            'permissionsDelta' => $this->permissionsDelta,
        ];
    }
}
