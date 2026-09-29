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
use oat\taoDacSimple\model\DataBaseAccess;
use oat\taoDacSimple\model\DacMentionEligibleUsersProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use tao_models_classes_UserService;

class DacMentionEligibleUsersProviderTest extends TestCase
{
    private DataBaseAccess|MockObject $dataBaseAccess;
    private tao_models_classes_UserService|MockObject $userService;

    protected function setUp(): void
    {
        $this->dataBaseAccess = $this->createMock(DataBaseAccess::class);
        $this->userService = $this->createMock(tao_models_classes_UserService::class);
    }

    public function testFilterCandidatesForResourceUsesUserAndRoleIdentities(): void
    {
        $role = $this->createMock(core_kernel_classes_Resource::class);
        $role->method('getUri')->willReturn('http://example.test/role#author');

        $this->userService
            ->expects($this->exactly(2))
            ->method('getUserRoles')
            ->willReturnCallback(static function (core_kernel_classes_Resource $user) use ($role): array {
                if ($user->getUri() === 'http://example.test/user#alice') {
                    return [$role];
                }

                return [];
            });

        $this->dataBaseAccess
            ->expects($this->once())
            ->method('getPermissionsByUsersAndResources')
            ->with(
                $this->callback(static function (array $identities): bool {
                    sort($identities);

                    return $identities === [
                        'http://example.test/role#author',
                        'http://example.test/user#alice',
                        'http://example.test/user#bob',
                    ];
                }),
                ['http://example.test/resource#4']
            )
            ->willReturn([
                'http://example.test/resource#4' => [
                    'http://example.test/role#author' => ['READ'],
                ],
            ]);

        $provider = new DacMentionEligibleUsersProvider(
            $this->dataBaseAccess,
            $this->userService
        );

        $filtered = $provider->filterCandidatesForResource(
            'http://example.test/resource#4',
            [
                [
                    'id' => 'http://example.test/user#alice',
                    'login' => 'alice',
                    'displayName' => 'alice',
                ],
                [
                    'id' => 'http://example.test/user#bob',
                    'login' => 'bob',
                    'displayName' => 'bob',
                ],
            ]
        );

        $this->assertSame(['alice'], array_column($filtered, 'login'));
    }
}
