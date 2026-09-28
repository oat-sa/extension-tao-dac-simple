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
 * Foundation, Inc., 31 Milk St # 960789 Boston, MA 02196 USA
 *
 * Copyright (c) 2026 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoDacSimple\test\unit\model;

use core_kernel_classes_Resource;
use oat\taoDacSimple\model\DacMentionEligibleUsersProvider;
use oat\taoDacSimple\model\RolePrivilegeRetriever;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DacMentionEligibleUsersProviderTest extends TestCase
{
    private RolePrivilegeRetriever|MockObject $rolePrivilegeRetriever;
    private \tao_models_classes_RoleService|MockObject $roleService;

    protected function setUp(): void
    {
        $this->rolePrivilegeRetriever = $this->createMock(RolePrivilegeRetriever::class);
        $this->roleService = $this->createMock(\tao_models_classes_RoleService::class);
    }

    public function testReturnsEmptyWhenNoAclEntriesExist(): void
    {
        $this->rolePrivilegeRetriever
            ->method('retrieveByResourceIds')
            ->with(['http://example.test/resource#1'])
            ->willReturn([]);

        $this->roleService->expects($this->never())->method('getUsers');

        $provider = new DacMentionEligibleUsersProvider($this->rolePrivilegeRetriever, $this->roleService);

        $this->assertSame([], $provider->getEligibleUserUris('http://example.test/resource#1'));
    }

    public function testExpandsRolesAndKeepsDirectUsersInEligibleList(): void
    {
        $this->rolePrivilegeRetriever
            ->method('retrieveByResourceIds')
            ->with(['http://example.test/resource#2'])
            ->willReturn([
                'http://example.test/role#author' => ['READ'],
                'http://example.test/user#direct' => ['READ'],
                'http://example.test/role#reviewer' => ['WRITE'],
            ]);

        $this->roleService
            ->method('getUsers')
            ->willReturnCallback(static function (core_kernel_classes_Resource $identity): array {
                if ($identity->getUri() === 'http://example.test/role#author') {
                    return ['http://example.test/user#alice', 'http://example.test/user#bob'];
                }

                if ($identity->getUri() === 'http://example.test/role#reviewer') {
                    return ['http://example.test/user#bob'];
                }

                return [];
            });

        $provider = new DacMentionEligibleUsersProvider($this->rolePrivilegeRetriever, $this->roleService);

        $this->assertSame(
            [
                'http://example.test/user#alice',
                'http://example.test/user#bob',
                'http://example.test/user#direct',
            ],
            $provider->getEligibleUserUris('http://example.test/resource#2')
        );
    }
}
