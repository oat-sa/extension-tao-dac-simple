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

use core_kernel_classes_Resource;
use JsonSerializable;
use oat\oatbox\event\Event;

class DacRootChangedEvent implements Event, JsonSerializable
{
    private core_kernel_classes_Resource $resource;
    private array $permissionsDelta;

    public function __construct(core_kernel_classes_Resource $resource, array $permissionsDelta)
    {
        $this->resource = $resource;
        $this->permissionsDelta = $permissionsDelta;
    }

    public function getResource(): core_kernel_classes_Resource
    {
        return $this->resource;
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
            'resourceUri' => $this->getResource()->getUri(),
            'permissionsDelta' => $this->permissionsDelta,
        ];
    }
}
