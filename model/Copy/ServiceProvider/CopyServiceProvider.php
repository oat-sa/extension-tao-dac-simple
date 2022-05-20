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
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA;
 *
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com>
 */

declare(strict_types=1);

namespace oat\taoDacSimple\model\Copy\ServiceProvider;

use oat\tao\model\resources\Service\ClassCopier;
use oat\tao\model\resources\Service\InstanceCopier;
use oat\generis\model\DependencyInjection\ContainerServiceProviderInterface;
use oat\taoDacSimple\model\Copy\Service\DacSimplePermissionCopier;
use oat\taoDacSimple\model\DataBaseAccess;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class CopyServiceProvider implements ContainerServiceProviderInterface
{
    public function __invoke(ContainerConfigurator $configurator): void
    {
        $services = $configurator->services();

        $services
            ->set(DacSimplePermissionCopier::class, DacSimplePermissionCopier::class)
            ->args(
                [
                    service(DataBaseAccess::SERVICE_ID),
                ]
            );

        $services
            ->get(ClassCopier::class . '::ITEMS')
            ->call(
                'withPermissionCopier',
                [
                    service(DacSimplePermissionCopier::class),
                ]
            );

        $services
            ->get(InstanceCopier::class . '::ITEMS')
            ->call(
                'withPermissionCopier',
                [
                    service(DacSimplePermissionCopier::class),
                ]
            );
    }
}
