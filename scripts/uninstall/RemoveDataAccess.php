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
 * Copyright (c) 2013 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 *
 */
namespace oat\taoDacSimple\scripts\uninstall;

use oat\taoDacSimple\model\DataBaseAccess;
use oat\generis\model\data\permission\PermissionInterface;
use oat\taoDacSimple\model\AdminService;
use oat\generis\model\data\permission\implementation\FreeAccess;
use oat\oatbox\extension\UninstallAction;
use oat\taoBackOffice\model\menuStructure\ClassActionRegistry;
use oat\taoDacSimple\model\PermissionProvider;
use oat\taoDacSimple\model\action\AdminAction;

class RemoveDataAccess extends UninstallAction
{
    public function __invoke($params)
    {
        $classAdmin = new AdminAction();
        foreach (PermissionProvider::getSupportedRootClasses() as $class) {
            ClassActionRegistry::getRegistry()->unregisterAction($class, $classAdmin);
        }

        try {
            /** @var DataBaseAccess $databaseAccess */
            $databaseAccess = $this->getServiceManager()->get(DataBaseAccess::SERVICE_ID);
            $databaseAccess->removeTables();

            $this->getServiceManager()->register(PermissionInterface::SERVICE_ID, new FreeAccess());

        } catch (\Exception $e) {
            return \common_report_Report::createFailure(__("something went wrong during taoDacSimple uninstallation\n".$e->getMessage()));
        }

        return \common_report_Report::createSuccess(__('taoDacSimple extension correctly uninstalled'));
    }
}
