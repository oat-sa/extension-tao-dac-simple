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

namespace oat\taoDacSimple\test\unit\model\Comment;

use core_kernel_classes_Class;
use core_kernel_classes_Resource;
use oat\generis\model\data\Ontology;
use oat\generis\model\GenerisRdf;
use oat\taoDacSimple\model\Comment\DacMentionEligibleUsersProvider;
use oat\taoDacSimple\model\PermissionProvider;
use oat\taoDacSimple\model\RolePrivilegeRetriever;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use tao_models_classes_UserService;

class DacMentionEligibleUsersProviderTest extends TestCase
{
    private RolePrivilegeRetriever|MockObject $rolePrivilegeRetriever;
    private Ontology|MockObject $ontology;
    private tao_models_classes_UserService|MockObject $userService;
    private DacMentionEligibleUsersProvider $sut;

    protected function setUp(): void
    {
        $this->rolePrivilegeRetriever = $this->createMock(RolePrivilegeRetriever::class);
        $this->ontology = $this->createMock(Ontology::class);
        $this->userService = $this->createMock(tao_models_classes_UserService::class);

        $this->sut = new DacMentionEligibleUsersProvider(
            $this->rolePrivilegeRetriever,
            $this->ontology,
            $this->userService
        );
    }

    public function testReturnsEmptyWhenNoGrants(): void
    {
        $this->rolePrivilegeRetriever
            ->method('retrieveByResourceIds')
            ->with(['http://example.test/item#1'])
            ->willReturn([]);

        $this->assertSame([], $this->sut->getEligibleUserUris('http://example.test/item#1'));
    }

    public function testIncludesDirectUserWithRead(): void
    {
        $userUri = 'http://example.test/user#alice';

        $this->rolePrivilegeRetriever
            ->method('retrieveByResourceIds')
            ->willReturn([
                $userUri => [PermissionProvider::PERMISSION_READ],
            ]);

        $roleClass = $this->createMock(core_kernel_classes_Class::class);
        $this->ontology
            ->method('getClass')
            ->with(GenerisRdf::CLASS_ROLE)
            ->willReturn($roleClass);

        $identity = $this->createMock(core_kernel_classes_Resource::class);
        $identity->method('isInstanceOf')->with($roleClass)->willReturn(false);

        $this->ontology
            ->method('getResource')
            ->with($userUri)
            ->willReturn($identity);

        $this->userService->expects($this->never())->method('getAllUsers');

        $this->assertSame([$userUri], $this->sut->getEligibleUserUris('http://example.test/item#1'));
    }

    public function testExpandsRoleGrantToMemberUsers(): void
    {
        $roleUri = 'http://example.test/roles#authors';
        $memberUri = 'http://example.test/user#bob';

        $this->rolePrivilegeRetriever
            ->method('retrieveByResourceIds')
            ->willReturn([
                $roleUri => [PermissionProvider::PERMISSION_WRITE],
            ]);

        $roleClass = $this->createMock(core_kernel_classes_Class::class);
        $this->ontology
            ->method('getClass')
            ->with(GenerisRdf::CLASS_ROLE)
            ->willReturn($roleClass);

        $roleIdentity = $this->createMock(core_kernel_classes_Resource::class);
        $roleIdentity->method('isInstanceOf')->with($roleClass)->willReturn(true);

        $this->ontology
            ->method('getResource')
            ->with($roleUri)
            ->willReturn($roleIdentity);

        $member = $this->createMock(core_kernel_classes_Resource::class);
        $member->method('getUri')->willReturn($memberUri);

        $this->userService
            ->expects($this->once())
            ->method('getAllUsers')
            ->with(
                [
                    'recursive' => true,
                    'like' => false,
                ],
                [
                    GenerisRdf::PROPERTY_USER_ROLES => $roleUri,
                ]
            )
            ->willReturn([$member]);

        $this->assertSame([$memberUri], $this->sut->getEligibleUserUris('http://example.test/item#1'));
    }

    public function testExcludesSubjectsWithoutEffectiveRead(): void
    {
        $this->rolePrivilegeRetriever
            ->method('retrieveByResourceIds')
            ->willReturn([
                'http://example.test/user#nope' => [],
            ]);

        $this->ontology->expects($this->never())->method('getResource');

        $this->assertSame([], $this->sut->getEligibleUserUris('http://example.test/item#1'));
    }
}
