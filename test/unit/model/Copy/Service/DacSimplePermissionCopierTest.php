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
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA;
 *
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com
 */

declare(strict_types=1);

namespace oat\taoDacSimple\test\unit\model\Copy\Service;

use core_kernel_classes_Resource;
use oat\taoDacSimple\model\Copy\Service\DacSimplePermissionCopier;
use oat\taoDacSimple\model\DataBaseAccess;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DacSimplePermissionCopierTest extends TestCase
{
    /** @var DacSimplePermissionCopier */
    private $sut;

    /** @var DataBaseAccess|MockObject */
    private $dataBaseAccess;

    public function setUp(): void
    {
        $this->dataBaseAccess = $this->createMock(DataBaseAccess::class);
        $this->sut = new DacSimplePermissionCopier($this->dataBaseAccess);
    }

    public function testCopy(): void
    {
        $from = $this->createMock(core_kernel_classes_Resource::class);
        $from->method('getUri')
            ->willReturn('fromUri');

        $to = $this->createMock(core_kernel_classes_Resource::class);
        $to->method('getUri')
            ->willReturn('toUri');

        $this->dataBaseAccess
            ->expects($this->once())
            ->method('removeAllPermissions')
            ->with(
                [
                    'toUri'
                ]
            );

        $this->dataBaseAccess
            ->expects($this->once())
            ->method('getResourcePermissions')
            ->with('fromUri')
            ->willReturn(
                [
                    'user1' => [
                        'WRITE'
                    ],
                    'user2' => [
                        'READ'
                    ]
                ]
            );

        $this->dataBaseAccess
            ->expects($this->at(2))
            ->method('addPermissions')
            ->with(
                'user1',
                'toUri',
                [
                    'WRITE'
                ]
            );

        $this->dataBaseAccess
            ->expects($this->at(3))
            ->method('addPermissions')
            ->with(
                'user2',
                'toUri',
                [
                    'READ'
                ]
            );

        $this->sut->copy($from, $to);
    }
}
