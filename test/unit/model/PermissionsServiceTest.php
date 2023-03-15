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

namespace oat\taoDacSimple\test\unit\model;

use core_kernel_classes_Class;
use oat\generis\test\MockObject;
use oat\generis\test\TestCase;
use oat\oatbox\event\EventManager;
use oat\tao\model\event\DataAccessControlChangedEvent;
use oat\taoDacSimple\model\DataBaseAccess;
use oat\taoDacSimple\model\event\DacAffectedUsersEvent;
use oat\taoDacSimple\model\event\DacRootAddedEvent;
use oat\taoDacSimple\model\PermissionsService;
use oat\taoDacSimple\model\PermissionsServiceException;
use oat\taoDacSimple\model\PermissionsStrategyInterface;
use Psr\Log\LoggerInterface;

class PermissionsServiceTest extends TestCase
{
    /**
     * @var MockObject|DataBaseAccess
     */
    private $databaseAccess;
    /**
     * @var PermissionsService
     */
    private $service;
    /**
     * @var MockObject|PermissionsStrategyInterface
     */
    private $strategy;
    /**
     * @var MockObject|EventManager
     */
    private $eventManager;

    protected function setUp(): void
    {
        $this->eventManager = $this->createMock(EventManager::class);

        $this->databaseAccess = $this->createMock(DataBaseAccess::class);
        $this->databaseAccess->method('getResourcePermissions')->willReturn([]);

        $this->strategy = $this->createMock(PermissionsStrategyInterface::class);

        $this->service = new PermissionsService(
            $this->databaseAccess,
            $this->strategy,
            $this->eventManager
        );

        $this->service->setLogger($this->createMock(LoggerInterface::class));
    }

    public function testSaveAddPermissions(): void
    {
        $this->databaseAccess->expects($this->atLeast(1))->method('addMultiplePermissions');
        $this->databaseAccess->expects($this->never())->method('removeMultiplePermissions');

        $this->databaseAccess->method('getResourcesPermissions')->willReturn(
            [
                'res1' => []
            ]
        );

        $this->strategy->method('normalizeRequest')->willReturn(
            [
                'add' => [
                    'uid1' => ['GRANT', 'READ', 'WRITE']
                ]
            ]
        );

        $this->strategy->method('getPermissionsToAdd')->willReturn(
            [
                'uid1' => ['GRANT', 'READ', 'WRITE']
            ]
        );

        $resource = $this->createMock(core_kernel_classes_Class::class);
        $resource->method('getUri')->willReturn('res1');

        $this->mockTriggeredEvents('res1', 'res1', 'uid1', false);

        $this->service->savePermissions(
            false,
            $resource,
            [
                'uid1' => ['GRANT', 'READ', 'WRITE']
            ]
        );
    }

    public function testSavePermissionsAddRecursively(): void
    {
        $this->databaseAccess->method('getResourcesPermissions')->willReturn(
            [
                'uid2uri' => []
            ]
        );

        $this->databaseAccess->expects($this->once())->method('addMultiplePermissions');
        $this->databaseAccess->expects($this->never())->method('removeMultiplePermissions');

        $this->strategy->method('normalizeRequest')->willReturn(
            [
                'add' => [
                    'uid1' => ['GRANT', 'READ', 'WRITE']
                ]
            ]
        );

        $this->strategy->method('getPermissionsToAdd')->willReturn(
            [
                'uid1' => ['GRANT', 'READ', 'WRITE']
            ]
        );

        /** @var MockObject|core_kernel_classes_Class $childResource */
        $childResource = $this->createMock(core_kernel_classes_Class::class);
        $childResource->method('getUri')->willReturn('uid2uri');

        /** @var MockObject|core_kernel_classes_Class $resource */
        $resource = $this->createMock(core_kernel_classes_Class::class);
        $resource->method('getSubClasses')->willReturn(
            [
                $childResource
            ]
        );

        $resource->method('getInstances')->willReturn([]);
        $resource->method('getUri')->willReturn('uid2uri');

        $this->mockTriggeredEvents('uid2uri', 'uid2uri', 'uid1', true);

        $this->service->savePermissions(
            true,
            $resource,
            [
                'uid1' => ['GRANT', 'READ', 'WRITE']
            ]
        );
    }

    public function testCantRemoveResourceWithNoGrantLeft(): void
    {
        $this->expectException(PermissionsServiceException::class);

        $this->databaseAccess->method('getResourcesPermissions')->willReturn(
            [
                'uid2uri' => [
                    'uid1' => ['GRANT', 'READ', 'WRITE']
                ]
            ]
        );

        $this->strategy->method('normalizeRequest')->willReturn(
            [
                'remove' => [
                    'uid1' => ['GRANT', 'READ', 'WRITE']
                ]
            ]
        );

        $this->strategy->method('getPermissionsToRemove')->willReturn(
            [
                'uid1' => ['GRANT', 'READ', 'WRITE']
            ]
        );

        $resource = $this->createMock(core_kernel_classes_Class::class);
        $resource->method('getUri')->willReturn('uid2uri');

        $this->service->savePermissions(
            false,
            $resource,
            [
                'uid1' => ['GRANT', 'READ', 'WRITE']
            ]
        );
    }

    public function testSaveRemovePermissions(): void
    {
        $this->databaseAccess->method('getResourcesPermissions')->willReturn(
            [
                'uid2uri' => [
                    'uid1' => ['GRANT', 'READ', 'WRITE']
                ]
            ]
        );

        $this->databaseAccess->expects($this->never())->method('addMultiplePermissions');
        $this->databaseAccess->expects($this->once())->method('removeMultiplePermissions');

        $this->strategy->method('normalizeRequest')->willReturn(
            [
                'remove' => [
                    'uid1' => ['GRANT', 'READ', 'WRITE']
                ]
            ]
        );

        $this->strategy->method('getPermissionsToRemove')->willReturn(
            [
                'uid1' => ['READ', 'WRITE']
            ]
        );

        $resource = $this->createMock(core_kernel_classes_Class::class);
        $resource->method('getUri')->willReturn('uid2uri');

        $this->service->savePermissions(
            false,
            $resource,
            [
                'uid1' => ['READ', 'WRITE']
            ]
        );
    }

    public function testDoNotSaveAnythingWhenThereIsNothingToSave(): void
    {
        $this->databaseAccess->expects($this->never())->method('addPermissions');
        $this->databaseAccess->expects($this->never())->method('removePermissions');

        $this->databaseAccess->method('getResourcesPermissions')->willReturn([]);

        $this->strategy->method('normalizeRequest')->willReturn([]);

        $resource = $this->createMock(core_kernel_classes_Class::class);

        $this->service->savePermissions(false, $resource, []);
    }

    public function testDuplicatedAddPermissionsAreNotPersistedTwice(): void
    {
        $resource = $this->createMock(core_kernel_classes_Class::class);
        $resource->method('getUri')->willReturn('res1');

        $this->strategy
             ->method('normalizeRequest')
             ->willReturn([
                'add' => [
                    'uid1' => ['GRANT', 'READ', 'WRITE', 'GRANT']
                ]
            ]);

        $this->strategy->method('getPermissionsToAdd')->willReturn([
            'uid1' => ['GRANT', 'READ', 'WRITE', 'GRANT']
        ]);

        $this->databaseAccess
             ->expects($this->once())
             ->method('addMultiplePermissions')
             ->with([
                 [
                     'resource' => $resource,
                     'permissions' => [
                         'uid1' => ['GRANT', 'READ', 'WRITE']
                     ]
                 ]
             ]);

        $this->databaseAccess
             ->expects($this->never())
             ->method('removeMultiplePermissions');

        $this->databaseAccess
             ->method('getResourcesPermissions')
             ->willReturn(['res1' => []]);

        $this->service->savePermissions(
            false,
            $resource,
            [
                'uid1' => ['GRANT', 'READ', 'WRITE']
            ]
        );
    }

    private function mockTriggeredEvents(
        string $resourceId,
        ?string $rootResourceId,
        string $userId,
        bool $isRecursive
    ): void {
        $this->eventManager->expects($this->at(0))
            ->method('trigger')
            ->with(
                new DacRootAddedEvent(
                    $userId,
                    $resourceId,
                    ['GRANT', 'READ', 'WRITE']
                )
            );

        $this->eventManager->expects($this->at(1))
            ->method('trigger')
            ->with(
                new DacAffectedUsersEvent(
                    [$userId],
                    []
                )
            );

        $this->eventManager->expects($this->at(2))
            ->method('trigger')
            ->with(
                new DataAccessControlChangedEvent(
                    $resourceId,
                    [
                        'add' => ['uid1' => ['GRANT', 'READ', 'WRITE']]
                    ],
                    $isRecursive,
                    $isRecursive,
                    $rootResourceId
                )
            );
    }
}
