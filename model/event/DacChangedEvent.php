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

namespace oat\taoDacSimple\model\event;

use JsonSerializable;
use oat\oatbox\event\BulkEvent;

class DacChangedEvent implements BulkEvent, JsonSerializable
{
    private array $added;
    private array $removed;

    public function __construct(array $added, array $removed)
    {
        $this->added = $added;
        $this->removed = $removed;
    }

    public function getValues(): array
    {
        $added = array_chunk($this->added, 100);
        $removed = array_chunk($this->removed, 100);

        return array_merge(
            $this->enrichWithActions($added, 'add'),
            $this->enrichWithActions($removed, 'remove'),
        );
    }

    public function getName(): string
    {
        return __CLASS__;
    }

    public function jsonSerialize(): array
    {
        return [
            'added' => $this->added,
            'removed' => $this->removed,
        ];
    }

    private function enrichWithActions(array $values, string $action): array
    {
        return array_map(
            static fn (array $record): array => array_merge($record, ['action' => $action]),
            $values
        );
    }
}
