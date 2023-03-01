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

use common_exception_MissingParameter;
use common_report_Report as Report;
use core_kernel_classes_Resource;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\AbstractAction;
use oat\oatbox\service\ServiceManagerAwareTrait;
use oat\tao\model\taskQueue\QueueDispatcher;
use oat\tao\model\taskQueue\Task\TaskAwareInterface;
use oat\tao\model\taskQueue\Task\TaskAwareTrait;
use oat\taoDacSimple\model\PermissionsService;
use oat\taoDacSimple\model\PermissionsServiceFactory;
use Exception;
use JsonSerializable;

/**
 * Handles permission changes in the background.
 *
 * The task receives an array of resource IDs to change instead of a
 * root class and a "isRecursive' flag.
 */
class ChangePermissionsSubtask
    extends AbstractAction
    implements TaskAwareInterface, JsonSerializable
{
    use ServiceManagerAwareTrait;
    use TaskAwareTrait;
    use OntologyAwareTrait;

    public const PARAM_ROOT = 'root';
    public const PARAM_RESOURCES = 'resource';
    public const PARAM_PRIVILEGES = 'privileges';
    public const PARAM_NORMALIZED_REQUEST = 'normalized_request';

    private const MANDATORY_PARAMS = [
        self::PARAM_ROOT,
        self::PARAM_RESOURCES,
        self::PARAM_PRIVILEGES,
        self::PARAM_NORMALIZED_REQUEST,
    ];

    public function __invoke($params = []): Report
    {
        $this->validateParams($params);

        try {
            $this->getPermissionService()->savePermissionsForMultipleResources(
                $this->getClass($params[self::PARAM_ROOT]),
                $this->getResourcesFromParams($params),
                $params[self::PARAM_PRIVILEGES]
            );
        } catch (Exception $exception) {
            $errMessage = sprintf('Saving permissions failed: %s', $exception->getMessage());
            $this->getLogger()->error($errMessage);

            return Report::createFailure($errMessage);
        }

        $this->logDebug(
            sprintf(
                '%s finished, mem peak usage: %u kB',
                self::class,
                memory_get_peak_usage(true) / 1024
            )
        );

        return Report::createSuccess('Permissions saved');
    }

    /**
     * @return core_kernel_classes_Resource[]
     */
    private function getResourcesFromParams(array $params): array
    {
        return array_map([$this, 'getResource'], $params[self::PARAM_RESOURCES]);
    }

    private function validateParams(array $params): void
    {
        foreach (self::MANDATORY_PARAMS as $param) {
            if (!isset($params[$param])) {
                throw new common_exception_MissingParameter(
                    sprintf('Missing parameter `%s` in %s', $param, self::class)
                );
            }
        }
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
