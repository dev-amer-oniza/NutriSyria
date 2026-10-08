<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RestaurantCategoryPivotSeeder extends Seeder
{
    public function run(): void
    {
        $restaurantIds = DB::table('restaurants')->pluck('id'); 
        $categoryIds = DB::table('categories')->pluck('id'); 

        foreach ($restaurantIds as $restaurantId) {
            
            $randomCategories = $categoryIds->random(rand(2, 3));

            foreach ($randomCategories as $categoryId) {
                DB::table('restaurant_categories')->insert([
                    'restaurant_id' => $restaurantId,
                    'category_id' => $categoryId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}