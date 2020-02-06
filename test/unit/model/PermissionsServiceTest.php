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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA;
 *
 */

namespace oat\taoDacSimple\test\unit\model;

use common_exception_InconsistentData;
use common_session_Session;
use core_kernel_classes_Class;
use oat\generis\test\MockObject;
use oat\oatbox\service\exception\InvalidServiceManagerException;
use oat\oatbox\user\AnonymousUser;
use oat\taoDacSimple\model\DataBaseAccess;
use oat\taoDacSimple\model\PermissionProvider;
use oat\taoDacSimple\model\PermissionsService;
use oat\taoDacSimple\model\PermissionsStrategyInterface;
use PHPUnit\Framework\TestCase;

class PermissionsServiceTest extends TestCase
{
    /**
     * @var MockObject|PermissionProvider
     */
    private $permissionProvider;
    /**
     * @var MockObject|DataBaseAccess
     */
    private $databaseAccess;
    /**
     * @var PermissionsService
     */
    private $service;
    /**
     * @var MockObject|PermissionsStrategyInterface
     */
    private $strategy;
    /**
     * @var MockObject|common_session_Session
     */
    private $session;

    protected function setUp(): void
    {
        $this->permissionProvider = $this->getMockBuilder(PermissionProvider::class)
            ->setMethods(['getPermissions'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->databaseAccess = $this->createMock(DataBaseAccess::class);
        $this->databaseAccess->method('getResourcePermissions')->willReturn([]);

        $this->strategy = $this->createMock(PermissionsStrategyInterface::class);
        $this->session = $this->createMock(common_session_Session::class);

        $this->service = new PermissionsService(
            $this->permissionProvider,
            $this->databaseAccess,
            $this->strategy,
            $this->session
        );
    }

    /**
     * @throws common_exception_InconsistentData
     * @throws InvalidServiceManagerException
     */
    public function testSaveAddPermissions(): void
    {
        $this->databaseAccess->expects($this->atLeast(1))->method('addPermissions');
        $this->databaseAccess->expects($this->never())->method('removePermissions');

        $this->strategy->method('normalizeRequest')->willReturn(
            [
                'add' => [
                    'uid1' => ['GRANT', 'READ', 'WRITE']
                ]
            ]
        );

        $this->strategy->method('getPermissionsToAdd')->willReturn([
            'uid1' => ['GRANT', 'READ', 'WRITE']
        ]);

        $resource = $this->createMock(core_kernel_classes_Class::class);

        $this->service->savePermissions(
            false,
            $resource,
            [
                'uid1' => ['GRANT', 'READ', 'WRITE']
            ],
            'resourceId'
        );
    }

    /**
     * @throws common_exception_InconsistentData
     * @throws InvalidServiceManagerException
     */
    public function testSavePermissionsAddRecursively(): void
    {
        $this->databaseAccess->expects($this->exactly(2))->method('addPermissions');
        $this->databaseAccess->expects($this->never())->method('removePermissions');

        $this->strategy->method('normalizeRequest')->willReturn(
            [
                'add' => [
                    'uid1' => ['GRANT', 'READ', 'WRITE']
                ]
            ]
        );

        $this->strategy->method('getPermissionsToAdd')->willReturn([
            'uid1' => ['GRANT', 'READ', 'WRITE']
        ]);

        /** @var MockObject|core_kernel_classes_Class $childResource */
        $childResource = $this->createMock(core_kernel_classes_Class::class);
        $childResource->method('getUri')->willReturn('uid2uri');

        /** @var MockObject|core_kernel_classes_Class $resource */
        $resource = $this->createMock(core_kernel_classes_Class::class);
        $resource->method('getSubClasses')->willReturn([
            $childResource
        ]);

        $resource->method('getInstances')->willReturn([]);

        $this->service->savePermissions(
            true,
            $resource,
            [
                'uid1' => ['GRANT', 'READ', 'WRITE']
            ],
            'resourceId'
        );
    }

    /**
     * @throws common_exception_InconsistentData
     * @throws InvalidServiceManagerException
     */
    public function testSaveRemovePermissions(): void
    {
        $this->databaseAccess->expects($this->never())->method('addPermissions');
        $this->databaseAccess->expects($this->once())->method('removePermissions');

        $this->session->method('getUser')->willReturn(new AnonymousUser());

        $this->permissionProvider->method('getPermissions')->willReturn([
            'uid1' => ['GRANT', 'READ', 'WRITE']
        ]);

        $this->strategy->method('normalizeRequest')->willReturn(
            [
                'remove' => [
                    'uid1' => ['GRANT', 'READ', 'WRITE']
                ]
            ]
        );

        $this->strategy->method('getPermissionsToRemove')->willReturn([
            'uid1' => ['GRANT', 'READ', 'WRITE']
        ]);

        $resource = $this->createMock(core_kernel_classes_Class::class);

        $this->service->savePermissions(
            false,
            $resource,
            [
                'uid1' => ['GRANT', 'READ', 'WRITE']
            ],
            'resourceId'
        );
    }

    public function testDoNotSaveAnythingWhenThereIsNothingToSave(): void
    {
        $this->databaseAccess->expects($this->never())->method('addPermissions');
        $this->databaseAccess->expects($this->never())->method('removePermissions');

        $this->databaseAccess->method('getResourcePermissions')->willReturn([]);

        $this->strategy->method('normalizeRequest')->willReturn([]);

        $resource = $this->createMock(core_kernel_classes_Class::class);

        $this->service->savePermissions(false, $resource, [], 'resourceId');
    }
}
