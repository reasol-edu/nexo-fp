<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade el representante de la empresa (nombre, apellidos, DNI y cargo) a company (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('ALTER TABLE company ADD COLUMN representative_first_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE company ADD COLUMN representative_last_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE company ADD COLUMN representative_national_id VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE company ADD COLUMN representative_role VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('ALTER TABLE company DROP COLUMN representative_first_name');
        $this->addSql('ALTER TABLE company DROP COLUMN representative_last_name');
        $this->addSql('ALTER TABLE company DROP COLUMN representative_national_id');
        $this->addSql('ALTER TABLE company DROP COLUMN representative_role');
    }
}
