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

namespace oat\taoDacSimple\test\unit\model;

use oat\generis\test\TestCase;
use oat\taoDacSimple\model\DataBaseAccess;
use oat\taoDacSimple\model\RolePrivilegeRetriever;

class RolePrivilegeRetrieverTest extends TestCase
{
    /** @var RolePrivilegeRetriever */
    private $sut;

    /** @var DataBaseAccess */
    private $databaseAccess;

    public function setUp(): void
    {
        $this->databaseAccess = $this->createMock(DataBaseAccess::class);

        $this->sut = new RolePrivilegeRetriever();
        $this->sut->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    DataBaseAccess::SERVICE_ID => $this->databaseAccess
                ]
            )
        );
    }

    public function testRetrieveByResourceIds(): void
    {
        $this->databaseAccess
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

        $this->assertSame(
            [
                'http://www.tao.lu/Ontologies/TAO.rdf#BackOfficeRole' => [
                    'READ',
                    'WRITE',
                    'GRANT'
                ]
            ],
            $this->sut->retrieveByResourceIds(['id'])
        );
    }
}
