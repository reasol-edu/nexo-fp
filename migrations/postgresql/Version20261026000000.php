<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Código de centro (8 dígitos) de la empresa, para distinguir centros educativos con el mismo CIF (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql("ALTER TABLE company ADD centre_code VARCHAR(8) DEFAULT '' NOT NULL");
        $this->addSql('DROP INDEX UNIQ_uq_company_vat_centre');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_uq_company_vat_code_centre ON company (vat_number, centre_code, educational_centre_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('DROP INDEX UNIQ_uq_company_vat_code_centre');
        $this->addSql("UPDATE company SET vat_number = substr(vat_number, 1, 41) || '-' || centre_code WHERE centre_code <> ''");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_uq_company_vat_centre ON company (vat_number, educational_centre_id)');
        $this->addSql('ALTER TABLE company DROP centre_code');
    }
}
