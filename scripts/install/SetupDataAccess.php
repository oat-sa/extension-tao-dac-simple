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

use oat\taoDacSimple\model\DataBaseAccess;
use oat\taoDacSimple\model\PermissionProvider;
use oat\taoDacSimple\model\AdminService;
use oat\oatbox\service\ServiceManager;
use oat\generis\model\data\permission\PermissionInterface;
use oat\oatbox\extension\InstallAction;
use oat\tao\model\user\TaoRoles;

class SetupDataAccess extends InstallAction
{
    public function __invoke($params)
    {
        $persistence = $this->getServiceLocator()->get(\common_persistence_Manager::SERVICE_ID)->getPersistenceById('default');
        
        $schemaManager = $persistence->getDriver()->getSchemaManager();
        $schema = $schemaManager->createSchema();
        $fromSchema = clone $schema;
        $table = $schema->createtable(DataBaseAccess::TABLE_PRIVILEGES_NAME);
        $table->addColumn('user_id',"string",array("notnull" => null,"length" => 255));
        $table->addColumn('resource_id',"string",array("notnull" => null,"length" => 255));
        $table->addColumn('privilege',"string",array("notnull" => null,"length" => 255));
        $table->setPrimaryKey(array("user_id","resource_id","privilege"));
        
        $queries = $persistence->getPlatform()->getMigrateSchemaSql($fromSchema, $schema);
        foreach ($queries as $query){
            $persistence->exec($query);
        }
        
        $impl = new PermissionProvider();
        $this->registerService(PermissionInterface::SERVICE_ID, $impl);
        
        $rights = $impl->getSupportedRights();
        foreach (PermissionProvider::getSupportedRootClasses() as $class) {
            AdminService::addPermissionToClass($class, TaoRoles::BACK_OFFICE, $rights);
        }
        return new \common_report_Report(\common_report_Report::TYPE_SUCCESS, 'Setup SimpleDac');
    }
}