<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Límites de ajuste: campos min_value y max_value en setting_definition + restricciones de page.size (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('ALTER TABLE setting_definition ADD min_value INT DEFAULT NULL');
        $this->addSql('ALTER TABLE setting_definition ADD max_value INT DEFAULT NULL');
        $this->addSql("UPDATE setting_definition SET min_value = 5, max_value = 100 WHERE key = 'page.size'");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('ALTER TABLE setting_definition DROP min_value');
        $this->addSql('ALTER TABLE setting_definition DROP max_value');
    }
}
