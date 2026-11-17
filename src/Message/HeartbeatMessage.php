<?php

declare(strict_types=1);

namespace App\Message;

/** Cada pocos minutos (ver Schedule.php): demuestra que hay un worker en marcha consumiendo el planificador. Se ve en Administración → Estado del sistema. */
final class HeartbeatMessage {}
