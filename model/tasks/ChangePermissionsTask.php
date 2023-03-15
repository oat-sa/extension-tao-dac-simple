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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA;
 *
 */

namespace oat\taoDacSimple\model\tasks;

use common_exception_MissingParameter;
use common_report_Report as Report;
use Exception;
use JsonSerializable;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\AbstractAction;
use oat\oatbox\service\ServiceManagerAwareTrait;
use oat\tao\model\taskQueue\QueueDispatcher;
use oat\tao\model\taskQueue\Task\TaskAwareInterface;
use oat\tao\model\taskQueue\Task\TaskAwareTrait;
use oat\taoDacSimple\model\PermissionsService;
use oat\taoDacSimple\model\PermissionsServiceFactory;

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
    public const PARAM_NESTED_RESOURCES = 'nested_resources'; //FIXME Find better name

    public function __invoke($params = []): Report
    {
        $this->validateParams($params);

        try {
            $isRecursive = (bool)$params[self::PARAM_RECURSIVE];

            if ($isRecursive) {
                $queueDispatcher = $this->getServiceManager()->get(QueueDispatcher::SERVICE_ID);

                $class = $this->getClass($params[self::PARAM_RESOURCE]);
                $subClasses = $class->getSubClasses(true);
                $allSubclasses = array_merge([$class], $subClasses);

                foreach ($allSubclasses as $subCLass) {
                    $queueDispatcher->createTask(
                        new self(),
                        [
                            self::PARAM_RESOURCE => $subCLass->getUri(),
                            self::PARAM_PRIVILEGES => $params[self::PARAM_PRIVILEGES],
                            self::PARAM_RECURSIVE => false,
                            self::PARAM_NESTED_RESOURCES => true,
                        ],
                        sprintf(
                            'Processing permissions for class %s [%s]',
                            $subCLass->getLabel(),
                            $subCLass->getUri()
                        )
                    );
                }
            } else {
                $service = $this->getPermissionService();
                $service->savePermissions(
                    false,
                    $this->getClass($params[self::PARAM_RESOURCE]),
                    $params[self::PARAM_PRIVILEGES],
                    $params[self::PARAM_NESTED_RESOURCES]
                );
            }

            $result = Report::createSuccess('Permissions saved');
        } catch (Exception $exception) {
            $errMessage = sprintf('Saving permissions failed: %s', $exception->getMessage());
            $this->getLogger()->error($errMessage);
            $result = Report::createFailure($errMessage);
        }

        return $result;
    }

    private function getPermissionService(): PermissionsService
    {
        return $this->serviceLocator->get(PermissionsServiceFactory::SERVICE_ID)->create();
    }

    private function validateParams(array $params): void
    {
        $knownParams = [self::PARAM_RECURSIVE, self::PARAM_PRIVILEGES, self::PARAM_RESOURCE];
        foreach ($knownParams as $param) {
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
