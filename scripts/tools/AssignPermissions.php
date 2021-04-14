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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoDacSimple\scripts\tools;

use Exception;
use Throwable;
use core_kernel_classes_Class;
use oat\oatbox\reporting\Report;
use core_kernel_classes_Resource;
use oat\oatbox\extension\script\ScriptAction;
use oat\taoDacSimple\model\PermissionsService;
use oat\taoDacSimple\model\PermissionsServiceFactory;

class AssignPermissions extends ScriptAction
{
    public const OPTION_CLASS = 'class';
    public const OPTION_PERMISSIONS = 'permissions';
    public const OPTION_RECURSIVE = 'recursive';
    public const OPTION_REVOKE = 'revoke';

    private const ALLOWED_PERMISSIONS = ['GRANT', 'WRITE', 'READ'];

    /** @var Report */
    private $report;

    /** @var core_kernel_classes_Class */
    private $class;

    /** @var array */
    private $permissions;

    /** @var bool */
    private $recursive;

    /** @var bool */
    private $revoke;

    /** @var PermissionsService */
    private $permissionService;

    protected function provideOptions()
    {
        return [
            self::OPTION_CLASS => [
                'prefix' => 'c',
                'longPrefix' => self::OPTION_CLASS,
                'required' => true,
                'cast' => 'string',
                'description' => 'Class (uri) to assign permissions.',
            ],
            self::OPTION_PERMISSIONS => [
                'prefix' => 'p',
                'longPrefix' => self::OPTION_PERMISSIONS,
                'required' => true,
                'cast' => 'array',
                'description' => 'List of permissions.',
            ],
            self::OPTION_RECURSIVE => [
                'longPrefix' => self::OPTION_RECURSIVE,
                'required' => false,
                'cast' => 'boolean',
                'flag' => true,
                'description' => 'Assign permissions recursively.',
                'default' => false,
            ],
            self::OPTION_REVOKE => [
                'longPrefix' => self::OPTION_REVOKE,
                'required' => false,
                'cast' => 'boolean',
                'flag' => true,
                'description' => 'Revoke permissions.',
                'default' => false,
            ],
        ];
    }

    protected function provideDescription()
    {
        return 'Allow to assign or revoke list of permissions to a specific class.';
    }

    protected function run()
    {
        $this->report = Report::createInfo(
            sprintf(
                'Started to %s permissions.',
                $this->revoke ? 'revoke' : 'assign'
            )
        );

        try {
            $this->parseOptions();
            $this->validateOptions();

            $this->revoke
                ? $this->revokePermissions()
                : $this->assignPermissions();
        } catch (Throwable $exception) {
            $this->report->add(Report::createError($exception->getMessage()));
        }

        return $this->report;
    }

    private function parseOptions(): void
    {
        $this->class = new core_kernel_classes_Class(
            $this->getOption(self::OPTION_CLASS)
        );
        $this->permissions = $this->getOption(self::OPTION_PERMISSIONS);
        $this->recursive = $this->getOption(self::OPTION_RECURSIVE);
        $this->revoke = $this->getOption(self::OPTION_REVOKE);
    }

    private function validateOptions(): void
    {
        $this->validateClass();
        $this->validatePermissions();
    }

    private function validateClass(): void
    {
        if ($this->class->exists() === false) {
            throw new Exception(sprintf('Class %s not exist.', $this->class->getUri()));
        }
    }

    private function validatePermissions(): void
    {
        foreach ($this->permissions as $resourceUri => $resourcePermissions) {
            $this->validateResource($resourceUri);
            $this->validateResourcePermissions($resourcePermissions);

            if (empty($resourcePermissions)) {
                Report::createWarning(
                    sprintf('Permissions list for resource %s is empty. Skipped.', $resourceUri)
                );

                unset($this->permissions[$resourceUri]);
            }
        }

        if (empty($this->permissions)) {
            throw new Exception('Permission list is empty.');
        }
    }

    private function validateResource(string $resourceUri): void
    {
        $resource = new core_kernel_classes_Resource($resourceUri);

        if ($resource->exists() === false) {
            throw new Exception(sprintf('Resource %s not exist.', $resourceUri));
        }
    }

    private function validateResourcePermissions(array &$resourcePermissions): void
    {
        foreach ($resourcePermissions as $rolePermissionIndex => $rolePermission) {
            if (!in_array($rolePermission, self::ALLOWED_PERMISSIONS, true)) {
                $this->report->add(
                    Report::createWarning(
                        sprintf('Permission %s is now allowed. Skipped.', $rolePermission)
                    )
                );

                unset($resourcePermissions[$rolePermissionIndex]);
            }
        }
    }

    private function assignPermissions(): void
    {
        $permissions = $this->getPermissionService()->getResourcePermissions($this->class);

        foreach ($this->permissions as $resourceUri => $resourcePermissions) {
            $permissions[$resourceUri] = $resourcePermissions;
        }

        $this->getPermissionService()->savePermissions($this->recursive, $this->class, $permissions);
    }

    private function revokePermissions(): void
    {
        $permissions = $this->getPermissionService()->getResourcePermissions($this->class);

        foreach ($this->permissions as $resourceUri => $resourcePermissions) {
            unset($permissions[$resourceUri]);
        }

        $this->getPermissionService()->savePermissions($this->recursive, $this->class, $permissions);
    }

    private function getPermissionService(): PermissionsService
    {
        if (!isset($this->permissionService)) {
            $this->permissionService = $this->getServiceManager()->get(PermissionsServiceFactory::SERVICE_ID)->create();
        }

        return $this->permissionService;
    }
}
