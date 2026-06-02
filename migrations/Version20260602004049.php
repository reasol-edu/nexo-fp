<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260602004049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE company_workers (company_id BINARY(16) NOT NULL, worker_id BINARY(16) NOT NULL, INDEX IDX_987F6717979B1AD6 (company_id), INDEX IDX_987F67176B20BA36 (worker_id), PRIMARY KEY (company_id, worker_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE company_workers ADD CONSTRAINT FK_987F6717979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_workers ADD CONSTRAINT FK_987F67176B20BA36 FOREIGN KEY (worker_id) REFERENCES worker (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workcenter_worker DROP FOREIGN KEY `FK_AAF9960C6B20BA36`');
        $this->addSql('ALTER TABLE workcenter_worker DROP FOREIGN KEY `FK_AAF9960CA2473C4B`');
        $this->addSql('DROP TABLE workcenter_worker');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE workcenter_worker (workcenter_id BINARY(16) NOT NULL, worker_id BINARY(16) NOT NULL, INDEX IDX_AAF9960C6B20BA36 (worker_id), INDEX IDX_AAF9960CA2473C4B (workcenter_id), PRIMARY KEY (workcenter_id, worker_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE workcenter_worker ADD CONSTRAINT `FK_AAF9960C6B20BA36` FOREIGN KEY (worker_id) REFERENCES worker (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workcenter_worker ADD CONSTRAINT `FK_AAF9960CA2473C4B` FOREIGN KEY (workcenter_id) REFERENCES workcenter (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_workers DROP FOREIGN KEY FK_987F6717979B1AD6');
        $this->addSql('ALTER TABLE company_workers DROP FOREIGN KEY FK_987F67176B20BA36');
        $this->addSql('DROP TABLE company_workers');
    }
}
