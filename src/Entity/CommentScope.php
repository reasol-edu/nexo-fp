<?php

namespace App\Entity;

enum CommentScope: string {
    case GENERAL = 'GENERAL';
    case INCIDENT = 'INCIDENT';
    case PRIVATE_NOTE = 'PRIVATE_NOTE';
}
