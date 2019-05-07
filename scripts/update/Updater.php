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
 * Copyright (c) 2014 (original work) Open Assessment Technologies SA;
 *
 *
 */

namespace oat\taoDacSimple\scripts\update;

use oat\generis\model\data\permission\implementation\FreeAccess;
use oat\generis\model\data\permission\implementation\IntersectionUnionSupported;
use oat\generis\model\data\permission\implementation\NoAccess;
use oat\taoDacSimple\model\DataBaseAccess;
use oat\generis\model\GenerisRdf;
use oat\tao\model\TaoOntology;
use oat\taoDacSimple\model\PermissionProvider;
use oat\taoDacSimple\model\AdminService;
use oat\taoBackOffice\model\menuStructure\ClassActionRegistry;
use oat\generis\model\data\permission\PermissionInterface;
use oat\taoDacSimple\model\action\AdminAction;
use oat\tao\scripts\update\OntologyUpdater;

/**
 *
 * @author Joel Bout <joel@taotesting.com>
 */
class Updater extends \common_ext_ExtensionUpdater {

    /**
     *
     * @param string $currentVersion
     * @return string $versionUpdatedTo
     */
    public function update($initialVersion) {


        if ($this->isVersion('1.0')) {
            $impl = new PermissionProvider();

            // add read access to Items
            $class = new \core_kernel_classes_Class(TaoOntology::ITEM_CLASS_URI);
            AdminService::addPermissionToClass($class, TaoOntology::PROPERTY_INSTANCE_ROLE_BACKOFFICE, array('READ'));

            // add backoffice user rights to Tests
            $class = new \core_kernel_classes_Class(TaoOntology::TEST_CLASS_URI);
            AdminService::addPermissionToClass($class, TaoOntology::PROPERTY_INSTANCE_ROLE_BACKOFFICE, $impl->getSupportedRights());

            $this->setVersion('1.0.1');
        }
        if ($this->isVersion('1.0.1')) {
            $this->setVersion('1.0.2');
        }
        if ($this->isVersion( '1.0.2')) {
            $taoClass = new \core_kernel_classes_Class(TaoOntology::OBJECT_CLASS_URI);
            $classAdmin = new AdminAction();
            ClassActionRegistry::getRegistry()->registerAction($taoClass, $classAdmin);

            $this->setVersion('1.1');
        }
        if ($this->isVersion('1.1')) {
            $classesToAdd = array(
                new \core_kernel_classes_Class(GenerisRdf::CLASS_GENERIS_USER),
                new \core_kernel_classes_Class(GenerisRdf::CLASS_ROLE)
            );

            // add admin to new instances
            $classAdmin = new AdminAction();
            foreach ($classesToAdd as $class) {
                ClassActionRegistry::getRegistry()->registerAction($class, $classAdmin);
            }

            // add base permissions to new classes
            $taoClass = new \core_kernel_classes_Class(TaoOntology::OBJECT_CLASS_URI);
            foreach ($taoClass->getSubClasses(false) as $class) {
                if (!in_array($class->getUri(), array(TaoOntology::ITEM_CLASS_URI,TaoOntology::TEST_CLASS_URI))) {
                    $classesToAdd[] = $class;
                }
            }
            $rights = $this->getServiceManager()->get(PermissionInterface::SERVICE_ID)->getSupportedRights();
            foreach ($classesToAdd as $class) {
                if (count(AdminService::getUsersPermissions($class->getUri())) == 0) {
                    AdminService::addPermissionToClass($class, TaoOntology::PROPERTY_INSTANCE_ROLE_BACKOFFICE, $rights);
                } else {
                    \common_Logger::w('Unexpected rights present for '.$class->getUri());
                }
            }
            $this->setVersion('1.2.0');
        }

        $this->skip('1.2.0', '2.0.3');


        if ($this->isVersion( '2.0.3')) {

            $dataAccess = new DataBaseAccess([
                DataBaseAccess::OPTION_PERSISTENCE =>'default'
            ]);

            $this->getServiceManager()->register(DataBaseAccess::SERVICE_ID, $dataAccess);

            $this->setVersion('2.1.0');
        }

        if ($this->isVersion( '2.1.0')) {

            $currentService = $this->getServiceManager()->get(PermissionProvider::SERVICE_ID);
            if(!$currentService instanceof PermissionProvider && !$currentService instanceof FreeAccess && !$currentService instanceof NoAccess){
                if($currentService instanceof IntersectionUnionSupported){
                    $toRegister = $currentService->add(new PermissionProvider());
                } else {
                    $toRegister = new IntersectionUnionSupported(['inner' => [$currentService, new PermissionProvider()]]);
                }
                $this->getServiceManager()->register(PermissionInterface::SERVICE_ID, $toRegister);
            }

            $this->setVersion('2.2.0');
        }

        $this->skip('2.2.0', '2.6.0');

        if ($this->isVersion('2.6.0')) {
            OntologyUpdater::syncModels();
            $this->setVersion('2.7.0');
        }

        $this->skip('2.7.0', '3.2.3');
    }
}
