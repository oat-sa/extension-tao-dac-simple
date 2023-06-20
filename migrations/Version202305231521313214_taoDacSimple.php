<?php

declare(strict_types=1);

namespace oat\taoDacSimple\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\tao\scripts\tools\accessControl\SetRolesAccess;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\tao\scripts\update\OntologyUpdater;
use oat\taoDacSimple\model\DacRoles;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version202305231521313214_taoDacSimple extends AbstractMigration
{
    private const CONFIG = [
        SetRolesAccess::CONFIG_RULES => [
            DacRoles::RESTRICTED_ITEM_AUTHOR => [
                ['ext' => 'taoItems', 'mod' => 'Items'],
                ['ext' => 'taoItems', 'mod' => 'ItemExport']
            ],
            DacRoles::RESTRICTED_TEST_AUTHOR => [
                ['ext' => 'taoTests', 'mod' => 'Tests']
            ]
        ],
    ];

    public function getDescription(): string
    {
        return 'Add and configure limited results manager';
    }

    public function up(Schema $schema): void
    {
        OntologyUpdater::syncModels();
        $setRolesAccess = $this->propagate(new SetRolesAccess());
        $setRolesAccess([
            '--' . SetRolesAccess::OPTION_CONFIG, self::CONFIG,
        ]);
    }

    public function down(Schema $schema): void
    {
        $setRolesAccess = $this->propagate(new SetRolesAccess());
        $setRolesAccess([
            '--' . SetRolesAccess::OPTION_REVOKE,
            '--' . SetRolesAccess::OPTION_CONFIG, self::CONFIG,
        ]);
    }
}
