<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\CentreSettingValue;
use App\Entity\EducationalCentre;
use App\Entity\GlobalSettingValue;
use App\Entity\SettingDefinition;
use App\Entity\Teacher;
use App\Entity\TeacherSettingValue;
use App\Repository\CentreSettingValueRepository;
use App\Repository\GlobalSettingValueRepository;
use App\Repository\SettingDefinitionRepository;
use App\Repository\TeacherSettingValueRepository;
use App\Service\AppSettings;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsLiveComponent]
class SettingsComponent extends AbstractController
{
    use DefaultActionTrait;

    /** 'global' | 'centre' | 'teacher' */
    #[LiveProp]
    public string $scope = 'teacher';

    /** Key of the last saved/reset setting — used for inline feedback. */
    #[LiveProp]
    public string $lastSaved = '';

    /** Key of the setting that last failed validation — used for inline error feedback. */
    #[LiveProp]
    public string $lastError = '';

    public function __construct(
        private readonly SettingDefinitionRepository   $definitions,
        private readonly GlobalSettingValueRepository  $globalValues,
        private readonly CentreSettingValueRepository  $centreValues,
        private readonly TeacherSettingValueRepository $teacherValues,
        private readonly EntityManagerInterface $em,
        private readonly TenantContext $tenant,
        private readonly Security $security,
        private readonly AppSettings $appSettings,
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * Returns rows for the current scope, each row containing the definition,
     * the stored raw value (null = not set), lock state and parent-lock origin.
     *
     * @return list<array{definition: SettingDefinition, storedValue: ?string, effectiveValue: string, isLocked: bool, parentLock: ?string}>
     */
    public function getRows(): array
    {
        $defs          = $this->definitions->findByScope($this->scope);
        $storedMap     = $this->loadStoredMap();
        $parentLockMap = $this->loadParentLockMap();
        $rows          = [];

        foreach ($defs as $def) {
            $key         = $def->getKey();
            $storedValue = $storedMap[$key] ?? null;
            $stored      = $storedValue?->getValue();

            $rows[] = [
                'definition'     => $def,
                'storedValue'    => $stored,
                'effectiveValue' => $stored ?? $def->getDefaultValue(),
                'isLocked'       => $storedValue !== null
                                        && method_exists($storedValue, 'isLocked')
                                        && $storedValue->isLocked(),
                'parentLock'     => $parentLockMap[$key] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Saves or resets a setting value for the current scope.
     * An empty string or '__default__' removes the stored value.
     */
    #[LiveAction]
    public function save(#[LiveArg] string $key, #[LiveArg] string $value): void
    {
        if (isset($this->loadParentLockMap()[$key])) {
            return;
        }

        $isReset = $value === '' || $value === '__default__';
        $def     = $this->definitions->findOneBy(['key' => $key]);

        if ($def === null) {
            return;
        }

        if ($isReset) {
            $this->removeValue($def);
            $this->lastError = '';
        } else {
            if (!$def->isValueValid($value)) {
                $this->lastError = $key;

                return;
            }

            $this->lastError = '';
            $this->upsertValue($def, $value);
        }

        $this->em->flush();
        $this->appSettings->invalidate();
        $this->lastSaved = $key;
    }

    #[LiveAction]
    public function toggleLock(#[LiveArg] string $key): void
    {
        $def = $this->definitions->findOneBy(['key' => $key]);
        if ($def === null) {
            return;
        }

        match ($this->scope) {
            'global'  => $this->toggleGlobalLock($def),
            'centre'  => $this->toggleCentreLock($def),
            default   => null,
        };

        $this->em->flush();
        $this->appSettings->invalidate();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function upsertValue(SettingDefinition $def, string $value): void
    {
        match ($this->scope) {
            'global'  => $this->upsertGlobal($def, $value),
            'centre'  => $this->upsertCentre($def, $value),
            'teacher' => $this->upsertTeacher($def, $value),
            default   => null,
        };
    }

    private function removeValue(SettingDefinition $def): void
    {
        match ($this->scope) {
            'global'  => $this->removeGlobal($def),
            'centre'  => $this->removeCentre($def),
            'teacher' => $this->removeTeacher($def),
            default   => null,
        };
    }

    private function upsertGlobal(SettingDefinition $def, string $value): void
    {
        $entity = $this->globalValues->findByDefinition($def)
            ?? (new GlobalSettingValue())->setDefinition($def);

        $entity->setValue($value);
        $this->em->persist($entity);
    }

    private function upsertCentre(SettingDefinition $def, string $value): void
    {
        $centre = $this->requireCentre();
        $entity = $this->centreValues->findByDefinitionAndCentre($def, $centre)
            ?? (new CentreSettingValue())->setDefinition($def)->setCentre($centre);

        $entity->setValue($value);
        $this->em->persist($entity);
    }

    private function upsertTeacher(SettingDefinition $def, string $value): void
    {
        $teacher = $this->requireTeacher();
        $entity  = $this->teacherValues->findByDefinitionAndTeacher($def, $teacher)
            ?? (new TeacherSettingValue())->setDefinition($def)->setTeacher($teacher);

        $entity->setValue($value);
        $this->em->persist($entity);
    }

    private function removeGlobal(SettingDefinition $def): void
    {
        $entity = $this->globalValues->findByDefinition($def);
        if ($entity !== null) {
            $this->em->remove($entity);
        }
    }

    private function removeCentre(SettingDefinition $def): void
    {
        $entity = $this->centreValues->findByDefinitionAndCentre($def, $this->requireCentre());
        if ($entity !== null) {
            $this->em->remove($entity);
        }
    }

    private function removeTeacher(SettingDefinition $def): void
    {
        $entity = $this->teacherValues->findByDefinitionAndTeacher($def, $this->requireTeacher());
        if ($entity !== null) {
            $this->em->remove($entity);
        }
    }

    private function toggleGlobalLock(SettingDefinition $def): void
    {
        $entity = $this->globalValues->findByDefinition($def);
        if ($entity === null) {
            $entity = (new GlobalSettingValue())
                ->setDefinition($def)
                ->setValue($def->getDefaultValue())
                ->setLocked(true);
            $this->em->persist($entity);
            return;
        }
        $entity->setLocked(!$entity->isLocked());
    }

    private function toggleCentreLock(SettingDefinition $def): void
    {
        $centre = $this->requireCentre();
        $entity = $this->centreValues->findByDefinitionAndCentre($def, $centre);
        if ($entity === null) {
            $entity = (new CentreSettingValue())
                ->setDefinition($def)
                ->setCentre($centre)
                ->setValue($def->getDefaultValue())
                ->setLocked(true);
            $this->em->persist($entity);
            return;
        }
        $entity->setLocked(!$entity->isLocked());
    }

    /**
     * Returns a map of keys locked by a parent scope.
     * Values are 'global' or 'centre' indicating the locking level.
     *
     * @return array<string, 'global'|'centre'>
     */
    private function loadParentLockMap(): array
    {
        $result = [];

        if (in_array($this->scope, ['centre', 'teacher'], true)) {
            foreach ($this->globalValues->findAllIndexedByKey() as $key => $v) {
                if ($v->isLocked()) {
                    $result[$key] = 'global';
                }
            }
        }

        if ($this->scope === 'teacher') {
            $centre = $this->tenant->getSelectedCentre();
            if ($centre !== null) {
                foreach ($this->centreValues->findByCentreIndexedByKey($centre) as $key => $v) {
                    if ($v->isLocked() && !isset($result[$key])) {
                        $result[$key] = 'centre';
                    }
                }
            }
        }

        return $result;
    }

    /** @return array<string, GlobalSettingValue|CentreSettingValue|TeacherSettingValue> */
    private function loadStoredMap(): array
    {
        return match ($this->scope) {
            'global'  => $this->globalValues->findAllIndexedByKey(),
            'centre'  => $this->centreValues->findByCentreIndexedByKey($this->requireCentre()),
            'teacher' => $this->teacherValues->findByTeacherIndexedByKey($this->requireTeacher()),
            default   => [],
        };
    }

    private function requireCentre(): EducationalCentre
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            throw $this->createAccessDeniedException();
        }

        return $centre;
    }

    private function requireTeacher(): Teacher
    {
        $user = $this->security->getUser();
        if (!$user instanceof Teacher) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
