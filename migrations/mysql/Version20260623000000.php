<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade el representante de la empresa (nombre, apellidos, DNI y cargo) a company (MySQL/MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL/MariaDB.'
        );

        $this->addSql('ALTER TABLE company
            ADD representative_first_name VARCHAR(255) DEFAULT NULL,
            ADD representative_last_name VARCHAR(255) DEFAULT NULL,
            ADD representative_national_id VARCHAR(20) DEFAULT NULL,
            ADD representative_role VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL/MariaDB.'
        );

        $this->addSql('ALTER TABLE company
            DROP representative_first_name,
            DROP representative_last_name,
            DROP representative_national_id,
            DROP representative_role');
    }
}
