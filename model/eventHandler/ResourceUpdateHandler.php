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

namespace oat\taoDacSimple\model\eventHandler;

use Laminas\ServiceManager\ServiceLocatorAwareTrait;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\event\ResourceMovedEvent;
use oat\taoDacSimple\model\PermissionsService;
use oat\taoDacSimple\model\PermissionsServiceFactory;
use oat\taoDacSimple\model\RolePrivilegeRetriever;

class ResourceUpdateHandler extends ConfigurableService
{
    use ServiceLocatorAwareTrait;
    use OntologyAwareTrait;

    public function catchResourceUpdated(ResourceMovedEvent $event): void
    {
        $movedResource = $event->getMovedResource();

        $rolePrivilegeList = $this->getRolePrivilegeRetriever()->retrieveByResourceIds([
            $event->getDestinationClass()->getUri(),
            $movedResource->getUri()
        ]);

        if ($movedResource->isClass()) {
            foreach ($movedResource->getInstances(true) as $item) {
                $itemUri = $item->getUri();
                $itemPrivilegesMap[$itemUri] =  $this->getRolePrivilegeRetriever()->retrieveByResourceIds([$itemUri]);
            }
        }

        $this->getPermissionService()->saveResourcePermissionsRecursive(
            $movedResource,
            $rolePrivilegeList
        );

        if (isset($itemPrivilegesMap)) {
            foreach ($itemPrivilegesMap as $uri => $itemPrivilege) {
                $this->getPermissionService()
                    ->saveResourcePermissionsRecursive(
                        $this->getResource($uri), $itemPrivilege
                    );
            }
        }
    }

    private function getRolePrivilegeRetriever(): RolePrivilegeRetriever
    {
        return $this->getServiceLocator()->get(RolePrivilegeRetriever::class);
    }

    private function getPermissionService(): PermissionsService
    {
        return $this->getServiceLocator()->get(PermissionsServiceFactory::class)->create();
    }
}
