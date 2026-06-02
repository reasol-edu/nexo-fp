<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260602003135 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE company SET vat_number = CONCAT('PENDIENTE-', LOWER(HEX(id))) WHERE vat_number IS NULL");
        $this->addSql('ALTER TABLE company CHANGE vat_number vat_number VARCHAR(50) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uq_company_vat_centre ON company (vat_number, educational_centre_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uq_company_vat_centre ON company');
        $this->addSql('ALTER TABLE company CHANGE vat_number vat_number VARCHAR(50) DEFAULT NULL');
    }
}
