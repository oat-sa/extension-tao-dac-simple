<?php

namespace oat\taoDacSimple\model;

use oat\generis\model\DependencyInjection\ContainerServiceProviderInterface;
use oat\oatbox\event\EventManager;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class PermissionServiceProvider implements ContainerServiceProviderInterface
{
    public function __invoke(ContainerConfigurator $configurator): void
    {
        $services = $configurator->services();

        $services->set(SavePermissionsStrategy::class, SavePermissionsStrategy::class);

        $services
            ->set(ChangePermissionsService::class, ChangePermissionsService::class)
            ->public()
            ->args([
                service(DataBaseAccess::SERVICE_ID),
                service(SavePermissionsStrategy::class),
                service(EventManager::SERVICE_ID),
            ]);
    }
}