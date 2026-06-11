<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bloqueo de ajustes: campo locked en global_setting_value y centre_setting_value (MySQL / MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql('ALTER TABLE global_setting_value ADD locked TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE centre_setting_value ADD locked TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql('ALTER TABLE global_setting_value DROP COLUMN locked');
        $this->addSql('ALTER TABLE centre_setting_value DROP COLUMN locked');
    }
}
