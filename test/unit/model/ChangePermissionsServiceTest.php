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
 * Copyright (c) 2023 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoDacSimple\test\unit\model;

use oat\oatbox\event\EventManager;
use oat\taoDacSimple\model\ChangePermissionsService;
use oat\taoDacSimple\model\DataBaseAccess;
use oat\taoDacSimple\model\PermissionsStrategyInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ChangePermissionsServiceTest extends TestCase
{
    /** @var MockObject|DataBaseAccess */
    private $databaseAccess;

    /** @var MockObject|PermissionsStrategyInterface */
    private $strategy;
    /** @var MockObject|EventManager */
    private $eventManager;
    private ChangePermissionsService $service;

    protected function setUp(): void
    {
        $this->eventManager = $this->createMock(EventManager::class);
        $this->databaseAccess = $this->createMock(DataBaseAccess::class);
        $this->strategy = $this->createMock(PermissionsStrategyInterface::class);
        $this->service = new ChangePermissionsService(
            $this->databaseAccess,
            $this->strategy,
            $this->eventManager
        );

        $this->service->setLogger($this->createMock(LoggerInterface::class));
    }

    public function testChange(): void
    {
        //@TODO Complete test before release this code

        $this->markTestIncomplete();
    }
}
