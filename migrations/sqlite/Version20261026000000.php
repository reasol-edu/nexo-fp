<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Código de centro (8 dígitos) de la empresa, para distinguir centros educativos con el mismo CIF (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql("ALTER TABLE company ADD COLUMN centre_code VARCHAR(8) DEFAULT '' NOT NULL");
        $this->addSql('DROP INDEX UNIQ_uq_company_vat_centre');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_uq_company_vat_code_centre ON company (vat_number, centre_code, educational_centre_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        // Con códigos de centro distintos puede haber varias empresas con el mismo CIF: al quitar la columna, el código
        // pasa a formar parte del CIF («Q1234567A-41000001») para que sigan siendo únicas y no se pierda ninguna.
        $this->addSql('DROP INDEX UNIQ_uq_company_vat_code_centre');
        $this->addSql("UPDATE company SET vat_number = substr(vat_number, 1, 41) || '-' || centre_code WHERE centre_code <> ''");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_uq_company_vat_centre ON company (vat_number, educational_centre_id)');
        $this->addSql('ALTER TABLE company DROP COLUMN centre_code');
    }
}
