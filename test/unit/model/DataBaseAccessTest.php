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
 * Copyright (c) 2014-2021 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 */

declare(strict_types=1);

namespace oat\taoDacSimple\test\unit\model;

use common_persistence_SqlPersistence;
use core_kernel_classes_Resource;
use oat\generis\test\TestCase;
use oat\oatbox\event\EventManager;
use oat\taoDacSimple\model\DataBaseAccess;
use oat\taoDacSimple\model\event\DacAddedEvent;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use ReflectionProperty;

/**
 * Test database access
 *
 * @author Antoine Robin, <antoine.robin@vesperiagroup.com>
 * @package taodacSimple
 */
class DataBaseAccessTest extends TestCase
{
    private const INSERT_CHUNK_SIZE = 1;

    /** @var DataBaseAccess */
    private $sut;

    /** @var common_persistence_SqlPersistence|MockObject */
    private $persistenceMock;

    /** @var EventManager|MockObject */
    private $eventManager;

    public function setUp(): void
    {
        $this->persistenceMock = $this->createMock(common_persistence_SqlPersistence::class);
        $this->eventManager = $this->createMock(EventManager::class);

        $this->sut = new DataBaseAccess();
        $this->sut->setLogger(new NullLogger());
        $this->sut->setInsertChunkSize(self::INSERT_CHUNK_SIZE);
        $this->sut->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    EventManager::SERVICE_ID => $this->eventManager
                ]
            )
        );

        $reflector = new ReflectionProperty(DataBaseAccess::class, 'persistence');
        $reflector->setAccessible(true);
        $reflector->setValue($this->sut, $this->persistenceMock);
    }

    /**
     * @return array
     */
    public function resourceIdsProvider()
    {
        return [
            [[1]],
            [[1, 2, 3, 4]],
            [[1, 2]],
        ];
    }

    /**
     * @dataProvider resourceIdsProvider
     * @preserveGlobalState disable
     */
    public function testGetUsersWithPermissions($resourceIds)
    {
        $queryFixture = sprintf(
            'SELECT %s, %s, %s FROM %s WHERE resource_id IN (%s)',
            DataBaseAccess::COLUMN_RESOURCE_ID,
            DataBaseAccess::COLUMN_USER_ID,
            DataBaseAccess::COLUMN_PRIVILEGE,
            DataBaseAccess::TABLE_PRIVILEGES_NAME,
            implode(',', array_fill(0, count($resourceIds), '?'))
        );

        $resultFixture = [
            ['fixture']
        ];

        $statementMock = $this->createMock(PDOStatement::class);
        $statementMock->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($resultFixture);

        $this->persistenceMock
            ->method('query')
            ->with($queryFixture, $resourceIds)
            ->willReturn($statementMock);

        $this->assertSame($resultFixture, $this->sut->getUsersWithPermissions($resourceIds));
    }

    public function getPermissionProvider(): array
    {
        return [
            [[1, 2, 3], [1, 2, 3]],
            [[1], [2]],
        ];
    }

    /**
     * @dataProvider getPermissionProvider
     */
    public function testGetPermissions(array $userIds, array $resourceIds)
    {
        $query = sprintf(
            'SELECT %s, %s FROM %s WHERE resource_id IN (%s) AND user_id IN (%s)',
            DataBaseAccess::COLUMN_RESOURCE_ID,
            DataBaseAccess::COLUMN_PRIVILEGE,
            DataBaseAccess::TABLE_PRIVILEGES_NAME,
            implode(',', array_fill(0, count($resourceIds), '?')),
            implode(',', array_fill(0, count($userIds), '?'))
        );

        $fetchResultFixture = [
            ['resource_id' => 1, 'privilege' => 'open'],
            ['resource_id' => 2, 'privilege' => 'close'],
            ['resource_id' => 3, 'privilege' => 'create'],
            ['resource_id' => 3, 'privilege' => 'delete'],
        ];

        $resultFixture = [
            1 => ['open'],
            2 => ['close'],
            3 => ['create', 'delete']
        ];

        $statementMock = $this->createMock(PDOStatement::class);
        $statementMock->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($fetchResultFixture);

        $this->persistenceMock
            ->method('query')
            ->with($query, array_merge($resourceIds, $userIds))
            ->willReturn($statementMock);

        $this->assertEquals($resultFixture, $this->sut->getPermissions($userIds, $resourceIds));
        $this->assertEquals([], $this->sut->getPermissions($userIds, []));
    }

    /**
     * @dataProvider addMultiplePermissionsDataProvider
     */
    public function testAddMultiplePermissions(int $numEvents, int $numInserts, array $permissionData): void
    {
        $this->persistenceMock
            ->expects($this->exactly($numInserts))
            ->method('insertMultiple')
            ->with(DataBaseAccess::TABLE_PRIVILEGES_NAME, self::anything())
            ->willReturnCallback(function ($tableName, array $data, array $types = []) {
                return count($data);
            });

        $this->persistenceMock
            ->expects($this->exactly($numInserts > 0 ? 1 : 0))
            ->method('transactional')
            ->willReturnCallback(function (callable $closure) {
                $closure();
            });

        $this->eventManager
            ->expects($this->exactly($numEvents))
            ->method('trigger')
            ->with($this->isInstanceOf(DacAddedEvent::class));

        $this->sut->addMultiplePermissions($permissionData);
    }

    public function addMultiplePermissionsDataProvider(): array
    {
        return [
            'Empty arrays don\'t result in events nor queries' => [
                'numEvents' => 0,
                'numInserts' => 0,
                'permissionData' => []
            ],
            'Empty permissions don\'t result in events nor queries' => [
                'numEvents' => 0,
                'numInserts' => 0,
                'permissionData' => [
                    [
                        'resource' => $this->getResourceMock('123'),
                        'permissions' => []
                    ]
                ]
            ],
            '3 permissions: One resource, single user' => [
                'numEvents' => 3,
                'numInserts' => 3,
                'permissionData' => [
                    [
                        'resource' => $this->getResourceMock('123'),
                        'permissions' => [
                            123 => ['GRANT', 'READ', 'WRITE']
                        ]
                    ]
                ]
            ],
            '6 permissions: One resource, two users' => [
                'numEvents' => 6,
                'numInserts' => 6,
                'permissionData' => [
                    [
                        'resource' => $this->getResourceMock('456'),
                        'permissions' => [
                            123 => ['GRANT', 'READ', 'WRITE'],
                            456 => ['GRANT', 'WRITE', 'READ']
                        ]
                    ]
                ]
            ]
        ];
    }

    private function getResourceMock(string $id): core_kernel_classes_Resource
    {
        $resourceMock = $this->createMock(core_kernel_classes_Resource::class);
        $resourceMock
            ->method('getUri')
            ->willReturn("http://unit.tests/mock.rdf#{$id}");

        return $resourceMock;
    }
}
