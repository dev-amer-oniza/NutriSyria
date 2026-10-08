<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin1 = User::firstOrCreate([
            "name"=> "admin1",
            "email" => "admin1@gmail.com",
            "password"=> bcrypt("1234"),
        ]);

        $admin1->assignRole("super-admin");
    }
}
