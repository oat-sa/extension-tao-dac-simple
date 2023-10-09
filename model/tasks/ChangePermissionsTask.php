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
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\AbstractAction;
use oat\oatbox\reporting\Report;
use oat\tao\model\taskQueue\Task\TaskAwareInterface;
use oat\tao\model\taskQueue\Task\TaskAwareTrait;
use oat\taoDacSimple\model\ChangePermissionsService;
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

            return Report::createSuccess('Permissions saved');
        } catch (Exception $e) {
            $errMessage = sprintf('Saving permissions failed: %s', $e->getMessage());
            $this->getLogger()->error($errMessage);

            return Report::createError($errMessage);
        }
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
