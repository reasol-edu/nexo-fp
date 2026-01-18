<?php
namespace App\Entity;

enum TrainingPositionState: string
{
    case DRAFT = 'DRAFT';
    case REGISTERED = 'REGISTERED';
    case PENDING = 'PENDING';
    case DONE = 'DONE';
}
