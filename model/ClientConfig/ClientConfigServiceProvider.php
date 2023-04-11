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
 * Copyright (c) 2023 (original work) Open Assessment Technologies SA.
 *
 * @author Andrei Shapiro <andrei.shapiro@taotesting.com>
 */

declare(strict_types=1);

namespace oat\taoDacSimple\model\ClientConfig;

use oat\tao\model\resources\Command\ResourceTransferCommand;
use oat\generis\model\DependencyInjection\ContainerServiceProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use oat\taoDacSimple\model\ClientConfig\Handler\AclTransferModeClientLibConfigHandler;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

class ClientConfigServiceProvider implements ContainerServiceProviderInterface
{
    public function __invoke(ContainerConfigurator $configurator): void
    {
        $services = $configurator->services();
        $parameters = $configurator->parameters();

        $parameters->set('ACL_TRANSFER_MODE', ResourceTransferCommand::ACL_KEEP_ORIGINAL);

        $services
            ->set(AclTransferModeClientLibConfigHandler::class, AclTransferModeClientLibConfigHandler::class)
            ->args([
                env('ACL_TRANSFER_MODE')->default('ACL_TRANSFER_MODE')->string(),
            ])
            ->tag('tao.client_lib_config.handler');
    }
}
