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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 *
 */

declare(strict_types=1);

namespace oat\taoDacSimple\test\unit\model\user;

use oat\generis\test\TestCase;
use oat\taoDacSimple\model\DataBaseAccess;
use oat\taoDacSimple\model\user\UserPrivilegeRetriever;

class UserPrivilegeRetrieverTest extends TestCase
{
    /** @var UserPrivilegeRetriever */
    private $sut;

    /** @var DataBaseAccess */
    private $databaseAccess;

    public function setUp(): void
    {
        $this->databaseAccess = $this->createMock(DataBaseAccess::class);

        $serviceLocator = $this->getServiceLocatorMock(
            [
                DataBaseAccess::SERVICE_ID => $this->databaseAccess
            ]
        );

        $this->sut = new UserPrivilegeRetriever();
        $this->sut->setServiceLocator($serviceLocator);
    }

    public function testGetResourceAccessData(): void
    {
        $result = $this->sut->getResourceAccessData('id');

        $this->databaseAccess
            ->expects($this->once())
            ->method('getUsersWithPermissions')
            ->with(['id'])
            ->willReturn(
                [
                    [
                        'resource_id' => 'id',
                        'user_id' => 'http://www.tao.lu/Ontologies/TAO.rdf#BackOfficeRole',
                        'privilege' => 'READ'
                    ],
                    [
                        'resource_id' => 'id',
                        'user_id' => 'http://www.tao.lu/Ontologies/TAO.rdf#BackOfficeRole',
                        'privilege' => 'WRITE'
                    ],
                    [
                        'resource_id' => 'id',
                        'user_id' => 'http://www.tao.lu/Ontologies/TAO.rdf#BackOfficeRole',
                        'privilege' => 'GRANT'
                    ]
                ]
            );

        $expected = [
            'http://www.tao.lu/Ontologies/TAO.rdf#BackOfficeRole' => [
                'READ',
                'WRITE',
                'GRANT'
            ]
        ];

        $this->assertSame($expected, $result);
    }
}
