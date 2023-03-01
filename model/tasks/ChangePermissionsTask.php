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
 * Copyright (c) 2020-2023 (original work) Open Assessment Technologies SA;
 */

namespace oat\taoDacSimple\model\tasks;

use common_exception_MissingParameter;
use common_report_Report as Report;
use core_kernel_classes_Class;
use core_kernel_classes_Resource;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\AbstractAction;
use oat\oatbox\service\ServiceManagerAwareTrait;
use oat\tao\model\taskQueue\QueueDispatcher;
use oat\tao\model\taskQueue\Task\CallbackTaskInterface;
use oat\tao\model\taskQueue\Task\TaskAwareInterface;
use oat\tao\model\taskQueue\Task\TaskAwareTrait;
use oat\taoDacSimple\model\PermissionsService;
use oat\taoDacSimple\model\PermissionsServiceFactory;
use Exception;
use JsonSerializable;

use function count;


/**
 * Class ChangePermissionsTask
 *
 * Handling permission changes in background
 */
class ChangePermissionsTask extends AbstractAction implements TaskAwareInterface, JsonSerializable
{
    use ServiceManagerAwareTrait;
    use TaskAwareTrait;
    use OntologyAwareTrait;

    public const PARAM_RECURSIVE = 'recursive';
    public const PARAM_RESOURCE = 'resource';
    public const PARAM_PRIVILEGES = 'privileges';

    // @todo May be configurable
    private const PERMISSIONS_PER_TASK = 250;

    private const MANDATORY_PARAMS = [
        self::PARAM_RECURSIVE,
        self::PARAM_PRIVILEGES,
        self::PARAM_RESOURCE
    ];

    public function __invoke($params = []): Report
    {
        $this->validateParams($params);

        try {
            $this->doChangePermissions(
                $this->getClass($params[self::PARAM_RESOURCE]),
                (bool) $params[self::PARAM_RECURSIVE],
                $params[self::PARAM_PRIVILEGES]
            );
        } catch (Exception $exception) {
            $errMessage = sprintf('Saving permissions failed: %s', $exception->getMessage());
            $this->getLogger()->error($errMessage);

            return Report::createFailure($errMessage);
        }

        return Report::createSuccess('Permissions saved');
    }

    private function doChangePermissions(
        core_kernel_classes_Class $class,
        bool $isRecursive,
        array $privileges
    ): void {
        $dispatcher = $this->getQueueDispatcher();

        if ($isRecursive && null !== $dispatcher && $this->isHugeClass($class)) {
            $this->splitRequestIntoSubtasks($dispatcher, $class, $privileges);
        } else {
            $this->getPermissionService()->savePermissions(
                $isRecursive,
                $class,
                $privileges
            );
        }
    }

    private function splitRequestIntoSubtasks(
        QueueDispatcher $dispatcher,
        core_kernel_classes_Class $class,
        array $privileges
    ): void {
        $permissionsService = $this->getPermissionService();

        $this->getLogger()->debug(
            sprintf(
                '%s: Splitting request for %s into subtasks',
                self::class,
                $class->getUri()
            )
        );

        $request = $permissionsService->getNormalizedRequest($class, $privileges);
        $tasksIds = [];
        $buffer = [];

        // getNestedResources() returns a Generator, therefore it won't load all
        // resources in memory at once
        //
        foreach($permissionsService->getNestedResources($class) as $resource) {
            $buffer[] = $resource;

            if(count($buffer) >= self::PERMISSIONS_PER_TASK) {
                $tasksIds[] = $this->spawnSubtask(
                    $dispatcher,
                    $class,
                    $buffer,
                    $privileges,
                    $request
                )->getId();

                $buffer = [];
            }
        }

        if (!empty($buffer)) {
            $tasksIds[] = $this->spawnSubtask(
                $dispatcher,
                $class,
                $buffer,
                $privileges,
                $request
            )->getId();
        }

        $this->spawnTriggerEventsSubtask($dispatcher, $class, $tasksIds, $request);
    }

    private function spawnSubtask(
        QueueDispatcher $dispatcher,
        core_kernel_classes_Class $class,
        array $resources,
        array $privileges,
        array $normalizedRequest
    ): CallbackTaskInterface {
        $this->logDebug(
            sprintf(
                "Spawning subtask, class=%s resource count=%d privileges count=%s",
                $class->getUri(),
                count($resources),
                count($privileges)
            )
        );

        $resourceURIs = [];
        foreach ($resources as $resource) {
            assert($resource instanceof core_kernel_classes_Resource);
            $resourceURIs[] = $resource->getUri();
        }

        return $dispatcher->createTask(
            new ChangePermissionsSubtask(),
            [
                ChangePermissionsSubtask::PARAM_ROOT => $class->getUri(),
                ChangePermissionsSubtask::PARAM_RESOURCES => $resourceURIs,
                ChangePermissionsSubtask::PARAM_NORMALIZED_REQUEST => $normalizedRequest,
            ],
            'Processing permissions subtask'
        );
    }

    /**
     * If at least one task has been created, this method creates an additional
     * one to trigger DacXXXXEvents using the root resource as the parameter.
     *
     * @param CallbackTaskInterface[] $tasks
     * @return void
     */
    private function spawnTriggerEventsSubtask(
        QueueDispatcher $dispatcher,
        core_kernel_classes_Class $class,
        array $tasksIds,
        array $normalizedRequest
    ): void {
        if (empty($tasks)) {
            return;
        }

        $dispatcher->createTask(
            new TriggerEventsOnCompletionSubtask(),
            [
                TriggerEventsOnCompletionSubtask::PARAM_ROOT_RESOURCE => $class->getUri(),
                TriggerEventsOnCompletionSubtask::PARAM_NORMALIZED_REQUEST => $normalizedRequest,
                TriggerEventsOnCompletionSubtask::PARAM_SUBTASK_IDS => $tasksIds,
            ],
            'Waiting for subtasks to finish to trigger change events'
        );
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

    private function isHugeClass(core_kernel_classes_Resource $resource): bool
    {
        if ($resource->isClass()) {
            // Ensure we have a core_kernel_classes_Class instance
            $class = $resource->getClass($resource->getUri());
            $resourceCount = $class->countInstances([], ['recursive' => true]);

            return ($resourceCount >= self::PERMISSIONS_PER_TASK);
        }

        return false;
    }

    private function getQueueDispatcher(): ?QueueDispatcher
    {
        return $this->serviceLocator->get(QueueDispatcher::SERVICE_ID);
    }

    private function getPermissionService(): PermissionsService
    {
        return $this->serviceLocator->get(PermissionsServiceFactory::SERVICE_ID)->create();
    }

    public function jsonSerialize(): string
    {
        return __CLASS__;
    }
}
