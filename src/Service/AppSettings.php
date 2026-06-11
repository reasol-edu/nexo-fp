<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SettingType;
use App\Entity\Teacher;
use App\Repository\CentreSettingValueRepository;
use App\Repository\GlobalSettingValueRepository;
use App\Repository\SettingDefinitionRepository;
use App\Repository\TeacherSettingValueRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class AppSettings
{
    /** @var array<string, mixed>|null null until first access */
    private ?array $resolved = null;

    public function __construct(
        private readonly SettingDefinitionRepository   $definitions,
        private readonly GlobalSettingValueRepository  $globalValues,
        private readonly CentreSettingValueRepository  $centreValues,
        private readonly TeacherSettingValueRepository $teacherValues,
        private readonly TenantContextInterface $tenant,
        private readonly Security $security,
    ) {}

    /**
     * Returns the resolved value for the given key, cast to its native type.
     * Falls back through teacher → centre → global → definition default.
     */
    public function get(string $key): mixed
    {
        $this->load();

        return $this->resolved[$key] ?? null;
    }

    /** Invalidates the in-memory cache, forcing a reload on the next get(). */
    public function invalidate(): void
    {
        $this->resolved = null;
    }

    private function load(): void
    {
        if ($this->resolved !== null) {
            return;
        }

        $this->resolved = [];

        $allDefinitions = $this->definitions->findAllIndexedByKey();
        $globalMap      = $this->globalValues->findAllIndexedByKey();
        $centreMap      = $this->loadCentreMap();
        $teacherMap     = $this->loadTeacherMap();

        foreach ($allDefinitions as $key => $definition) {
            $raw = match (true) {
                isset($teacherMap[$key]) => $teacherMap[$key]->getValue(),
                isset($centreMap[$key])  => $centreMap[$key]->getValue(),
                isset($globalMap[$key])  => $globalMap[$key]->getValue(),
                default                  => $definition->getDefaultValue(),
            };

            $this->resolved[$key] = match ($definition->getType()) {
                SettingType::Boolean => $raw === 'true',
                SettingType::Integer => (int) $raw,
                SettingType::String  => $raw,
            };
        }
    }

    /** @return array<string, \App\Entity\CentreSettingValue> */
    private function loadCentreMap(): array
    {
        $centre = $this->tenant->getSelectedCentre();

        return $centre !== null
            ? $this->centreValues->findByCentreIndexedByKey($centre)
            : [];
    }

    /** @return array<string, \App\Entity\TeacherSettingValue> */
    private function loadTeacherMap(): array
    {
        $user = $this->security->getUser();

        return $user instanceof Teacher
            ? $this->teacherValues->findByTeacherIndexedByKey($user)
            : [];
    }
}
