<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Esquema inicial (MySQL / MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        // educational_centre sin FK circular (se añade después de crear academic_year)
        $this->addSql(<<<'SQL'
            CREATE TABLE educational_centre (
                id                      BINARY(16)     NOT NULL,
                active_academic_year_id BINARY(16)     DEFAULT NULL,
                code                    VARCHAR(8)   NOT NULL,
                name                    VARCHAR(255) NOT NULL,
                city                    VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_educational_centre_code ON educational_centre (code)');
        $this->addSql('CREATE INDEX IDX_educational_centre_active_year ON educational_centre (active_academic_year_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE academic_year (
                id                    BINARY(16)    NOT NULL,
                educational_centre_id BINARY(16)    NOT NULL,
                name                  VARCHAR(50) NOT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_uq_academic_year_centre ON academic_year (name, educational_centre_id)');
        $this->addSql('CREATE INDEX IDX_academic_year_centre ON academic_year (educational_centre_id)');
        $this->addSql('ALTER TABLE academic_year ADD CONSTRAINT FK_ay_centre FOREIGN KEY (educational_centre_id) REFERENCES educational_centre(id)');

        // Añadir FK circular ahora que academic_year existe
        $this->addSql('ALTER TABLE educational_centre ADD CONSTRAINT FK_ec_active_year FOREIGN KEY (active_academic_year_id) REFERENCES academic_year(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE teacher (
                id              BINARY(16)     NOT NULL,
                name_first_name VARCHAR(255) NOT NULL,
                name_last_name  VARCHAR(255) NOT NULL,
                username        VARCHAR(180) NOT NULL,
                admin           TINYINT(1)   NOT NULL,
                password        VARCHAR(255) DEFAULT NULL,
                external        TINYINT(1)   NOT NULL,
                active          TINYINT(1)   NOT NULL,
                email           VARCHAR(180) DEFAULT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_teacher_username ON teacher (username)');

        $this->addSql(<<<'SQL'
            CREATE TABLE educational_centre_admins (
                educational_centre_id BINARY(16) NOT NULL,
                teacher_id            BINARY(16) NOT NULL,
                PRIMARY KEY(educational_centre_id, teacher_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_eca_centre  ON educational_centre_admins (educational_centre_id)');
        $this->addSql('CREATE INDEX IDX_eca_teacher ON educational_centre_admins (teacher_id)');
        $this->addSql('ALTER TABLE educational_centre_admins ADD CONSTRAINT FK_eca_centre  FOREIGN KEY (educational_centre_id) REFERENCES educational_centre(id)');
        $this->addSql('ALTER TABLE educational_centre_admins ADD CONSTRAINT FK_eca_teacher FOREIGN KEY (teacher_id)            REFERENCES teacher(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE teacher_academic_year (
                academic_year_id BINARY(16) NOT NULL,
                teacher_id       BINARY(16) NOT NULL,
                PRIMARY KEY(academic_year_id, teacher_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_tay_year    ON teacher_academic_year (academic_year_id)');
        $this->addSql('CREATE INDEX IDX_tay_teacher ON teacher_academic_year (teacher_id)');
        $this->addSql('ALTER TABLE teacher_academic_year ADD CONSTRAINT FK_tay_year    FOREIGN KEY (academic_year_id) REFERENCES academic_year(id)');
        $this->addSql('ALTER TABLE teacher_academic_year ADD CONSTRAINT FK_tay_teacher FOREIGN KEY (teacher_id)       REFERENCES teacher(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE professional_family (
                id               BINARY(16)     NOT NULL,
                academic_year_id BINARY(16)     NOT NULL,
                head_id          BINARY(16)     DEFAULT NULL,
                name             VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_pf_year ON professional_family (academic_year_id)');
        $this->addSql('CREATE INDEX IDX_pf_head ON professional_family (head_id)');
        $this->addSql('ALTER TABLE professional_family ADD CONSTRAINT FK_pf_year FOREIGN KEY (academic_year_id) REFERENCES academic_year(id)');
        $this->addSql('ALTER TABLE professional_family ADD CONSTRAINT FK_pf_head FOREIGN KEY (head_id)          REFERENCES teacher(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE programme (
                id                    BINARY(16)     NOT NULL,
                academic_year_id      BINARY(16)     NOT NULL,
                professional_family_id BINARY(16)    NOT NULL,
                name                  VARCHAR(255) NOT NULL,
                details               LONGTEXT     DEFAULT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_prog_year   ON programme (academic_year_id)');
        $this->addSql('CREATE INDEX IDX_prog_family ON programme (professional_family_id)');
        $this->addSql('ALTER TABLE programme ADD CONSTRAINT FK_prog_year   FOREIGN KEY (academic_year_id)       REFERENCES academic_year(id)');
        $this->addSql('ALTER TABLE programme ADD CONSTRAINT FK_prog_family FOREIGN KEY (professional_family_id) REFERENCES professional_family(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE programme_coordinator (
                programme_id BINARY(16) NOT NULL,
                teacher_id   BINARY(16) NOT NULL,
                PRIMARY KEY(programme_id, teacher_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_pc_programme ON programme_coordinator (programme_id)');
        $this->addSql('CREATE INDEX IDX_pc_teacher   ON programme_coordinator (teacher_id)');
        $this->addSql('ALTER TABLE programme_coordinator ADD CONSTRAINT FK_pc_programme FOREIGN KEY (programme_id) REFERENCES programme(id)');
        $this->addSql('ALTER TABLE programme_coordinator ADD CONSTRAINT FK_pc_teacher   FOREIGN KEY (teacher_id)   REFERENCES teacher(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE programme_year (
                id           BINARY(16)     NOT NULL,
                programme_id BINARY(16)     NOT NULL,
                name         VARCHAR(255) NOT NULL,
                details      LONGTEXT     DEFAULT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_py_programme ON programme_year (programme_id)');
        $this->addSql('ALTER TABLE programme_year ADD CONSTRAINT FK_py_programme FOREIGN KEY (programme_id) REFERENCES programme(id)');

        // `group` es palabra reservada en MySQL → usar backticks
        $this->addSql(<<<'SQL'
            CREATE TABLE `group` (
                id                BINARY(16)     NOT NULL,
                programme_year_id BINARY(16)     NOT NULL,
                tutor_id          BINARY(16)     DEFAULT NULL,
                name              VARCHAR(255) NOT NULL,
                details           LONGTEXT     DEFAULT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_group_py    ON `group` (programme_year_id)');
        $this->addSql('CREATE INDEX IDX_group_tutor ON `group` (tutor_id)');
        $this->addSql('ALTER TABLE `group` ADD CONSTRAINT FK_group_py    FOREIGN KEY (programme_year_id) REFERENCES programme_year(id)');
        $this->addSql('ALTER TABLE `group` ADD CONSTRAINT FK_group_tutor FOREIGN KEY (tutor_id)          REFERENCES teacher(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE group_teacher (
                group_id   BINARY(16) NOT NULL,
                teacher_id BINARY(16) NOT NULL,
                PRIMARY KEY(group_id, teacher_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_gt_group   ON group_teacher (group_id)');
        $this->addSql('CREATE INDEX IDX_gt_teacher ON group_teacher (teacher_id)');
        $this->addSql('ALTER TABLE group_teacher ADD CONSTRAINT FK_gt_group   FOREIGN KEY (group_id)   REFERENCES `group`(id)');
        $this->addSql('ALTER TABLE group_teacher ADD CONSTRAINT FK_gt_teacher FOREIGN KEY (teacher_id) REFERENCES teacher(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE student (
                id              BINARY(16)     NOT NULL,
                name_first_name VARCHAR(255) NOT NULL,
                name_last_name  VARCHAR(255) NOT NULL,
                student_id      VARCHAR(50)  NOT NULL,
                details         VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE student_groups (
                student_id BINARY(16) NOT NULL,
                group_id   BINARY(16) NOT NULL,
                PRIMARY KEY(student_id, group_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_sg_student ON student_groups (student_id)');
        $this->addSql('CREATE INDEX IDX_sg_group   ON student_groups (group_id)');
        $this->addSql('ALTER TABLE student_groups ADD CONSTRAINT FK_sg_student FOREIGN KEY (student_id) REFERENCES student(id)');
        $this->addSql('ALTER TABLE student_groups ADD CONSTRAINT FK_sg_group   FOREIGN KEY (group_id)   REFERENCES `group`(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE company (
                id                        BINARY(16)     NOT NULL,
                educational_centre_id     BINARY(16)     NOT NULL,
                name                      VARCHAR(255) NOT NULL,
                vat_number                VARCHAR(50)  NOT NULL,
                city                      VARCHAR(255) NOT NULL,
                exceptional_circumstances LONGTEXT     DEFAULT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_uq_company_vat_centre ON company (vat_number, educational_centre_id)');
        $this->addSql('CREATE INDEX IDX_company_centre ON company (educational_centre_id)');
        $this->addSql('ALTER TABLE company ADD CONSTRAINT FK_company_centre FOREIGN KEY (educational_centre_id) REFERENCES educational_centre(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE company_liaisons (
                company_id BINARY(16) NOT NULL,
                teacher_id BINARY(16) NOT NULL,
                PRIMARY KEY(company_id, teacher_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_cl_company ON company_liaisons (company_id)');
        $this->addSql('CREATE INDEX IDX_cl_teacher ON company_liaisons (teacher_id)');
        $this->addSql('ALTER TABLE company_liaisons ADD CONSTRAINT FK_cl_company FOREIGN KEY (company_id) REFERENCES company(id)');
        $this->addSql('ALTER TABLE company_liaisons ADD CONSTRAINT FK_cl_teacher FOREIGN KEY (teacher_id) REFERENCES teacher(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE worker (
                id                  BINARY(16)     NOT NULL,
                name_first_name     VARCHAR(255) NOT NULL,
                name_last_name      VARCHAR(255) NOT NULL,
                national_id_number  VARCHAR(20)  NOT NULL,
                work_email          VARCHAR(255) DEFAULT NULL,
                work_phone_number   VARCHAR(50)  DEFAULT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_worker_national_id ON worker (national_id_number)');

        $this->addSql(<<<'SQL'
            CREATE TABLE company_workers (
                company_id BINARY(16) NOT NULL,
                worker_id  BINARY(16) NOT NULL,
                PRIMARY KEY(company_id, worker_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_cw_company ON company_workers (company_id)');
        $this->addSql('CREATE INDEX IDX_cw_worker  ON company_workers (worker_id)');
        $this->addSql('ALTER TABLE company_workers ADD CONSTRAINT FK_cw_company FOREIGN KEY (company_id) REFERENCES company(id)');
        $this->addSql('ALTER TABLE company_workers ADD CONSTRAINT FK_cw_worker  FOREIGN KEY (worker_id)  REFERENCES worker(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE workcenter (
                id         BINARY(16)     NOT NULL,
                company_id BINARY(16)     NOT NULL,
                name       VARCHAR(255) NOT NULL,
                city       VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_wc_company ON workcenter (company_id)');
        $this->addSql('ALTER TABLE workcenter ADD CONSTRAINT FK_wc_company FOREIGN KEY (company_id) REFERENCES company(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE comment (
                id         BINARY(16)  NOT NULL,
                author_id  BINARY(16)  NOT NULL,
                company_id BINARY(16)  NOT NULL,
                content    LONGTEXT  NOT NULL,
                posted_at  DATETIME  NOT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_comment_author  ON comment (author_id)');
        $this->addSql('CREATE INDEX IDX_comment_company ON comment (company_id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_comment_author  FOREIGN KEY (author_id)  REFERENCES teacher(id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_comment_company FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            CREATE TABLE company_audit (
                id            BINARY(16)  NOT NULL,
                company_id    BINARY(16)  NOT NULL,
                changed_by_id BINARY(16)  DEFAULT NULL,
                changed_at    DATETIME  NOT NULL,
                changes       JSON      NOT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_ca_company    ON company_audit (company_id)');
        $this->addSql('CREATE INDEX IDX_ca_changed_by ON company_audit (changed_by_id)');
        $this->addSql('ALTER TABLE company_audit ADD CONSTRAINT FK_ca_company    FOREIGN KEY (company_id)    REFERENCES company(id)');
        $this->addSql('ALTER TABLE company_audit ADD CONSTRAINT FK_ca_changed_by FOREIGN KEY (changed_by_id) REFERENCES teacher(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE stay (
                id               BINARY(16)     NOT NULL,
                academic_year_id BINARY(16)     NOT NULL,
                programme_id     BINARY(16)     NOT NULL,
                name             VARCHAR(255) NOT NULL,
                start_date       DATE         NOT NULL,
                end_date         DATE         NOT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_uq_stay_name_year ON stay (name, academic_year_id)');
        $this->addSql('CREATE INDEX IDX_stay_year      ON stay (academic_year_id)');
        $this->addSql('CREATE INDEX IDX_stay_programme ON stay (programme_id)');
        $this->addSql('ALTER TABLE stay ADD CONSTRAINT FK_stay_year      FOREIGN KEY (academic_year_id) REFERENCES academic_year(id)');
        $this->addSql('ALTER TABLE stay ADD CONSTRAINT FK_stay_programme FOREIGN KEY (programme_id)     REFERENCES programme(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE stay_students (
                stay_id    BINARY(16) NOT NULL,
                student_id BINARY(16) NOT NULL,
                PRIMARY KEY(stay_id, student_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_ss_stay    ON stay_students (stay_id)');
        $this->addSql('CREATE INDEX IDX_ss_student ON stay_students (student_id)');
        $this->addSql('ALTER TABLE stay_students ADD CONSTRAINT FK_ss_stay    FOREIGN KEY (stay_id)    REFERENCES stay(id)');
        $this->addSql('ALTER TABLE stay_students ADD CONSTRAINT FK_ss_student FOREIGN KEY (student_id) REFERENCES student(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE training_position (
                id                  BINARY(16)     NOT NULL,
                stay_id             BINARY(16)     NOT NULL,
                student_id          BINARY(16)     DEFAULT NULL,
                academic_tutor_id   BINARY(16)     DEFAULT NULL,
                workplace_mentor_id BINARY(16)     DEFAULT NULL,
                workcenter_id       BINARY(16)     DEFAULT NULL,
                details             LONGTEXT     DEFAULT NULL,
                signed              TINYINT(1)   NOT NULL,
                state               VARCHAR(255) NOT NULL,
                start_date          DATE         DEFAULT NULL,
                end_date            DATE         DEFAULT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_uq_stay_student ON training_position (stay_id, student_id)');
        $this->addSql('CREATE INDEX IDX_tp_stay       ON training_position (stay_id)');
        $this->addSql('CREATE INDEX IDX_tp_student    ON training_position (student_id)');
        $this->addSql('CREATE INDEX IDX_tp_tutor      ON training_position (academic_tutor_id)');
        $this->addSql('CREATE INDEX IDX_tp_mentor     ON training_position (workplace_mentor_id)');
        $this->addSql('CREATE INDEX IDX_tp_workcenter ON training_position (workcenter_id)');
        $this->addSql('ALTER TABLE training_position ADD CONSTRAINT FK_tp_stay       FOREIGN KEY (stay_id)             REFERENCES stay(id)');
        $this->addSql('ALTER TABLE training_position ADD CONSTRAINT FK_tp_student    FOREIGN KEY (student_id)          REFERENCES student(id)');
        $this->addSql('ALTER TABLE training_position ADD CONSTRAINT FK_tp_tutor      FOREIGN KEY (academic_tutor_id)   REFERENCES teacher(id)');
        $this->addSql('ALTER TABLE training_position ADD CONSTRAINT FK_tp_mentor     FOREIGN KEY (workplace_mentor_id) REFERENCES worker(id)');
        $this->addSql('ALTER TABLE training_position ADD CONSTRAINT FK_tp_workcenter FOREIGN KEY (workcenter_id)       REFERENCES workcenter(id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE training_position_programme_year (
                training_position_id BINARY(16) NOT NULL,
                programme_year_id    BINARY(16) NOT NULL,
                PRIMARY KEY(training_position_id, programme_year_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_tppy_tp ON training_position_programme_year (training_position_id)');
        $this->addSql('CREATE INDEX IDX_tppy_py ON training_position_programme_year (programme_year_id)');
        $this->addSql('ALTER TABLE training_position_programme_year ADD CONSTRAINT FK_tppy_tp FOREIGN KEY (training_position_id) REFERENCES training_position(id)');
        $this->addSql('ALTER TABLE training_position_programme_year ADD CONSTRAINT FK_tppy_py FOREIGN KEY (programme_year_id)    REFERENCES programme_year(id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        // Romper la FK circular antes de eliminar tablas
        $this->addSql('ALTER TABLE educational_centre DROP FOREIGN KEY FK_ec_active_year');

        // Eliminar en orden inverso de dependencias (las hojas primero)
        $this->addSql('DROP TABLE training_position_programme_year');
        $this->addSql('DROP TABLE training_position');
        $this->addSql('DROP TABLE stay_students');
        $this->addSql('DROP TABLE stay');
        $this->addSql('DROP TABLE company_audit');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE workcenter');
        $this->addSql('DROP TABLE company_workers');
        $this->addSql('DROP TABLE worker');
        $this->addSql('DROP TABLE company_liaisons');
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE student_groups');
        $this->addSql('DROP TABLE group_teacher');
        $this->addSql('DROP TABLE `group`');
        $this->addSql('DROP TABLE student');
        $this->addSql('DROP TABLE programme_year');
        $this->addSql('DROP TABLE programme_coordinator');
        $this->addSql('DROP TABLE programme');
        $this->addSql('DROP TABLE professional_family');
        $this->addSql('DROP TABLE teacher_academic_year');
        $this->addSql('DROP TABLE educational_centre_admins');
        $this->addSql('DROP TABLE academic_year');
        $this->addSql('DROP TABLE teacher');
        $this->addSql('DROP TABLE educational_centre');
    }
}
