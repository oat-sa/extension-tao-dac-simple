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
 * Copyright (c) 2020-2023 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoDacSimple\model\tasks;

use common_exception_MissingParameter;
use common_report_Report as Report;
use core_kernel_classes_Class;
use core_kernel_classes_Resource;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\AbstractAction;
use oat\tao\model\taskQueue\QueueDispatcher;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\tao\model\taskQueue\Task\TaskAwareInterface;
use oat\tao\model\taskQueue\Task\TaskAwareTrait;
use oat\taoDacSimple\model\ChangePermissionsService;
use oat\taoDacSimple\model\Command\ChangePermissionsCommand;
use oat\taoDacSimple\model\PermissionsService;
use oat\taoDacSimple\model\PermissionsServiceFactory;
use Exception;
use JsonSerializable;

/**
 * Class ChangePermissionsTask
 *
 * Handling permission changes in background
 */
class ChangePermissionsTask extends AbstractAction implements TaskAwareInterface, JsonSerializable
{
    use TaskAwareTrait;
    use OntologyAwareTrait;

    public const PARAM_RESOURCE = 'resource';
    public const PARAM_PRIVILEGES = 'privileges';
    public const PARAM_RECURSIVE = 'recursive';
    public const PARAM_NESTED_RESOURCES = 'nested_resources';
    public const PARAM_REQUEST_ROOT = 'request_root';
    public const PARAM_PERMISSIONS_DELTA = 'permissions_delta';

    private const MANDATORY_PARAMS = [
        self::PARAM_RESOURCE,
        self::PARAM_PRIVILEGES
    ];

    public function __invoke($params = []): Report
    {
        $this->validateParams($params);

        try {
            /** @var ChangePermissionsService $changePermissionsService */
            $changePermissionsService = $this->getServiceManager()->getContainer()->get(ChangePermissionsService::class);
            $changePermissionsService(
                $this->getResource($params[self::PARAM_RESOURCE]),
                (array) $params[self::PARAM_PRIVILEGES],
                filter_var($params[self::PARAM_RECURSIVE] ?? false, FILTER_VALIDATE_BOOL)
            );
//            $this->doHandle(
//                $this->getClass($params[self::PARAM_RESOURCE]),
//                ($params[self::PARAM_REQUEST_ROOT] ?? $params[self::PARAM_RESOURCE]),
//                (array) $params[self::PARAM_PRIVILEGES],
//                (bool) ($params[self::PARAM_RECURSIVE] ?? false),
//                (bool) ($params[self::PARAM_NESTED_RESOURCES] ?? false),
//                $params[self::PARAM_PERMISSIONS_DELTA] ?? null
//            );

            return Report::createSuccess('Permissions saved');
        } catch (Exception $e) {
            $errMessage = sprintf('Saving permissions failed: %s', $e->getMessage());

            $this->getLogger()->error($errMessage);
            return Report::createFailure($errMessage);
        }
    }

    private function doHandle(
        core_kernel_classes_Class $root,
        string $requestRoot,
        array $privileges,
        bool $isRecursive,
        bool $withNestedResources,
        ?array $permissionsDelta
    ): Report {
        $permissionService = $this->getPermissionService();
        $permissionsDelta ??= $permissionService->getPermissionsDelta($root, $privileges);

        if ($withNestedResources) {
            $message = sprintf(
                "Permissions saved for resources under subclass %s [%s]",
                $this->formatLabel($root),
                $root->getUri()
            );

            $command = new ChangePermissionsCommand($root, $requestRoot, $privileges, $permissionsDelta);
            $command->withNestedResources();

            $this->getPermissionService()->applyPermissions($command);
        } elseif ($isRecursive) {
            $message = 'Starting recursive permissions update';

            $this->createSubtasksForClasses(
                array_merge([$root], $root->getSubClasses(true)),
                $requestRoot,
                $privileges,
                $permissionsDelta
            );
        } else {
            $message = 'Permissions saved';

            $this->getPermissionService()->applyPermissions(
                new ChangePermissionsCommand($root, $requestRoot, $privileges, $permissionsDelta)
            );
        }

        return Report::createSuccess($message);
    }

    private function createSubtasksForClasses(
        array $allClasses,
        string $requestRoot,
        array $privileges,
        array $permissionsDelta
    ): void {
        foreach ($allClasses as $oneClass) {
            $this->getDispatcher()->createTask(
                new self(),
                [
                    self::PARAM_RESOURCE => $oneClass->getUri(),
                    self::PARAM_REQUEST_ROOT => $requestRoot,
                    self::PARAM_PRIVILEGES => $privileges,
                    self::PARAM_RECURSIVE => false,
                    self::PARAM_NESTED_RESOURCES => true,
                    self::PARAM_PERMISSIONS_DELTA => $permissionsDelta,
                ],
                sprintf(
                    'Processing permissions for class %s [%s]',
                    $this->formatLabel($oneClass),
                    $oneClass->getUri()
                )
            );
        }
    }

    protected function formatLabel(core_kernel_classes_Resource $resource): string
    {
        return strlen($resource->getLabel()) > 128
            ? '...' . substr($resource->getLabel(), -128)
            : $resource->getLabel();
    }

    private function getDispatcher(): QueueDispatcher
    {
        return $this->serviceLocator->get(QueueDispatcherInterface::SERVICE_ID);
    }

    private function getPermissionService(): PermissionsService
    {
        return $this->serviceLocator->get(PermissionsServiceFactory::SERVICE_ID)->create();
    }

    private function validateParams(array $params): void
    {
        foreach (self::MANDATORY_PARAMS as $param) {
            if (!isset($params[$param])) {
                throw new common_exception_MissingParameter(
                    sprintf(
                        'Missing parameter `%s` in %s',
                        $param,
                        self::class
                    )
                );
            }
        }
    }

    public function jsonSerialize(): string
    {
        return __CLASS__;
    }
}
