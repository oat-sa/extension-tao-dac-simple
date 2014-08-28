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

use oat\tao\model\accessControl\data\AclProxy as DataProxy;
use oat\taoDacSimple\model\accessControl\data\implementation\DataBaseAccess;


$schemaManager = common_persistence_Manager::getPersistence('default')->getDriver()->getSchemaManager();
$schema = $schemaManager->createSchema();
$fromSchema = clone $schema;
$table = $schema->createtable(DataBaseAccess::TABLE_PRIVILEGES_NAME);
$table->addColumn('user_id',"string",array("notnull" => null,"length" => 255));
$table->addColumn('resource_id',"string",array("notnull" => null,"length" => 255));
$table->addColumn('privilege',"string",array("notnull" => null,"length" => 255));
$table->addColumn('user_type',"string",array("notnull" => null,"length" => 255));
$table->setPrimaryKey(array("user_id","resource_id","privilege"));

$generis = common_ext_ExtensionsManager::singleton()->getExtensionById('generis');
if ($generis->hasConfig('persistences')) {
    $configs = $generis->getConfig('persistences');
    $connection = \Doctrine\DBAL\DriverManager::getConnection($configs['default'], new Doctrine\DBAL\Configuration());
    $platform = $connection->getDatabasePlatform();
    $comparator = new \Doctrine\DBAL\Schema\Comparator();
    $schemaDiff = $comparator->compare($fromSchema, $schema);
    $queries = $schemaDiff->toSql($platform); // queries to get from one to another schema.
    foreach ($queries as $query){
        $connection->executeUpdate($query);
    }
}

$dataImpl = new DataBaseAccess();
DataProxy::setImplementation($dataImpl);