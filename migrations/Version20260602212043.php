<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260602212043 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tabla de unión teacher_academic_year (ManyToMany Teacher ↔ AcademicYear)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE teacher_academic_year (academic_year_id BINARY(16) NOT NULL, teacher_id BINARY(16) NOT NULL, INDEX IDX_EF1B6955C54F3401 (academic_year_id), INDEX IDX_EF1B695541807E1D (teacher_id), PRIMARY KEY (academic_year_id, teacher_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE teacher_academic_year ADD CONSTRAINT FK_EF1B6955C54F3401 FOREIGN KEY (academic_year_id) REFERENCES academic_year (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teacher_academic_year ADD CONSTRAINT FK_EF1B695541807E1D FOREIGN KEY (teacher_id) REFERENCES teacher (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE teacher_academic_year DROP FOREIGN KEY FK_EF1B6955C54F3401');
        $this->addSql('ALTER TABLE teacher_academic_year DROP FOREIGN KEY FK_EF1B695541807E1D');
        $this->addSql('DROP TABLE teacher_academic_year');
    }
}
