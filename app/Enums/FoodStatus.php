<?php

namespace App\Enums;

enum FoodStatus: string
{
    case ACTIVE = 'active';
    case RETIRED = 'retired';
    case REVIEW = 'review';
}

