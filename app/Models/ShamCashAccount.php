<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShamCashAccount extends Model
{
    protected $fillable = ['account_name','account_number','code','barcode_image','is_active'];

    protected $connection = 'central';
}
