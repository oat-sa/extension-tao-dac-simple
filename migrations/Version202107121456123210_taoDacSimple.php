<?php

declare(strict_types=1);

namespace oat\taoDacSimple\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\oatbox\event\EventManager;
use oat\tao\model\event\ResourceMovedEvent;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoDacSimple\model\eventHandler\ResourceUpdateHandler;

final class Version202107121456123210_taoDacSimple extends AbstractMigration
{

    public function getDescription(): string
    {
        return 'Attach ResourceMovedEvent handler';
    }

    public function up(Schema $schema): void
    {
        /** @var EventManager $eventManager */
        $eventManager = $this->getServiceLocator()->get(EventManager::SERVICE_ID);
        $eventManager->attach(
            ResourceMovedEvent::class,
            [
                ResourceUpdateHandler::class,
                'catchResourceUpdated'
            ]

        );
        $this->getServiceManager()->register(EventManager::SERVICE_ID, $eventManager);
    }

    public function down(Schema $schema): void
    {
        /** @var EventManager $eventManager */
        $eventManager = $this->getServiceLocator()->get(EventManager::SERVICE_ID);
        $eventManager->detach(
            ResourceMovedEvent::class,
            [
                ResourceUpdateHandler::class,
                'catchResourceUpdated'
            ]

        );
        $this->getServiceManager()->register(EventManager::SERVICE_ID, $eventManager);
    }
}
