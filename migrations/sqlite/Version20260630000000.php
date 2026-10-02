<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260630000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sin cambios en SQLite: las FK de las tablas de unión no se aplican (PRAGMA foreign_keys desactivado) y recrearlas no aporta nada';
    }

    public function up(Schema $schema): void
    {
    }

    public function down(Schema $schema): void
    {
    }
}
