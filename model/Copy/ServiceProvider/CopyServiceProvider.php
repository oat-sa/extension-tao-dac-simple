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
 * Copyright (c) 2022-2023 (original work) Open Assessment Technologies SA.
 *
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com>
 */

declare(strict_types=1);

namespace oat\taoDacSimple\model\Copy\ServiceProvider;

use oat\taoDacSimple\model\DataBaseAccess;
use oat\tao\model\clientConfig\ClientConfigStorage;
use oat\tao\model\resources\Command\ResourceTransferCommand;
use oat\taoDacSimple\model\Copy\Service\DacSimplePermissionCopier;
use oat\generis\model\DependencyInjection\ContainerServiceProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class CopyServiceProvider implements ContainerServiceProviderInterface
{
    public function __invoke(ContainerConfigurator $configurator): void
    {
        $services = $configurator->services();
        $parameters = $configurator->parameters();

        $services
            ->set(DacSimplePermissionCopier::class, DacSimplePermissionCopier::class)
            ->args(
                [
                    service(DataBaseAccess::SERVICE_ID),
                ]
            )
            ->tag('tao.copier.permissions');

        $parameters->set('ACL_TRANSFER_MODE', ResourceTransferCommand::ACL_KEEP_ORIGINAL);

        $services
            ->get(ClientConfigStorage::class)
            ->call(
                'setConfigByPath',
                [
                    [
                        'libConfigs' => [
                            'provider/resources' => [
                                'aclTransferMode' => env('ACL_TRANSFER_MODE')
                                    ->default('ACL_TRANSFER_MODE')
                                    ->string(),
                            ],
                        ],
                    ],
                ]
            );
    }
}
