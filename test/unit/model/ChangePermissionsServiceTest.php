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
use oat\tao\model\event\DataAccessControlChangedEvent;
use oat\taoDacSimple\model\ChangePermissionsService;
use oat\taoDacSimple\model\Command\ChangeAccessCommand;
use oat\taoDacSimple\model\Command\ChangePermissionsCommand;
use oat\taoDacSimple\model\DataBaseAccess;
use oat\taoDacSimple\model\event\DacAffectedUsersEvent;
use oat\taoDacSimple\model\event\DacRootChangedEvent;
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
    private ChangePermissionsService $sut;

    protected function setUp(): void
    {
        $this->eventManager = $this->createMock(EventManager::class);
        $this->databaseAccess = $this->createMock(DataBaseAccess::class);
        $this->strategy = $this->createMock(PermissionsStrategyInterface::class);

        $this->sut = new ChangePermissionsService($this->databaseAccess, $this->strategy, $this->eventManager);
    }

    public function testChange(): void
    {
        $root = $this->createMock(\core_kernel_classes_Resource::class);
        $root
            ->expects($this->never())
            ->method('getNestedResources');
        $root
            ->method('getUri')
            ->willReturn('rootUri');
        $root
            ->method('isClass')
            ->willReturn(true);

        $privilegesPerUser = [
            'user1' => ['READ', 'WRITE', 'GRANT'],
            'user2' => ['READ', 'WRITE'],
        ];

        $changePermissionsCommand = $this->createMock(ChangePermissionsCommand::class);
        $changePermissionsCommand
            ->method('getRoot')
            ->willReturn($root);
        $changePermissionsCommand
            ->method('isRecursive')
            ->willReturn(false);
        $changePermissionsCommand
            ->method('getPrivilegesPerUser')
            ->willReturn($privilegesPerUser);

        $rootPermissions = [
            'user1' => ['READ'],
            'user2' => ['READ', 'WRITE', 'GRANT'],
        ];

        $changeAccessCommand = new ChangeAccessCommand();
        $changeAccessCommand->revokeResourceForUser('rootUri', 'GRANT', 'user2');
        $changeAccessCommand->grantResourceForUser('rootUri', 'WRITE', 'user1');
        $changeAccessCommand->grantResourceForUser('rootUri', 'GRANT', 'user1');

        $this->databaseAccess
            ->expects($this->once())
            ->method('getResourcesPermissions')
            ->with(['rootUri'])
            ->willReturn([
                'rootUri' => $rootPermissions,
            ]);
        $this->databaseAccess
            ->expects($this->once())
            ->method('changeAccess')
            ->with($changeAccessCommand);

        $permissionsDelta = [
            'remove' => [
                'user2' => ['GRANT'],
            ],
            'add' => [
                'user1' => ['WRITE', 'GRANT'],
            ],
        ];

        $this->strategy
            ->expects($this->once())
            ->method('normalizeRequest')
            ->with($rootPermissions, $privilegesPerUser)
            ->willReturn($permissionsDelta);
        $this->strategy
            ->expects($this->once())
            ->method('getPermissionsToRemove')
            ->with($rootPermissions, $permissionsDelta)
            ->willReturn(['user2' => ['GRANT']]);
        $this->strategy
            ->expects($this->once())
            ->method('getPermissionsToAdd')
            ->with($rootPermissions, $permissionsDelta)
            ->willReturn(['user1' => ['WRITE', 'GRANT']]);

        $this->eventManager
            ->method('trigger')
            ->withConsecutive(
                [new DacRootChangedEvent($root, $permissionsDelta)],
                [new DataAccessControlChangedEvent('rootUri', $permissionsDelta, false)],
                [new DacAffectedUsersEvent(['user1'], ['user2'])],
            );

        $this->sut->change($changePermissionsCommand);
    }
}
