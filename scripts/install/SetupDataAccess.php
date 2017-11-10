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
namespace oat\taoDacSimple\scripts\install;

use oat\generis\model\data\permission\implementation\FreeAccess;
use oat\generis\model\data\permission\implementation\IntersectionUnionSupported;
use oat\generis\model\data\permission\implementation\NoAccess;
use oat\taoDacSimple\model\DataBaseAccess;
use oat\taoDacSimple\model\PermissionProvider;
use oat\taoDacSimple\model\AdminService;
use oat\generis\model\data\permission\PermissionInterface;
use oat\oatbox\extension\InstallAction;
use oat\tao\model\user\TaoRoles;

class SetupDataAccess extends InstallAction
{
    public function __invoke($params)
    {
        /** @var DataBaseAccess $databaseAccess */
        $databaseAccess = $this->getServiceLocator()->get(DataBaseAccess::SERVICE_ID);
        
        $databaseAccess->createTables();
        
        $impl = new PermissionProvider();
        $toRegister = $impl;
        $rights = $impl->getSupportedRights();
        foreach (PermissionProvider::getSupportedRootClasses() as $class) {
            AdminService::addPermissionToClass($class, TaoRoles::BACK_OFFICE, $rights);
        }

        $currentService = $this->getServiceManager()->get(PermissionProvider::SERVICE_ID);
        if(!$currentService instanceof FreeAccess && !$currentService instanceof NoAccess){
            if($currentService instanceof IntersectionUnionSupported){
                $toRegister = $currentService->add($impl);
            } else {
                $toRegister = new IntersectionUnionSupported(['inner' => [$currentService, $impl]]);
            }
        }

        $this->registerService(PermissionInterface::SERVICE_ID, $toRegister);
        

        return new \common_report_Report(\common_report_Report::TYPE_SUCCESS, 'Setup SimpleDac');
    }
}