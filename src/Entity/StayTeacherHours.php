<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StayTeacherHoursRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Horas dualizadas que imparte un docente en un grupo dentro de una estancia. Con ellas se calcula
 * cuántos estudiantes del grupo debería tutorizar cada docente (ver {@see \App\Service\TeacherAssignmentCalculator}).
 */
#[ORM\Entity(repositoryClass: StayTeacherHoursRepository::class)]
#[ORM\Table(name: 'stay_teacher_hours')]
#[ORM\UniqueConstraint(name: 'uq_sth', columns: ['stay_id', 'group_id', 'teacher_id'])]
#[ORM\Index(columns: ['group_id'], name: 'idx_sth_group')]
#[ORM\Index(columns: ['teacher_id'], name: 'idx_sth_teacher')]
class StayTeacherHours
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Stay $stay;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Group $group;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Teacher $teacher;

    #[ORM\Column]
    private int $hours;

    public function __construct(Stay $stay, Group $group, Teacher $teacher, int $hours)
    {
        $this->stay    = $stay;
        $this->group   = $group;
        $this->teacher = $teacher;
        $this->hours   = max(0, $hours);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getStay(): Stay
    {
        return $this->stay;
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function getTeacher(): Teacher
    {
        return $this->teacher;
    }

    public function getHours(): int
    {
        return $this->hours;
    }

    public function setHours(int $hours): static
    {
        $this->hours = max(0, $hours);

        return $this;
    }
}
