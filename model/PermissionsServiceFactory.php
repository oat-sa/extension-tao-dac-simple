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

namespace oat\taoDacSimple\model;

use oat\oatbox\event\EventManager;
use oat\oatbox\service\ConfigurableService;
use RuntimeException;

class PermissionsServiceFactory extends ConfigurableService
{
    public const SERVICE_ID = 'taoDacSimple/PermissionsService';

    public const OPTION_SAVE_STRATEGY = 'save_strategy';

    public const OPTION_RECURSIVE_BY_DEFAULT = 'recursive_by_default';

    /**
     * @return PermissionsService
     */
    public function create(): PermissionsService
    {
        if (!$this->hasOption(self::OPTION_SAVE_STRATEGY)) {
            throw new RuntimeException(
                sprintf('Option %s is not configured. Please check %s', self::OPTION_SAVE_STRATEGY, self::SERVICE_ID)
            );
        }

        /** @var DataBaseAccess $dataBaseAccess */
        $dataBaseAccess = $this->serviceLocator->get(DataBaseAccess::SERVICE_ID);

        /** @var EventManager $eventManager */
        $eventManager = $this->serviceLocator->get(EventManager::SERVICE_ID);

        $strategyClass = $this->getOption(self::OPTION_SAVE_STRATEGY);
        $permissionsStrategy = new $strategyClass();

        return new PermissionsService($dataBaseAccess, $permissionsStrategy, $eventManager);
    }
}
