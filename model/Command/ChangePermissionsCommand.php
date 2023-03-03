<?php

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

    public function rootIsAClass(): bool
    {
        return $this->root->isClass();
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
