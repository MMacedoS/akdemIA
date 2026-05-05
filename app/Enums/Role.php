<?php

namespace App\Enums;

enum Role: string
{
    case ADMIN = 'admin';
    case TRAINER = 'trainer';
    case STUDENT = 'student';
}
