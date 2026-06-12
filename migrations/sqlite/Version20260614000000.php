<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Corrección UUID en setting_definition: convierte IDs de TEXT RFC 4122 a TEXT binario para que los JOINs con Doctrine funcionen (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        // Los IDs de setting_definition fueron insertados como TEXT RFC 4122 en la
        // migración original, pero Doctrine los serializa como TEXT binario (16 bytes)
        // al hacer bind vía PDO. Esto provoca que los JOINs fallen y loadStoredMap()
        // devuelva siempre vacío. CAST(x'...' AS TEXT) produce el mismo tipo y valor
        // que almacena PDO al serializar un UUID con toBinary().
        $this->addSql("UPDATE setting_definition SET id = CAST(x'1A000000000040008000000000000001' AS TEXT) WHERE id = '1a000000-0000-4000-8000-000000000001'");
        $this->addSql("UPDATE setting_definition SET id = CAST(x'1A000000000040008000000000000002' AS TEXT) WHERE id = '1a000000-0000-4000-8000-000000000002'");
        $this->addSql("UPDATE setting_definition SET id = CAST(x'1A000000000040008000000000000003' AS TEXT) WHERE id = '1a000000-0000-4000-8000-000000000003'");
        $this->addSql("UPDATE setting_definition SET id = CAST(x'1A000000000040008000000000000004' AS TEXT) WHERE id = '1a000000-0000-4000-8000-000000000004'");
        $this->addSql("UPDATE setting_definition SET id = CAST(x'1A000000000040008000000000000005' AS TEXT) WHERE id = '1a000000-0000-4000-8000-000000000005'");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql("UPDATE setting_definition SET id = '1a000000-0000-4000-8000-000000000001' WHERE id = CAST(x'1A000000000040008000000000000001' AS TEXT)");
        $this->addSql("UPDATE setting_definition SET id = '1a000000-0000-4000-8000-000000000002' WHERE id = CAST(x'1A000000000040008000000000000002' AS TEXT)");
        $this->addSql("UPDATE setting_definition SET id = '1a000000-0000-4000-8000-000000000003' WHERE id = CAST(x'1A000000000040008000000000000003' AS TEXT)");
        $this->addSql("UPDATE setting_definition SET id = '1a000000-0000-4000-8000-000000000004' WHERE id = CAST(x'1A000000000040008000000000000004' AS TEXT)");
        $this->addSql("UPDATE setting_definition SET id = '1a000000-0000-4000-8000-000000000005' WHERE id = CAST(x'1A000000000040008000000000000005' AS TEXT)");
    }
}
