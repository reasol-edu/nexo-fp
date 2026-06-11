<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EducationalCentre;

interface TenantContextInterface
{
    public function getSelectedCentre(): ?EducationalCentre;
}
