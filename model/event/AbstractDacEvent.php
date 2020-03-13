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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA
 *
 */

namespace oat\taoDacSimple\model\event;

use JsonSerializable;
use oat\oatbox\event\Event;

abstract class AbstractDacEvent implements Event, JsonSerializable
{

    /** @var string */
    protected $userUri;
    /** @var string */
    protected $resourceUri;
    /** @var string[]  */
    protected $privilege;

    /**
     * @param string   $userUri
     * @param string   $resourceUri
     * @param string[] $privilege
     */
    public function __construct(string $userUri, string $resourceUri, array $privilege)
    {
        $this->userUri = $userUri;
        $this->resourceUri = $resourceUri;
        $this->privilege = $privilege;
    }


    /**
     * Return a unique name for this event
     * @see \oat\oatbox\event\Event::getName()
     */
    public function getName()
    {
        return static::class;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return [
            'userUri'   => $this->userUri,
            'accessUri' => $this->resourceUri,
            'privilege' => $this->privilege
        ];
    }

    /**
     * @return string
     */
    public function getUserUri(): string
    {
        return $this->userUri;
    }

    /**
     * @return string
     */
    public function getResourceUri(): string
    {
        return $this->resourceUri;
    }

    /**
     * @return string[]
     */
    public function getPrivilege(): array
    {
        return $this->privilege;
    }
}
