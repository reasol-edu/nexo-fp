<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Límites de ajuste: campos min_value y max_value en setting_definition + restricciones de page.size (MySQL / MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql('ALTER TABLE setting_definition ADD min_value INT DEFAULT NULL');
        $this->addSql('ALTER TABLE setting_definition ADD max_value INT DEFAULT NULL');
        $this->addSql("UPDATE setting_definition SET min_value = 5, max_value = 100 WHERE \`key\` = 'page.size'");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql('ALTER TABLE setting_definition DROP COLUMN min_value');
        $this->addSql('ALTER TABLE setting_definition DROP COLUMN max_value');
    }
}
