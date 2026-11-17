<?php

declare(strict_types=1);

namespace App\Service\Backup;

/**
 * Una copia de seguridad guardada en el servidor, con lo que se sabe de ella sin abrirla más que para leer su manifiesto.
 * Sin manifiesto (`null`) es que no se pudo leer: una copia cifrada con contraseña (el manifiesto también va cifrado), un
 * fichero dañado o un .zip que no es de Nexo FP.
 */
final readonly class StoredBackup
{
    public function __construct(
        public string $name,
        public int $size,
        public int $modifiedAt,
        public ?BackupManifest $manifest,
        public bool $automatic,
    ) {}
}
