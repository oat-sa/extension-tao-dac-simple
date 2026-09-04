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
 * Copyright (c) 2023-2026 (original work) Open Assessment Technologies SA.
 *
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com>
 */

declare(strict_types=1);

namespace oat\taoDacSimple\model\ServiceProvider;

use oat\generis\model\data\Ontology;
use oat\generis\model\DependencyInjection\ContainerServiceProviderInterface;
use oat\tao\model\user\MentionEligibleUsersProviderInterface;
use oat\taoDacSimple\model\ChangePermissionsService;
use oat\taoDacSimple\model\Comment\DacMentionEligibleUsersProvider;
use oat\taoDacSimple\model\PermissionsServiceFactory;
use oat\taoDacSimple\model\RolePrivilegeRetriever;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use tao_models_classes_UserService;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class PermissionsServiceProvider implements ContainerServiceProviderInterface
{
    public function __invoke(ContainerConfigurator $configurator): void
    {
        $services = $configurator->services();

        $services->set(ChangePermissionsService::class)
            ->public()
            ->factory(
                [
                    service(PermissionsServiceFactory::SERVICE_ID),
                    'create'
                ]
            );

        $services
            ->set(RolePrivilegeRetriever::class, RolePrivilegeRetriever::class)
            ->public();

        $services
            ->set(DacMentionEligibleUsersProvider::class, DacMentionEligibleUsersProvider::class)
            ->public()
            ->args([
                service(RolePrivilegeRetriever::class),
                service(Ontology::SERVICE_ID),
                service(tao_models_classes_UserService::SERVICE_ID),
            ]);

        $services
            ->alias(MentionEligibleUsersProviderInterface::class, DacMentionEligibleUsersProvider::class)
            ->public();
    }
}
