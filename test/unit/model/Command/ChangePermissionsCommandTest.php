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
 * Copyright (c) 2023 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoDacSimple\test\unit\model\Copy\Service;

use core_kernel_classes_Resource;
use oat\taoDacSimple\model\Command\ChangePermissionsCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ChangePermissionsCommandTest extends TestCase
{
    /**
     * @var core_kernel_classes_Resource|MockObject
     */
    private core_kernel_classes_Resource $root;

    public function setUp(): void
    {
        $this->root = $this->createMock(core_kernel_classes_Resource::class);
    }

    /**
     * @dataProvider constructorDataProvider
     */
    public function testConstructor(array $privileges): void
    {
        $sut = new ChangePermissionsCommand($this->root, $privileges);

        $this->assertSame($this->root, $sut->getRoot());
        $this->assertSame($privileges, $sut->getPrivilegesPerUser());
        $this->assertFalse($sut->isRecursive());
        $this->assertFalse($sut->applyToNestedResources());
    }

    public function constructorDataProvider(): array
    {
        return [
            'no permissions' => [
                'privileges' => [],
            ],
            'READ' => [
                'privileges' => ['READ'],
            ],
            'READ+WRITE' => [
                'privileges' => ['READ', 'WRITE'],
            ],
            'READ+WRITE+GRANT' => [
                'privileges' => ['READ', 'WRITE', 'GRANT'],
            ],
        ];
    }

    public function testWithRecursion(): void
    {
        $sut = new ChangePermissionsCommand($this->root, []);
        $sut->withRecursion();

        $this->assertTrue($sut->isRecursive());
        $this->assertFalse($sut->applyToNestedResources());
    }

    public function testWithNestedResources(): void {
        $sut = new ChangePermissionsCommand($this->root, []);
        $sut->withNestedResources();

        $this->assertFalse($sut->isRecursive());
        $this->assertTrue($sut->applyToNestedResources());
    }

    public function testWithBoth(): void {
        $sut = new ChangePermissionsCommand($this->root, []);
        $sut->withRecursion();
        $sut->withNestedResources();

        $this->assertTrue($sut->isRecursive());
        $this->assertTrue($sut->applyToNestedResources());
    }
}
