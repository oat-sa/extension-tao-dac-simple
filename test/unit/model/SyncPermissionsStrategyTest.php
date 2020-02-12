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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA;
 *
 */

namespace oat\taoDacSimple\test;

use oat\taoDacSimple\model\SyncPermissionsStrategy;
use PHPUnit\Framework\TestCase;

class SyncPermissionsStrategyTest extends TestCase
{
    public function testNormalizeRequest(): void
    {
        $strategy = new SyncPermissionsStrategy();

        $result = $strategy->normalizeRequest(
            [],
            [
                'p1' => ['READ', 'WRITE'],
                'p2' => ['READ'],
                'p3' => ['READ', 'WRITE'],
            ]
        );

        $this->assertEquals(
            [
                'add' => [
                    'p1' => ['READ', 'WRITE'],
                    'p2' => ['READ'],
                    'p3' => ['READ', 'WRITE'],
                ]
            ],
            $result
        );
    }

    public function testGetPermissionsToAdd(): void
    {
        $strategy = new SyncPermissionsStrategy();

        $result = $strategy->getPermissionsToAdd(
            [
                'p2' => ['READ'],
                'p3' => ['READ', 'WRITE'],
            ],
            [
                'add' => [
                    'p1' => ['READ', 'WRITE'],
                    'p2' => ['READ', 'GRANT'],
                    'p3' => ['READ',],
                ]
            ]
        );

        $this->assertEquals(
            [
                'p1' => ['READ', 'WRITE'],
                'p2' => ['GRANT'],
            ],
            $result
        );
    }

    public function testGetPermissionsToRemove(): void
    {
        $strategy = new SyncPermissionsStrategy();

        $result = $strategy->getPermissionsToRemove(
            [
                'p2' => ['READ'],
                'p3' => ['READ', 'WRITE'],
            ],
            [
                'add' => [
                    'p1' => ['READ', 'WRITE'],
                    'p2' => ['READ', 'GRANT'],
                    'p3' => ['READ',],
                ]
            ]
        );

        $this->assertEquals(
            [
                'p3' => ['WRITE',],
            ],
            $result
        );
    }
}
