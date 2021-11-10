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
 * Copyright (c) 2014-2017 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT); *
 *
 *
 */

namespace oat\taoDacSimple\test\unit\model;

use common_persistence_SqlPersistence;
use oat\generis\test\TestCase;
use oat\oatbox\event\EventManager;
use oat\taoDacSimple\model\DataBaseAccess;
use oat\generis\test\MockObject;
use oat\taoDacSimple\model\event\DacAddedEvent;
use PDO;
use PDOStatement;
use common_persistence_Driver;
use core_kernel_classes_Resource;
use Psr\Log\NullLogger;

/**
 * Test database access
 *
 * @author Antoine Robin, <antoine.robin@vesperiagroup.com>
 * @package taodacSimple
 *
 */
class DataBaseAccessTest extends TestCase
{
    private const INSERT_CHUNK_SIZE = 20000;

    /**
     * @var DataBaseAccess
     */
    protected $instance;

    public function setUp(): void
    {
        $this->instance = new DataBaseAccess();
        $this->instance->setLogger(new NullLogger());
    }

    public function tearDown(): void
    {
        $this->instance = null;
    }

    /**
     * Return a persistence Mock object supporting query()
     * @return MockObject
     */
    public function getPersistenceMockForGetOps($queryParams, $queryFixture, $resultFixture) {
        $statementMock = $this->createMock(PDOStatement::class);
        $statementMock->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($resultFixture);

        $driverMock = $this->getMockForAbstractClass(
            common_persistence_Driver::class,
            [],
            'common_persistence_Driver_Mock',
            false,
            false,
            true,
            ['query'],
            false
        );


        $persistenceMock = $this->createMock(common_persistence_SqlPersistence::class);
        $persistenceMock
            ->method('getDriver')
            ->with([], $driverMock)
            ->willReturn($driverMock);

        $persistenceMock
            ->method('query')
            ->with($queryFixture, $queryParams)
            ->willReturn($statementMock);

        return $persistenceMock;
    }

    /**
     * Return a persistence Mock object supporting transactional() & insertMultiple()
     * @return MockObject
     */
    public function getPersistenceMockForSetOps(int $numInsertMultipleCalls) {
        $driverMock = $this->getMockForAbstractClass(
            common_persistence_Driver::class,
            [],
            'common_persistence_Driver_Mock',
            false,
            false,
            true,
            ['insertMultiple'],
            false
        );

        $persistenceMock = $this->createMock(common_persistence_SqlPersistence::class);
        $persistenceMock
            ->method('getDriver')
            ->with([], $driverMock)
            ->willReturn($driverMock);

        $persistenceMock
            ->expects($this->exactly($numInsertMultipleCalls))
            ->method('insertMultiple')
            ->with(DataBaseAccess::TABLE_PRIVILEGES_NAME, self::anything())
            ->willReturnCallback(function($tableName, array $data, array $types = []) {
                return count($data);
            });

        $persistenceMock
            ->expects($this->exactly($numInsertMultipleCalls > 0 ? 1 : 0))
            ->method('transactional')
            ->willReturnCallback(function (callable $closure) {
                $closure();
            });

        return $persistenceMock;
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
     * @param $resourceIds
     */
    public function testGetUsersWithPermissions($resourceIds)
    {
        $inQuery = implode(',', array_fill(0, count($resourceIds), '?'));
        $queryFixture = 'SELECT ' . DataBaseAccess::COLUMN_RESOURCE_ID . ', ' . DataBaseAccess::COLUMN_USER_ID . ', '
            . DataBaseAccess::COLUMN_PRIVILEGE . ' FROM ' . DataBaseAccess::TABLE_PRIVILEGES_NAME
            . " WHERE resource_id IN ($inQuery)";

        $resultFixture = [
            ['fixture']
        ];

        $persistenceMock = $this->getPersistenceMockForGetOps($resourceIds, $queryFixture, $resultFixture);

        $this->setPersistence($this->instance, $persistenceMock);

        $this->assertSame($resultFixture, $this->instance->getUsersWithPermissions($resourceIds));
    }


    /**
     * @return array
     */
    public function getPermissionProvider()
    {
        return [
            [[1, 2, 3], [1, 2, 3]],
            [[1], [2]],
        ];
    }

    /**
     * Get the permissions a user has on a list of resources
     * @dataProvider getPermissionProvider
     *
     * @access public
     * @param array $userIds
     * @param array $resourceIds
     */
    public function testGetPermissions(array $userIds, array $resourceIds)
    {
        // Get privileges for a user/roles and a resource
        //
        $params = array_merge($resourceIds, $userIds);
        $inQueryResource = implode(',', array_fill(0, count($resourceIds), '?'));
        $inQueryUser = implode(',', array_fill(0, count($userIds), '?'));
        $cols = implode(', ', [DataBaseAccess::COLUMN_RESOURCE_ID, DataBaseAccess::COLUMN_PRIVILEGE]);
        $query = "SELECT {$cols} FROM " . DataBaseAccess::TABLE_PRIVILEGES_NAME
            . " WHERE resource_id IN ({$inQueryResource}) AND user_id IN ({$inQueryUser})";

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

        $persistenceMock = $this->getPersistenceMockForGetOps($params, $query, $fetchResultFixture);
        $this->setPersistence($this->instance, $persistenceMock);

        $this->assertEquals($resultFixture, $this->instance->getPermissions($userIds, $resourceIds));
        $this->assertEquals([], $this->instance->getPermissions($userIds, []));
    }

    /**
     * @dataProvider addMultiplePermissionsEmptyScenariosDataProvider
     * @dataProvider addMultiplePermissionsBasicScenariosDataProvider
     */
    public function testAddMultiplePermissions($numEvents, array $permissionData)
    {
        $numInserts = (int) ceil($numEvents / self::INSERT_CHUNK_SIZE);

        $persistenceMock = $this->getPersistenceMockForSetOps($numInserts);
        $this->setPersistence($this->instance, $persistenceMock);
        $this->setEventManager($this->instance, $numEvents);

        $this->instance->addMultiplePermissions($permissionData);
    }

    public function addMultiplePermissionsEmptyScenariosDataProvider(): array
    {
        return [
            'Empty arrays don\'t result in events nor queries' => [
                'numEvents' => 0,
                'permissionData' => []
            ],
            'Empty permissions don\'t result in events nor queries' => [
                'numEvents' => 0,
                'permissionData' => [
                    [
                        'resource' => $this->getResourceMock('123'),
                        'permissions' => []
                    ]
                ]
            ]
        ];
    }

    public function addMultiplePermissionsBasicScenariosDataProvider(): array
    {
        return [
            '3 permissions: One resource, single user' => [
                'numEvents' => 3,
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
                'permissionData' => [
                    [
                        'resource' => $this->getResourceMock('123'),
                        'permissions' => [
                            123 => ['GRANT', 'READ', 'WRITE'],
                            456 => ['GRANT', 'READ', 'WRITE']
                        ]
                    ]
                ]
            ]
        ];
    }

    // @todo Tackle the "easy" (smaller) cases first
    public function addMultiplePermissionsThousandsPermissionsDataProvider(): array
    {
        return [];
    }

    private function setPersistence(DataBaseAccess $instance, $persistenceMock)
    {
        $reflector = new \ReflectionProperty(get_class($instance), 'persistence');
        $reflector->setAccessible(true);
        $reflector->setValue($instance, $persistenceMock);
    }

    private function setEventManager(DataBaseAccess $instance, int $numTriggeredEvents)
    {
        $eventManager = $this->createMock(EventManager::class);
        $eventManager
            ->expects($this->exactly($numTriggeredEvents))
            ->method('trigger')
            ->with($this->isInstanceOf(DacAddedEvent::class));

        $locator = $this->getServiceLocatorMock([
            EventManager::SERVICE_ID => $eventManager
        ]);

        $instance->setServiceManager($locator);
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
