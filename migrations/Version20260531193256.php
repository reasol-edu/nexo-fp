<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260531193256 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE academic_year (id BINARY(16) NOT NULL, name VARCHAR(50) NOT NULL, educational_centre_id BINARY(16) NOT NULL, INDEX IDX_275AE72161F9EE23 (educational_centre_id), UNIQUE INDEX uq_academic_year_centre (name, educational_centre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE comment (id BINARY(16) NOT NULL, content LONGTEXT NOT NULL, posted_at DATETIME NOT NULL, author_id BINARY(16) NOT NULL, company_id BINARY(16) NOT NULL, INDEX IDX_9474526CF675F31B (author_id), INDEX IDX_9474526C979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE company (id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, vat_number VARCHAR(50) DEFAULT NULL, city VARCHAR(255) NOT NULL, exceptional_circumstances LONGTEXT DEFAULT NULL, educational_centre_id BINARY(16) NOT NULL, INDEX IDX_4FBF094F61F9EE23 (educational_centre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE company_liaisons (company_id BINARY(16) NOT NULL, teacher_id BINARY(16) NOT NULL, INDEX IDX_BB265AC5979B1AD6 (company_id), INDEX IDX_BB265AC541807E1D (teacher_id), PRIMARY KEY (company_id, teacher_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE company_audit (id BINARY(16) NOT NULL, changed_at DATETIME NOT NULL, changes JSON NOT NULL, company_id BINARY(16) NOT NULL, changed_by_id BINARY(16) DEFAULT NULL, INDEX IDX_D5E95D1C979B1AD6 (company_id), INDEX IDX_D5E95D1C828AD0A0 (changed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE educational_centre (id BINARY(16) NOT NULL, code VARCHAR(8) NOT NULL, name VARCHAR(255) NOT NULL, city VARCHAR(255) NOT NULL, active_academic_year_id BINARY(16) DEFAULT NULL, UNIQUE INDEX UNIQ_2E7EDDDC77153098 (code), INDEX IDX_2E7EDDDC3B9B1771 (active_academic_year_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE educational_centre_admins (educational_centre_id BINARY(16) NOT NULL, teacher_id BINARY(16) NOT NULL, INDEX IDX_9F1F12EF61F9EE23 (educational_centre_id), INDEX IDX_9F1F12EF41807E1D (teacher_id), PRIMARY KEY (educational_centre_id, teacher_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE `group` (id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, details LONGTEXT DEFAULT NULL, programme_year_id BINARY(16) NOT NULL, tutor_id BINARY(16) DEFAULT NULL, INDEX IDX_6DC044C59B62B32C (programme_year_id), INDEX IDX_6DC044C5208F64F1 (tutor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE group_teacher (group_id BINARY(16) NOT NULL, teacher_id BINARY(16) NOT NULL, INDEX IDX_36F6F2D9FE54D947 (group_id), INDEX IDX_36F6F2D941807E1D (teacher_id), PRIMARY KEY (group_id, teacher_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE professional_family (id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, academic_year_id BINARY(16) NOT NULL, head_id BINARY(16) DEFAULT NULL, INDEX IDX_E5585387C54F3401 (academic_year_id), INDEX IDX_E5585387F41A619E (head_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE programme (id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, details LONGTEXT DEFAULT NULL, academic_year_id BINARY(16) NOT NULL, professional_family_id BINARY(16) NOT NULL, INDEX IDX_3DDCB9FFC54F3401 (academic_year_id), INDEX IDX_3DDCB9FF761D1F81 (professional_family_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE programme_coordinator (programme_id BINARY(16) NOT NULL, teacher_id BINARY(16) NOT NULL, INDEX IDX_C3CFC5B562BB7AEE (programme_id), INDEX IDX_C3CFC5B541807E1D (teacher_id), PRIMARY KEY (programme_id, teacher_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE programme_year (id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, details LONGTEXT DEFAULT NULL, programme_id BINARY(16) NOT NULL, INDEX IDX_A2C2415A62BB7AEE (programme_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE stay (id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, academic_year_id BINARY(16) NOT NULL, programme_id BINARY(16) NOT NULL, INDEX IDX_5E09839CC54F3401 (academic_year_id), INDEX IDX_5E09839C62BB7AEE (programme_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE stay_students (stay_id BINARY(16) NOT NULL, student_id BINARY(16) NOT NULL, INDEX IDX_D8A0D795FB3AF7D6 (stay_id), INDEX IDX_D8A0D795CB944F1A (student_id), PRIMARY KEY (stay_id, student_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE student (id BINARY(16) NOT NULL, student_id VARCHAR(50) NOT NULL, details VARCHAR(255) DEFAULT NULL, name_first_name VARCHAR(255) NOT NULL, name_last_name VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE student_groups (student_id BINARY(16) NOT NULL, group_id BINARY(16) NOT NULL, INDEX IDX_7E5BE1F0CB944F1A (student_id), INDEX IDX_7E5BE1F0FE54D947 (group_id), PRIMARY KEY (student_id, group_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teacher (id BINARY(16) NOT NULL, username VARCHAR(180) NOT NULL, `admin` TINYINT NOT NULL, password VARCHAR(255) DEFAULT NULL, external TINYINT NOT NULL, active TINYINT NOT NULL, email VARCHAR(180) DEFAULT NULL, name_first_name VARCHAR(255) NOT NULL, name_last_name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_B0F6A6D5F85E0677 (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE training_position (id BINARY(16) NOT NULL, details LONGTEXT DEFAULT NULL, signed TINYINT NOT NULL, state VARCHAR(255) NOT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, stay_id BINARY(16) NOT NULL, student_id BINARY(16) DEFAULT NULL, academic_tutor_id BINARY(16) DEFAULT NULL, workplace_mentor_id BINARY(16) DEFAULT NULL, workcenter_id BINARY(16) DEFAULT NULL, INDEX IDX_A149F762FB3AF7D6 (stay_id), INDEX IDX_A149F762CB944F1A (student_id), INDEX IDX_A149F7621FBFECE6 (academic_tutor_id), INDEX IDX_A149F7625256CD7A (workplace_mentor_id), INDEX IDX_A149F762A2473C4B (workcenter_id), UNIQUE INDEX uq_stay_student (stay_id, student_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE training_position_programme_year (training_position_id BINARY(16) NOT NULL, programme_year_id BINARY(16) NOT NULL, INDEX IDX_41997DF479EF5491 (training_position_id), INDEX IDX_41997DF49B62B32C (programme_year_id), PRIMARY KEY (training_position_id, programme_year_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE workcenter (id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, city VARCHAR(255) DEFAULT NULL, company_id BINARY(16) NOT NULL, INDEX IDX_E2337C97979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE workcenter_worker (workcenter_id BINARY(16) NOT NULL, worker_id BINARY(16) NOT NULL, INDEX IDX_AAF9960CA2473C4B (workcenter_id), INDEX IDX_AAF9960C6B20BA36 (worker_id), PRIMARY KEY (workcenter_id, worker_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE worker (id BINARY(16) NOT NULL, national_id_number VARCHAR(20) NOT NULL, work_email VARCHAR(255) DEFAULT NULL, work_phone_number VARCHAR(50) DEFAULT NULL, name_first_name VARCHAR(255) NOT NULL, name_last_name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_9FB2BF628B718EB0 (national_id_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE academic_year ADD CONSTRAINT FK_275AE72161F9EE23 FOREIGN KEY (educational_centre_id) REFERENCES educational_centre (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CF675F31B FOREIGN KEY (author_id) REFERENCES teacher (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company ADD CONSTRAINT FK_4FBF094F61F9EE23 FOREIGN KEY (educational_centre_id) REFERENCES educational_centre (id)');
        $this->addSql('ALTER TABLE company_liaisons ADD CONSTRAINT FK_BB265AC5979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_liaisons ADD CONSTRAINT FK_BB265AC541807E1D FOREIGN KEY (teacher_id) REFERENCES teacher (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_audit ADD CONSTRAINT FK_D5E95D1C979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)');
        $this->addSql('ALTER TABLE company_audit ADD CONSTRAINT FK_D5E95D1C828AD0A0 FOREIGN KEY (changed_by_id) REFERENCES teacher (id)');
        $this->addSql('ALTER TABLE educational_centre ADD CONSTRAINT FK_2E7EDDDC3B9B1771 FOREIGN KEY (active_academic_year_id) REFERENCES academic_year (id)');
        $this->addSql('ALTER TABLE educational_centre_admins ADD CONSTRAINT FK_9F1F12EF61F9EE23 FOREIGN KEY (educational_centre_id) REFERENCES educational_centre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE educational_centre_admins ADD CONSTRAINT FK_9F1F12EF41807E1D FOREIGN KEY (teacher_id) REFERENCES teacher (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `group` ADD CONSTRAINT FK_6DC044C59B62B32C FOREIGN KEY (programme_year_id) REFERENCES programme_year (id)');
        $this->addSql('ALTER TABLE `group` ADD CONSTRAINT FK_6DC044C5208F64F1 FOREIGN KEY (tutor_id) REFERENCES teacher (id)');
        $this->addSql('ALTER TABLE group_teacher ADD CONSTRAINT FK_36F6F2D9FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_teacher ADD CONSTRAINT FK_36F6F2D941807E1D FOREIGN KEY (teacher_id) REFERENCES teacher (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE professional_family ADD CONSTRAINT FK_E5585387C54F3401 FOREIGN KEY (academic_year_id) REFERENCES academic_year (id)');
        $this->addSql('ALTER TABLE professional_family ADD CONSTRAINT FK_E5585387F41A619E FOREIGN KEY (head_id) REFERENCES teacher (id)');
        $this->addSql('ALTER TABLE programme ADD CONSTRAINT FK_3DDCB9FFC54F3401 FOREIGN KEY (academic_year_id) REFERENCES academic_year (id)');
        $this->addSql('ALTER TABLE programme ADD CONSTRAINT FK_3DDCB9FF761D1F81 FOREIGN KEY (professional_family_id) REFERENCES professional_family (id)');
        $this->addSql('ALTER TABLE programme_coordinator ADD CONSTRAINT FK_C3CFC5B562BB7AEE FOREIGN KEY (programme_id) REFERENCES programme (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE programme_coordinator ADD CONSTRAINT FK_C3CFC5B541807E1D FOREIGN KEY (teacher_id) REFERENCES teacher (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE programme_year ADD CONSTRAINT FK_A2C2415A62BB7AEE FOREIGN KEY (programme_id) REFERENCES programme (id)');
        $this->addSql('ALTER TABLE stay ADD CONSTRAINT FK_5E09839CC54F3401 FOREIGN KEY (academic_year_id) REFERENCES academic_year (id)');
        $this->addSql('ALTER TABLE stay ADD CONSTRAINT FK_5E09839C62BB7AEE FOREIGN KEY (programme_id) REFERENCES programme (id)');
        $this->addSql('ALTER TABLE stay_students ADD CONSTRAINT FK_D8A0D795FB3AF7D6 FOREIGN KEY (stay_id) REFERENCES stay (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stay_students ADD CONSTRAINT FK_D8A0D795CB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_groups ADD CONSTRAINT FK_7E5BE1F0CB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_groups ADD CONSTRAINT FK_7E5BE1F0FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE training_position ADD CONSTRAINT FK_A149F762FB3AF7D6 FOREIGN KEY (stay_id) REFERENCES stay (id)');
        $this->addSql('ALTER TABLE training_position ADD CONSTRAINT FK_A149F762CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE training_position ADD CONSTRAINT FK_A149F7621FBFECE6 FOREIGN KEY (academic_tutor_id) REFERENCES teacher (id)');
        $this->addSql('ALTER TABLE training_position ADD CONSTRAINT FK_A149F7625256CD7A FOREIGN KEY (workplace_mentor_id) REFERENCES worker (id)');
        $this->addSql('ALTER TABLE training_position ADD CONSTRAINT FK_A149F762A2473C4B FOREIGN KEY (workcenter_id) REFERENCES workcenter (id)');
        $this->addSql('ALTER TABLE training_position_programme_year ADD CONSTRAINT FK_41997DF479EF5491 FOREIGN KEY (training_position_id) REFERENCES training_position (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE training_position_programme_year ADD CONSTRAINT FK_41997DF49B62B32C FOREIGN KEY (programme_year_id) REFERENCES programme_year (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workcenter ADD CONSTRAINT FK_E2337C97979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)');
        $this->addSql('ALTER TABLE workcenter_worker ADD CONSTRAINT FK_AAF9960CA2473C4B FOREIGN KEY (workcenter_id) REFERENCES workcenter (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workcenter_worker ADD CONSTRAINT FK_AAF9960C6B20BA36 FOREIGN KEY (worker_id) REFERENCES worker (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE academic_year DROP FOREIGN KEY FK_275AE72161F9EE23');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CF675F31B');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C979B1AD6');
        $this->addSql('ALTER TABLE company DROP FOREIGN KEY FK_4FBF094F61F9EE23');
        $this->addSql('ALTER TABLE company_liaisons DROP FOREIGN KEY FK_BB265AC5979B1AD6');
        $this->addSql('ALTER TABLE company_liaisons DROP FOREIGN KEY FK_BB265AC541807E1D');
        $this->addSql('ALTER TABLE company_audit DROP FOREIGN KEY FK_D5E95D1C979B1AD6');
        $this->addSql('ALTER TABLE company_audit DROP FOREIGN KEY FK_D5E95D1C828AD0A0');
        $this->addSql('ALTER TABLE educational_centre DROP FOREIGN KEY FK_2E7EDDDC3B9B1771');
        $this->addSql('ALTER TABLE educational_centre_admins DROP FOREIGN KEY FK_9F1F12EF61F9EE23');
        $this->addSql('ALTER TABLE educational_centre_admins DROP FOREIGN KEY FK_9F1F12EF41807E1D');
        $this->addSql('ALTER TABLE `group` DROP FOREIGN KEY FK_6DC044C59B62B32C');
        $this->addSql('ALTER TABLE `group` DROP FOREIGN KEY FK_6DC044C5208F64F1');
        $this->addSql('ALTER TABLE group_teacher DROP FOREIGN KEY FK_36F6F2D9FE54D947');
        $this->addSql('ALTER TABLE group_teacher DROP FOREIGN KEY FK_36F6F2D941807E1D');
        $this->addSql('ALTER TABLE professional_family DROP FOREIGN KEY FK_E5585387C54F3401');
        $this->addSql('ALTER TABLE professional_family DROP FOREIGN KEY FK_E5585387F41A619E');
        $this->addSql('ALTER TABLE programme DROP FOREIGN KEY FK_3DDCB9FFC54F3401');
        $this->addSql('ALTER TABLE programme DROP FOREIGN KEY FK_3DDCB9FF761D1F81');
        $this->addSql('ALTER TABLE programme_coordinator DROP FOREIGN KEY FK_C3CFC5B562BB7AEE');
        $this->addSql('ALTER TABLE programme_coordinator DROP FOREIGN KEY FK_C3CFC5B541807E1D');
        $this->addSql('ALTER TABLE programme_year DROP FOREIGN KEY FK_A2C2415A62BB7AEE');
        $this->addSql('ALTER TABLE stay DROP FOREIGN KEY FK_5E09839CC54F3401');
        $this->addSql('ALTER TABLE stay DROP FOREIGN KEY FK_5E09839C62BB7AEE');
        $this->addSql('ALTER TABLE stay_students DROP FOREIGN KEY FK_D8A0D795FB3AF7D6');
        $this->addSql('ALTER TABLE stay_students DROP FOREIGN KEY FK_D8A0D795CB944F1A');
        $this->addSql('ALTER TABLE student_groups DROP FOREIGN KEY FK_7E5BE1F0CB944F1A');
        $this->addSql('ALTER TABLE student_groups DROP FOREIGN KEY FK_7E5BE1F0FE54D947');
        $this->addSql('ALTER TABLE training_position DROP FOREIGN KEY FK_A149F762FB3AF7D6');
        $this->addSql('ALTER TABLE training_position DROP FOREIGN KEY FK_A149F762CB944F1A');
        $this->addSql('ALTER TABLE training_position DROP FOREIGN KEY FK_A149F7621FBFECE6');
        $this->addSql('ALTER TABLE training_position DROP FOREIGN KEY FK_A149F7625256CD7A');
        $this->addSql('ALTER TABLE training_position DROP FOREIGN KEY FK_A149F762A2473C4B');
        $this->addSql('ALTER TABLE training_position_programme_year DROP FOREIGN KEY FK_41997DF479EF5491');
        $this->addSql('ALTER TABLE training_position_programme_year DROP FOREIGN KEY FK_41997DF49B62B32C');
        $this->addSql('ALTER TABLE workcenter DROP FOREIGN KEY FK_E2337C97979B1AD6');
        $this->addSql('ALTER TABLE workcenter_worker DROP FOREIGN KEY FK_AAF9960CA2473C4B');
        $this->addSql('ALTER TABLE workcenter_worker DROP FOREIGN KEY FK_AAF9960C6B20BA36');
        $this->addSql('DROP TABLE academic_year');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE company_liaisons');
        $this->addSql('DROP TABLE company_audit');
        $this->addSql('DROP TABLE educational_centre');
        $this->addSql('DROP TABLE educational_centre_admins');
        $this->addSql('DROP TABLE `group`');
        $this->addSql('DROP TABLE group_teacher');
        $this->addSql('DROP TABLE professional_family');
        $this->addSql('DROP TABLE programme');
        $this->addSql('DROP TABLE programme_coordinator');
        $this->addSql('DROP TABLE programme_year');
        $this->addSql('DROP TABLE stay');
        $this->addSql('DROP TABLE stay_students');
        $this->addSql('DROP TABLE student');
        $this->addSql('DROP TABLE student_groups');
        $this->addSql('DROP TABLE teacher');
        $this->addSql('DROP TABLE training_position');
        $this->addSql('DROP TABLE training_position_programme_year');
        $this->addSql('DROP TABLE workcenter');
        $this->addSql('DROP TABLE workcenter_worker');
        $this->addSql('DROP TABLE worker');
    }
}
