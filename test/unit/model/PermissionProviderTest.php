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
 * Copyright (c) 2020-2021 (original work) Open Assessment Technologies SA;
 *
 */

declare(strict_types=1);

namespace oat\taoDacSimple\test\unit\model;

use oat\generis\test\ServiceManagerMockTrait;
use PHPUnit\Framework\TestCase;
use oat\taoDacSimple\model\DataBaseAccess;
use oat\taoDacSimple\model\PermissionProvider;
use oat\taoDacSimple\model\RolePrivilegeRetriever;

class PermissionProviderTest extends TestCase
{
    use ServiceManagerMockTrait;

    /** @var PermissionProvider */
    private $sut;

    /** @var RolePrivilegeRetriever */
    private $userPrivilegeRetriever;

    public function setUp(): void
    {
        $this->userPrivilegeRetriever = $this->createMock(RolePrivilegeRetriever::class);
        $serviceLocator = $this->getServiceManagerMock(
            [
                DataBaseAccess::SERVICE_ID => $this->createMock(DataBaseAccess::class),
                RolePrivilegeRetriever::class => $this->userPrivilegeRetriever,
            ]
        );

        $this->sut = new PermissionProvider();
        $this->sut->setServiceLocator($serviceLocator);
    }

    public function testGetResourceAccessData(): void
    {
        $this->userPrivilegeRetriever
            ->method('retrieveByResourceIds')
            ->with(['id'])
            ->willReturn([]);

        self::assertSame([], $this->sut->getResourceAccessData('id'));
    }
}
