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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoDacSimple\test\unit\model\eventHandler;

use core_kernel_classes_Class;
use core_kernel_classes_Resource;
use oat\generis\test\MockObject;
use oat\generis\test\TestCase;
use oat\tao\model\event\ResourceMovedEvent;
use oat\taoDacSimple\model\eventHandler\ResourceUpdateHandler;
use oat\taoDacSimple\model\PermissionsService;
use oat\taoDacSimple\model\PermissionsServiceFactory;
use oat\taoDacSimple\model\RolePrivilegeRetriever;

class ResourceUpdateHandlerTest extends TestCase
{
    /** @var RolePrivilegeRetriever|MockObject */
    private $rolePrivilegeRetriever;

    /** @var PermissionsService|MockObject */
    private $permissionsServiceMock;

    /** @var PermissionsServiceFactory|MockObject */
    private $permissionsServiceFactory;

    /** @var ResourceMovedEvent|MockObject */
    private $eventMock;

    /** @var core_kernel_classes_Resource|MockObject */
    private $resourceMock;

    /** @var core_kernel_classes_Class|MockObject */
    private $classMock;

    /** @var ResourceUpdateHandler */
    private $subject;

    public function setUp(): void
    {
        $this->permissionsServiceMock = $this->createMock(PermissionsService::class);
        $this->rolePrivilegeRetriever = $this->createMock(RolePrivilegeRetriever::class);
        $this->permissionsServiceFactory = $this->createMock(PermissionsServiceFactory::class);
        $this->eventMock = $this->createMock(ResourceMovedEvent::class);
        $this->resourceMock = $this->createMock(core_kernel_classes_Resource::class);
        $this->classMock = $this->createMock(core_kernel_classes_Class::class);

        $this->permissionsServiceFactory
            ->method('create')
            ->willReturn($this->permissionsServiceMock);

        $this->subject = new ResourceUpdateHandler();
        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    RolePrivilegeRetriever::class => $this->rolePrivilegeRetriever,
                    PermissionsServiceFactory::class => $this->permissionsServiceFactory
                ]
            )
        );
    }

    public function testCatchResourceUpdated()
    {
        $this->eventMock
            ->method('getMovedResource')
            ->willReturn($this->resourceMock);

        $this->resourceMock
            ->method('getUri')
            ->willReturn('resourceUri');

        $this->eventMock
            ->method('getDestinationClass')
            ->willReturn($this->classMock);

        $this->classMock
            ->method('getUri')
            ->willReturn('classUri');

        $this->rolePrivilegeRetriever
            ->expects($this->once())
            ->method('retrieveByResourceIds')
            ->with(
                [
                    'classUri',
                    'resourceUri'
                ]
            )
            ->willReturn(
                [
                    'http://www.tao.lu/Ontologies/TAO.rdf#BackOfficeRole' => [
                        'GRANT',
                        'READ',
                        'WRITE'
                    ],
                ]
            );

        $this->permissionsServiceMock
            ->expects($this->once())
            ->method('saveResourcePermissions')
            ->with(
                true,
                $this->resourceMock,
                [
                    'http://www.tao.lu/Ontologies/TAO.rdf#BackOfficeRole' => [
                        'GRANT',
                        'READ',
                        'WRITE'
                    ],
                ]
            );

        $this->subject->catchResourceUpdated($this->eventMock);

    }
}
