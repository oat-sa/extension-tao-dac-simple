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
 * Copyright (c) 2017 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 *
 */
namespace oat\taoDacSimple\scripts\tools;

use oat\oatbox\extension\AbstractAction;
use oat\taoDacSimple\model\DataBaseAccess;
use oat\taoDacSimple\model\PermissionProvider;
use oat\tao\model\user\TaoRoles;


/**
 * Class UpdateDataAccess
 * usage
 * ```
 * sudo -u www-data php index.php 'oat\taoDacSimple\scripts\tools\UpdateDataAccess'
 * ```
 *
 * @package oat\taoDacSimple\scripts\tools
 */
class UpdateDataAccess extends AbstractAction
{
    public function __invoke($params)
    {

        $impl = new PermissionProvider();
        $rights = $impl->getSupportedRights();

        $updated = 0;

        /** @var \core_kernel_classes_Class $class */
        foreach (PermissionProvider::getSupportedRootClasses() as $class) {
            $updated += $this->addPermissionsToClass($class, $rights);
        }

        return new \common_report_Report(\common_report_Report::TYPE_SUCCESS, __('%s classes and instances have been updated', $updated));
    }


    private function addPermissionsToClass($class, $rights)
    {
        $updated = 0;
        /** @var DataBaseAccess $databaseAccess */
        $databaseAccess = $this->getServiceLocator()->get(DataBaseAccess::SERVICE_ID);

        if(empty($databaseAccess->getResourcePermissions($class->getUri()))){
            $databaseAccess->addPermissions(TaoRoles::BACK_OFFICE, $class->getUri(), $rights);
            $updated++;
        }
        foreach ($class->getInstances(false) as $instance) {
            if(empty($databaseAccess->getResourcePermissions($instance->getUri()))){
                $databaseAccess->addPermissions(TaoRoles::BACK_OFFICE, $instance->getUri(), $rights);
                $updated++;
            }
        }

        foreach ($class->getSubClasses(false) as $subclass) {
            $updated += $this->addPermissionsToClass($subclass, $rights);
        }

        return $updated;
    }
}