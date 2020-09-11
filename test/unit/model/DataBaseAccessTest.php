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
use oat\taoDacSimple\model\DataBaseAccess;
use oat\generis\test\MockObject;
use PDO;
use PDOStatement;

/**
 * Test database access
 *
 * @author Antoine Robin, <antoine.robin@vesperiagroup.com>
 * @package taodacSimple
 *
 */
class DataBaseAccessTest extends TestCase
{
    /**
     * @var DataBaseAccess
     */
    protected $instance;

    public function setUp(): void
    {
        $this->instance = new DataBaseAccess();
    }

    public function tearDown(): void
    {
        $this->instance = null;
    }

    /**
     * Return a persistence Mock object
     * @return MockObject
     */
    public function getPersistenceMock($queryParams, $queryFixture, $resultFixture)
    {
        $statementMock = $this->createMock(PDOStatement::class);
        $statementMock->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($resultFixture);
        $driverMock = $this->getMockForAbstractClass(
            'common_persistence_Driver',
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

        $persistenceMock = $this->getPersistenceMock($resourceIds, $queryFixture, $resultFixture);

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
     * Get the permissions a user has on a list of ressources
     * @dataProvider getPermissionProvider
     * @access public
     * @param array $userIds
     * @param array $resourceIds
     * @return array()
     */
    public function testGetPermissions($userIds, array $resourceIds)
    {
        // get privileges for a user/roles and a resource
        $returnValue = [];

        $inQueryResource = implode(',', array_fill(0, count($resourceIds), '?'));
        $inQueryUser = implode(',', array_fill(0, count($userIds), '?'));
        $query = 'SELECT ' . DataBaseAccess::COLUMN_RESOURCE_ID . ', ' . DataBaseAccess::COLUMN_PRIVILEGE
            . ' FROM ' . DataBaseAccess::TABLE_PRIVILEGES_NAME
            . " WHERE resource_id IN ($inQueryResource) AND user_id IN ($inQueryUser)";


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

        $params = $resourceIds;
        foreach ($userIds as $userId) {
            $params[] = $userId;
        }
        $persistenceMock = $this->getPersistenceMock($params, $query, $fetchResultFixture);

        $this->setPersistence($this->instance, $persistenceMock);

        $this->assertEquals($resultFixture, $this->instance->getPermissions($userIds, $resourceIds));
        $this->assertEquals([], $this->instance->getPermissions($userIds, []));
    }

    private function setPersistence($instance, $persistenceMock)
    {
        $reflectionClass = new \ReflectionClass(get_class($instance));
        $persistence = $reflectionClass->getProperty('persistence');
        $persistence->setAccessible(true);
        $persistence->setValue($instance, $persistenceMock);
    }
}
