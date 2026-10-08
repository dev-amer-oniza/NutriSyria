<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customer1 = User::create([
            'name' => 'customer1' ,
            'email' => "ameroniza@gmail.com",
            "password"=> bcrypt("1234"),
            'email_verified_at' => now(),
        ]);

        $customer1->assignRole('customer');
        $customer1->assignRole('restaurant-owner');
        $customer1->assignRole('delivery');
    }
}
