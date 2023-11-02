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
 * Copyright (c) 2023 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

namespace oat\taoDacSimple\test\unit\model\event;

use JsonSerializable;
use oat\oatbox\event\BulkEvent;
use oat\oatbox\event\Event;
use oat\taoDacSimple\model\event\DacChangedEvent;
use PHPUnit\Framework\TestCase;

class DacChangedEventTest extends TestCase
{
    private array $added;
    private array $removed;
    private DacChangedEvent $sut;

    protected function setUp(): void
    {
        $this->added = [
            ['resource' => 'resource1', 'user' => 'user1', 'permissions' => ['READ', 'WRITE', 'GRANT']],
            ['resource' => 'resource2', 'user' => 'user2', 'permissions' => ['READ', 'WRITE']],
            ['resource' => 'resource2', 'user' => 'user3', 'permissions' => ['READ']],
        ];
        $this->removed = [
            ['resource' => 'resource1', 'user' => 'user4', 'permissions' => ['GRANT']],
            ['resource' => 'resource1', 'user' => 'user5', 'permissions' => ['WRITE', 'GRANT']],
            ['resource' => 'resource3', 'user' => 'user6', 'permissions' => ['READ']],
        ];

        $this->sut = new DacChangedEvent($this->added, $this->removed);
    }

    public function testIsInstanceOfEvent(): void
    {
        $this->assertInstanceOf(Event::class, $this->sut);
    }

    public function testGetName(): void
    {
        $this->assertEquals(DacChangedEvent::class, $this->sut->getName());
    }

    public function testIsInstanceOfBulkEvent(): void
    {
        $this->assertInstanceOf(BulkEvent::class, $this->sut);
    }

    public function testGetValues(): void
    {
        $this->assertEquals(
            [
                [
                    'action' => 'add',
                    ...$this->added,
                ],
                [
                    'action' => 'remove',
                    ...$this->removed,
                ]
            ],
            $this->sut->getValues()
        );
    }

    public function testInstanceOfJsonSerializable(): void
    {
        $this->assertInstanceOf(JsonSerializable::class, $this->sut);
    }

    public function testJsonSerialize(): void
    {
        $this->assertEquals(
            ['added' => $this->added, 'removed' => $this->removed],
            $this->sut->jsonSerialize()
        );
    }
}
