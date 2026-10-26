<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Código de centro (8 dígitos) de la empresa, para distinguir centros educativos con el mismo CIF (MySQL / MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql("ALTER TABLE company ADD centre_code VARCHAR(8) DEFAULT '' NOT NULL");
        $this->addSql('DROP INDEX UNIQ_uq_company_vat_centre ON company');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_uq_company_vat_code_centre ON company (vat_number, centre_code, educational_centre_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql('DROP INDEX UNIQ_uq_company_vat_code_centre ON company');
        $this->addSql("UPDATE company SET vat_number = CONCAT(SUBSTRING(vat_number, 1, 41), '-', centre_code) WHERE centre_code <> ''");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_uq_company_vat_centre ON company (vat_number, educational_centre_id)');
        $this->addSql('ALTER TABLE company DROP centre_code');
    }
}
