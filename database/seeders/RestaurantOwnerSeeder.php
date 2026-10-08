<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RestaurantOwnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner1 = User::create([
            "name" => "owner1",
            "email" => "owner1@gmail.com",
            "password" => bcrypt("1234"),
        ]);

        $owner1->assignRole("restaurant-owner");
        $owner1->assignRole("customer");
    }
}
