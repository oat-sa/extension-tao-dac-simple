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
use core_kernel_classes_Class;
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
 * @todo Find a better name for this?
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

    private const MANDATORY_PARAMS = [
        self::PARAM_PRIVILEGES,
        self::PARAM_RESOURCES
    ];

    public function __invoke($params = []): Report
    {
        $this->validateParams($params);

        $isRecursive = true; // this task is always called for recursive changes
        $service = $this->getPermissionService();
        $dispatcher = $this->getQueueDispatcher();

        /*foreach ($params[self::PARAM_RESOURCES] as $resource) {

        }*/
        // @todo Need an operation in PermissionService that receives the array of
        //       resources to operate on directly (instead of the $isRecursive flag)
        /*$service->savePermissions(
            $isRecursive,
            $this->getClass($params[self::PARAM_RESOURCE]),
        );*/
        $service->setPermissionsForMultipleResources(
            $this->getClass($params[self::PARAM_ROOT]),
            array_map([$this, 'getResource'], $params[self::PARAM_RESOURCES]),
            $params[self::PARAM_PRIVILEGES]
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
