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

namespace oat\taoDacSimple\test\unit\model;

use oat\taoDacSimple\model\SavePermissionsStrategy;
use PHPUnit\Framework\TestCase;

class SavePermissionsStrategyTest extends TestCase
{
    public function testGetPermissionsToRemove(): void
    {
        $strategy = new SavePermissionsStrategy();

        $result = $strategy->getPermissionsToRemove(
            [
                'p1' => ['READ'],
                'p2' => ['READ'],
                'p3' => ['READ', 'WRITE', 'GRANT'],
                'p4' => ['READ', 'WRITE', 'GRANT'],
            ],
            [
                'remove' => [
                    'p2' => ['READ', 'WRITE'],
                    'p3' => ['READ'],
                    'p4' => ['WRITE'],
                ]
            ]
        );

        $this->assertEquals(
            [
                'p2' => ['READ'],
                'p3' => ['READ', 'WRITE', 'GRANT'],
                'p4' => ['WRITE', 'GRANT'],
            ],
            $result
        );
    }

    public function testGetPermissionsToAdd(): void
    {
        $strategy = new SavePermissionsStrategy();

        $result = $strategy->getPermissionsToAdd(
            [
                'p1' => ['READ'],
                'p2' => ['READ'],
                'p3' => ['READ', 'WRITE', 'GRANT'],
                'p6' => ['READ'],
            ],
            [
                'add' => [
                    'p2' => ['READ', 'WRITE'],
                    'p3' => ['READ'],
                    'p4' => ['WRITE'],
                    'p5' => ['GRANT'],
                    'p6' => ['GRANT'],
                ]
            ]
        );

        $this->assertEquals(
            [
                'p2' => ['WRITE'],
                'p4' => ['WRITE', 'READ'],
                'p5' => ['GRANT', 'WRITE', 'READ'],
                'p6' => ['GRANT', 'WRITE'],
            ],
            $result
        );
    }

    public function testNormalizeRequest(): void
    {
        $strategy = new SavePermissionsStrategy();

        $result = $strategy->normalizeRequest(
            [
                'p1' => ['READ'],
                'p2' => ['READ'],
                'p3' => ['READ', 'WRITE', 'GRANT'],
            ],
            [
                'p1' => ['READ', 'WRITE'],
                'p2' => ['READ'],
                'p3' => ['READ', 'WRITE'],
            ]
        );

        $this->assertEquals(
            [
                'add'    => [
                    'p1' => ['WRITE'],
                ],
                'remove' => [
                    'p3' => ['GRANT']
                ]

            ],
            $result
        );
    }
}
