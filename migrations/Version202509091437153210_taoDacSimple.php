<?php

declare(strict_types=1);

namespace oat\taoDacSimple\migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoDacSimple\scripts\install\RegisterAclRoleProviderService;

/**
 * Auto-generated Migration: Please modify to your needs!
 *
 * phpcs:disable Squiz.Classes.ValidClassName
 */
final class Version202509091437153210_taoDacSimple extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registering DefaultAclRoleProvider as AclRoleProvider service';
    }

    public function up(Schema $schema): void
    {
        $this->runAction(new RegisterAclRoleProviderService());
    }

    public function down(Schema $schema): void
    {
        throw new IrreversibleMigration('Cannot unregister the AclRoleProvider service automatically.');
    }
}
