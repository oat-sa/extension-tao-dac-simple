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

namespace oat\taoDacSimple\test\unit\model\Command;

use oat\taoDacSimple\model\Command\ChangeAccessCommand;
use oat\taoDacSimple\model\PermissionProvider;
use PHPUnit\Framework\TestCase;

class ChangeAccessCommandTest extends TestCase
{
    public ChangeAccessCommand $sut;

    public function setUp(): void
    {
        $this->sut = new ChangeAccessCommand();
    }

    public function testGrantPermissions(): void
    {
        $this->sut->grantResourceForUser('r1', PermissionProvider::PERMISSION_READ, 'u1');
        $this->sut->grantResourceForUser('r1', PermissionProvider::PERMISSION_WRITE, 'u1');
        $this->sut->grantResourceForUser('r1', PermissionProvider::PERMISSION_GRANT, 'u1');

        $this->sut->grantResourceForUser('r2', PermissionProvider::PERMISSION_READ, 'u1');
        $this->sut->grantResourceForUser('r2', PermissionProvider::PERMISSION_WRITE, 'u1');
        $this->sut->grantResourceForUser('r2', PermissionProvider::PERMISSION_GRANT, 'u1');

        $this->sut->grantResourceForUser('r2', PermissionProvider::PERMISSION_READ, 'u2');
        $this->sut->grantResourceForUser('r2', PermissionProvider::PERMISSION_WRITE, 'u2');
        $this->sut->grantResourceForUser('r2', PermissionProvider::PERMISSION_GRANT, 'u2');

        $this->assertSame(['r1', 'r2'], $this->sut->getResourceIdsToGrant());

        $this->assertSame(['u1'], $this->sut->getUserIdsToGrant('r1', PermissionProvider::PERMISSION_READ));
        $this->assertSame(['u1'], $this->sut->getUserIdsToGrant('r1', PermissionProvider::PERMISSION_WRITE));
        $this->assertSame(['u1'], $this->sut->getUserIdsToGrant('r1', PermissionProvider::PERMISSION_GRANT));

        $this->assertSame(['u1', 'u2'], $this->sut->getUserIdsToGrant('r2', PermissionProvider::PERMISSION_READ));
        $this->assertSame(['u1', 'u2'], $this->sut->getUserIdsToGrant('r2', PermissionProvider::PERMISSION_WRITE));
        $this->assertSame(['u1', 'u2'], $this->sut->getUserIdsToGrant('r2', PermissionProvider::PERMISSION_GRANT));
    }

    public function testRevokePermissions(): void
    {
        $this->sut->revokeResourceForUser('r1', PermissionProvider::PERMISSION_READ, 'u1');
        $this->sut->revokeResourceForUser('r1', PermissionProvider::PERMISSION_WRITE, 'u1');
        $this->sut->revokeResourceForUser('r1', PermissionProvider::PERMISSION_GRANT, 'u1');

        $this->sut->revokeResourceForUser('r2', PermissionProvider::PERMISSION_READ, 'u1');
        $this->sut->revokeResourceForUser('r2', PermissionProvider::PERMISSION_WRITE, 'u1');
        $this->sut->revokeResourceForUser('r2', PermissionProvider::PERMISSION_GRANT, 'u1');

        $this->sut->revokeResourceForUser('r2', PermissionProvider::PERMISSION_READ, 'u2');
        $this->sut->revokeResourceForUser('r2', PermissionProvider::PERMISSION_WRITE, 'u2');
        $this->sut->revokeResourceForUser('r2', PermissionProvider::PERMISSION_GRANT, 'u2');

        $this->sut->removeRevokeResourceForUser('r2', PermissionProvider::PERMISSION_GRANT, 'u2');

        $this->assertSame(['r1', 'r2'], $this->sut->getResourceIdsToRevoke());
        $this->assertSame(['u1', 'u2'], $this->sut->getUserIdsToRevokePermissions());

        $this->assertSame(
            ['r1', 'r2'],
            $this->sut->getResourceIdsByUserAndPermissionToRevoke('u1', PermissionProvider::PERMISSION_READ)
        );
        $this->assertSame(
            ['r1', 'r2'],
            $this->sut->getResourceIdsByUserAndPermissionToRevoke('u1', PermissionProvider::PERMISSION_WRITE)
        );
        $this->assertSame(
            ['r1', 'r2'],
            $this->sut->getResourceIdsByUserAndPermissionToRevoke('u1', PermissionProvider::PERMISSION_GRANT)
        );

        $this->assertSame(
            ['r2'],
            $this->sut->getResourceIdsByUserAndPermissionToRevoke('u2', PermissionProvider::PERMISSION_READ)
        );
        $this->assertSame(
            ['r2'],
            $this->sut->getResourceIdsByUserAndPermissionToRevoke('u2', PermissionProvider::PERMISSION_WRITE)
        );
        $this->assertSame(
            [],
            $this->sut->getResourceIdsByUserAndPermissionToRevoke('u2', PermissionProvider::PERMISSION_GRANT)
        );

        $this->assertSame(
            [
                PermissionProvider::PERMISSION_READ,
                PermissionProvider::PERMISSION_WRITE,
                PermissionProvider::PERMISSION_GRANT
            ],
            $this->sut->getUserPermissionsToRevoke('u1')
        );
        $this->assertSame(
            [
                PermissionProvider::PERMISSION_READ,
                PermissionProvider::PERMISSION_WRITE,
                PermissionProvider::PERMISSION_GRANT
            ],
            $this->sut->getUserPermissionsToRevoke('u2')
        );

        $this->assertSame(['u1'], $this->sut->getUserIdsToRevoke('r1', PermissionProvider::PERMISSION_READ));
        $this->assertSame(['u1'], $this->sut->getUserIdsToRevoke('r1', PermissionProvider::PERMISSION_WRITE));
        $this->assertSame(['u1'], $this->sut->getUserIdsToRevoke('r1', PermissionProvider::PERMISSION_GRANT));
        $this->assertSame(['u1', 'u2'], $this->sut->getUserIdsToRevoke('r2', PermissionProvider::PERMISSION_READ));
        $this->assertSame(['u1', 'u2'], $this->sut->getUserIdsToRevoke('r2', PermissionProvider::PERMISSION_WRITE));
        $this->assertSame(['u1'], $this->sut->getUserIdsToRevoke('r2', PermissionProvider::PERMISSION_GRANT));
    }
}
