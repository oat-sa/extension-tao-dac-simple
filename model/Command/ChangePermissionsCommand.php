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
 * Holds data about permission changes to be done in a resource.
 *
 * By default, the command is set up to apply a given set of ACLs to a single
 * resource designated as the command's root. However, it also provides methods
 * to change its recursion settings after the command creation, making
 * PermissionsService to operate recursively or to modify class instances under
 * the root resource in case the root itself is a class.
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

    private bool $isRecursive = false;

    private bool $applyToNestedResources = false;

    public function __construct(
        core_kernel_classes_Resource $root,
        array $privileges
    ) {
        $this->root = $root;
        $this->privilegesPerUser = $privileges;
    }

    /**
     * Sets the isRecursive flag for the command.
     *
     * For commands having a class as the root, setting the recursion flag
     * makes PermissionsService to update permissions for the class and all its
     * descendants (i.e. updates all resources AND classes using the provided
     * root class as the initial node for a tree traversal, which may be slow
     * and resource-intensive).
     *
     * This is provided for backward compatibility purposes.
     */
    public function withRecursion(): void
    {
        $this->isRecursive = true;
    }

    /**
     * Sets the applyToNestedResources flag for the command.
     *
     * For commands having a class as the root AND not having the recursion
     * flag set, setting the nested resources flag makes PermissionsService to
     * update permissions for the class and all instances of that class, but
     * skips all nested classes and instances of them (i.e. does not go down
     * into nested levels of the resource tree).
     */
    public function withNestedResources(): void
    {
        $this->applyToNestedResources = true;
    }

    public function getRoot(): core_kernel_classes_Resource
    {
        return $this->root;
    }

    public function getPrivilegesPerUser(): array
    {
        return $this->privilegesPerUser;
    }

    public function isRecursive(): bool
    {
        return $this->isRecursive;
    }

    public function applyToNestedResources(): bool
    {
        return $this->applyToNestedResources;
    }
}
