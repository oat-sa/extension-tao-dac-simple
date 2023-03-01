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
 * Copyright (c) 2023 (original work) Open Assessment Technologies SA;
 */

namespace oat\taoDacSimple\model\tasks;

use common_report_Report as Report;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\AbstractAction;
use oat\oatbox\service\ServiceManagerAwareTrait;
use oat\tao\model\taskQueue\QueueDispatcher;
use oat\tao\model\taskQueue\Task\TaskAwareInterface;
use oat\tao\model\taskQueue\Task\TaskAwareTrait;
use oat\tao\model\taskQueue\TaskLog;
use oat\tao\model\taskQueue\TaskLogInterface;
use oat\taoDacSimple\model\PermissionsService;
use oat\taoDacSimple\model\PermissionsServiceFactory;
use JsonSerializable;

class TriggerEventsOnCompletionSubtask extends AbstractAction implements TaskAwareInterface, JsonSerializable
{
    use ServiceManagerAwareTrait;
    use TaskAwareTrait;
    use OntologyAwareTrait;

    public const PARAM_SUBTASK_IDS = 'subtask_ids';
    public const PARAM_NORMALIZED_REQUEST = 'normalized_request';
    public const PARAM_ROOT_RESOURCE = 'root_resource';

    public function __invoke($params = []): Report
    {
        $rootClassId = (string) $params[self::PARAM_ROOT_RESOURCE];
        $subtaskIds = (array) $params[self::PARAM_SUBTASK_IDS];
        $normalizedRequest = (array) $params[self::PARAM_NORMALIZED_REQUEST];

        foreach ($subtaskIds as $subtaskId) {
            if (!$this->subtaskHasFinished($subtaskId)) {
                $this->reenqueueTask($rootClassId, $subtaskIds, $normalizedRequest);

                return Report::createSuccess('Change permissions subtasks in progress');
            }
        }

        $this->logInfo('Triggering events');

        // triggerEvents is only called for the root resource (it was like that before)
        $this->getPermissionService()->triggerEventsForRootResource(
            $this->getClass($rootClassId),
            $normalizedRequest,
            true
        );

        return Report::createSuccess('Events triggered');
    }

    private function reenqueueTask(
        string $rootClassId,
        array $subtaskIds,
        array $normalizedRequest
    ): void {
        $taskParameters = [
            self::PARAM_ROOT_RESOURCE => $rootClassId,
            self::PARAM_SUBTASK_IDS => $subtaskIds,
            self::PARAM_NORMALIZED_REQUEST => $normalizedRequest,
        ];

        sleep(10);

        $this->getQueueDispatcher()->createTask(
            new TriggerEventsOnCompletionSubtask(),
            $taskParameters,
            'Waiting for subtasks to finish to trigger change events'
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

    private function getPermissionService(): PermissionsService
    {
        return $this->serviceLocator->get(PermissionsServiceFactory::SERVICE_ID)->create();
    }

    private function getQueueDispatcher(): ?QueueDispatcher
    {
        return $this->serviceLocator->get(QueueDispatcher::SERVICE_ID);
    }

    private function getTaskLog(): TaskLog
    {
        return $this->serviceLocator->get(TaskLog::SERVICE_ID);
    }

    public function jsonSerialize(): string
    {
        return __CLASS__;
    }
}
