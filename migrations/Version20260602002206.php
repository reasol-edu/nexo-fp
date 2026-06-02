<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260602002206 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make workcenter.city NOT NULL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE workcenter SET city = '' WHERE city IS NULL");
        $this->addSql('ALTER TABLE workcenter CHANGE city city VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE workcenter CHANGE city city VARCHAR(255) DEFAULT NULL');
    }
}
