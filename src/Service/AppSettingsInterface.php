<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Teacher;

interface AppSettingsInterface
{
    /** Returns the resolved value for the current user / selected centre context. */
    public function get(string $key): mixed;

    /** Returns the resolved value for a specific teacher (teacher → global → default, no centre). */
    public function getForTeacher(string $key, Teacher $teacher): mixed;
}
