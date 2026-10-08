<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Restaurants\Models\Restaurant;

class Category extends Model
{
    protected $fillable =['name','img','is_active'];

    protected $connection = 'central';

    public function restaurants():BelongsToMany{
        return $this->belongsToMany(Restaurant::class,'restaurant_categories'); 
        

    }
}
