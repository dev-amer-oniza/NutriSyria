<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesInSystem extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolenames = ["super-admin","restaurant-owner","customer" , "delivery"];
        foreach ($rolenames as $name) {
            Role::findOrCreate( $name);
        }
    }
}
