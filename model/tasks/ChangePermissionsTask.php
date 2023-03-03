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
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\AbstractAction;
use oat\oatbox\service\ServiceManagerAwareTrait;
use oat\tao\model\taskQueue\QueueDispatcher;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\tao\model\taskQueue\Task\CallbackTask;
use oat\tao\model\taskQueue\Task\TaskAwareInterface;
use oat\tao\model\taskQueue\Task\TaskAwareTrait;
use oat\tao\model\taskQueue\Task\TaskInterface;
use oat\tao\model\taskQueue\TaskLog;
use oat\tao\model\taskQueue\TaskLogInterface;
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
    use ServiceManagerAwareTrait; // Already implemented by AbstractAction
    use TaskAwareTrait;
    use OntologyAwareTrait;

    public const PARAM_RESOURCE = 'resource';
    public const PARAM_RECURSIVE = 'recursive';
    public const PARAM_PRIVILEGES = 'privileges';
    public const PARAM_IS_SUBCLASS = 'is_subclass';
    public const PARAM_IS_SENTINEL = 'sentinel';
    public const PARAM_SUBTASK_IDS = 'subtask_ids';

    private const MANDATORY_PARAMS = [
        self::PARAM_RESOURCE,
        self::PARAM_PRIVILEGES
    ];

    public function __invoke($params = []): Report
    {
        $this->validateParams($params);

        $isSentinel = (bool) ($params[self::PARAM_IS_SENTINEL] ?? false);
        $isRecursive = (bool) ($params[self::PARAM_RECURSIVE] ?? false);
        $privileges = (array) $params[self::PARAM_PRIVILEGES];

        try {
            $rootClass = $this->getClass($params[self::PARAM_RESOURCE]);

            if ($isSentinel) {
                return $this->handleSentinelRequest(
                    $rootClass,
                    $privileges,
                    $params[self::PARAM_SUBTASK_IDS]
                );
            }

            if ($isRecursive) {
                $this->handleRecursiveRequest($rootClass, $privileges);
            } else {
                $this->getPermissionService()->applyPermissions(
                    new ChangePermissionsCommand($rootClass, $privileges, false, false)
                );
            }

            $result = Report::createSuccess('Permissions saved');
        } catch (Exception $exception) {
            $errMessage = sprintf(
                'Saving permissions failed: %s',
                $exception->getMessage()
            );

            $this->getLogger()->error($errMessage);
            $result = Report::createFailure($errMessage);
        }

        return $result;
    }

    /**
     * Checks if all subtasks have finished for a recursive request: If they
     * did, updates permissions for the root class itself, otherwise it
     * enqueues a new task to recheck it later.
     */
    private function handleSentinelRequest(
        core_kernel_classes_Class $rootClass,
        array $privileges,
        array $subtaskIds
    ): Report {
        foreach ($subtaskIds as $subtaskId) {
            if (!$this->subtaskHasFinished($subtaskId)) {
                $this->spawnSentinelTask($rootClass, $privileges, $subtaskIds);

                return Report::createSuccess('Change permissions subtasks in progress');
            }
        }

        $this->getPermissionService()->applyPermissions(
            new ChangePermissionsCommand($rootClass, $privileges, false, true)
        );

        return Report::createSuccess("Permissions saved for root {$rootClass->getUri()}");
    }

    private function handleRecursiveRequest(
        core_kernel_classes_Class $class,
        array $privileges
    ): void {
        $subClasses = $class->getSubClasses(true); // NOT including the root
        $allSubclasses = array_merge($subClasses);

        /** @var CallbackTask[] $taskIds */
        $taskIds = [];

        foreach ($allSubclasses as $subClass) {
            $task = $this->getDispatcher()->createTask(
                new self(),
                [
                    self::PARAM_RESOURCE => $subClass->getUri(),
                    self::PARAM_PRIVILEGES => $privileges,
                    self::PARAM_RECURSIVE => false,
                    self::PARAM_IS_SUBCLASS => true,
                ],
                sprintf(
                    'Processing permissions for class %s [%s]',
                    $subClass->getLabel(),
                    $subClass->getUri()
                )
            );

            $taskIds[] = $task->getId();
        }

        $this->spawnSentinelTask($class, $privileges, $taskIds);
    }

    /**
     * PermissionsService::saveResourcePermissions uses the root class to
     * compute the ACL diff, so we cannot change its permissions before all
     * child classes/resources are changed.
     *
     * Also, we cannot enqueue a task to change the root until we are sure
     * children have been processed (because then child classes would stop
     * applying the correct permissions).
     *
     * @param core_kernel_classes_Class $class Class to update on tasks completion.
     * @param array $privileges New class privileges.
     * @param string[] $taskIds IDs of tasks to finish execution for.
     */
    private function spawnSentinelTask(
        core_kernel_classes_Class $class,
        array $privileges,
        array $taskIds
    ): void {
        $this->getDispatcher()->createTask(
            new self(),
            [
                self::PARAM_RESOURCE => $class->getUri(),
                self::PARAM_PRIVILEGES => $privileges,
                self::PARAM_IS_SENTINEL => true,
                self::PARAM_SUBTASK_IDS => $taskIds, // array_map(
                    //function (CallbackTask $task) {

                /*
                 * Argument 1 passed to oat\taoDacSimple\model\tasks\ChangePermissionsTask::
                 *  oat\taoDacSimple\model\tasks\{closure}() must implement interface
                 *  oat\tao\model\taskQueue\Task\TaskInterface, string given
                 */
                    /*function (TaskInterface $task) {
                        return $task->getId();
                    },
                    $taskIds
                ),*/
            ],
            sprintf(
                'Process permissions for root class %s [%s]',
                $class->getLabel(),
                $class->getUri()
            )
        );
    }

    private function subtaskHasFinished(string $subtaskId): bool
    {
        $this->logDebug(
            sprintf(
                "Subtask %s status: %s",
                $subtaskId,
                $this->getTaskLog()->getStatus($subtaskId)
            )
        );

        return in_array(
            $this->getTaskLog()->getStatus($subtaskId),
            [
                TaskLogInterface::STATUS_COMPLETED,
                TaskLogInterface::STATUS_CANCELLED,
                TaskLogInterface::STATUS_ARCHIVED,
                TaskLogInterface::STATUS_FAILED,
                TaskLogInterface::STATUS_CHILD_RUNNING
            ]
        );
    }

    private function getTaskLog(): TaskLog
    {
        return $this->serviceLocator->get(TaskLog::SERVICE_ID);
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
                throw new common_exception_MissingParameter(sprintf(
                    'Missing parameter `%s` in %s',
                    $param,
                    self::class
                ));
            }
        }
    }

    public function jsonSerialize(): string
    {
        return __CLASS__;
    }
}
