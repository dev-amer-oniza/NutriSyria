<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;

class CentralDatabaseNotification extends DatabaseNotification
{
    protected $connection = 'central';
}
