<?php

namespace App\Enums;

enum FoodScopeType :String
{
    case GLOBAL = "global";
    case RESTAURANT = "restaurant";
    case TENANT = "tenant";
    case COUNTRY = "country";
    case REGION = "region";
}
