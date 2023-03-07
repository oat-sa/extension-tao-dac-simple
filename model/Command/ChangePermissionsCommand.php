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

/**
 * Value object holding data about permission changes to be
 * done in a resource or class.
 */
class ChangePermissionsCommand
{
    private core_kernel_classes_Resource $root;

    /**
     * An array in the form ['userId' => ['READ', 'WRITE], ...]
     *
     * @var string[]
     */
    private array $privilegesPerUser;

    private bool $isRecursive;

    private bool $applyToNestedResources;

    public function __construct(
        core_kernel_classes_Resource $root,
        array $privileges,
        bool $isRecursive,
        bool $applyToNestedResources
    ) {
        $this->root = $root;
        $this->privilegesPerUser = $privileges;
        $this->isRecursive = $isRecursive;
        $this->applyToNestedResources = $applyToNestedResources;
    }

    /**
     * @return core_kernel_classes_Resource
     */
    public function getRoot(): core_kernel_classes_Resource
    {
        return $this->root;
    }

    /**
     * @return array
     */
    public function getPrivilegesPerUser(): array
    {
        return $this->privilegesPerUser;
    }

    /**
     * @return bool
     */
    public function isRecursive(): bool
    {
        return $this->isRecursive;
    }

    /**
     * @return bool
     */
    public function applyToNestedResources(): bool
    {
        return $this->applyToNestedResources;
    }
}
