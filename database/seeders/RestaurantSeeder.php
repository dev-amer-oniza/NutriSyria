<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $restaurants = [
            [
                'owner_id' => 3,
                'name' => 'Sham Restaurant',
                'description' => 'Authentic Syrian cuisine',
                'logo' => 'restaurants/logos/sham.avif',
                'cover_image' => 'restaurants/covers/sham.jpg',
                'address' => 'Amsterdam Center',
                'phone' => '0612345678',
                'email' => 'sham@example.com',
                'commission_rate' => 10,
                'is_active' => true,
                'latitude' => 52.3676,
                'longitude' => 4.9041,
                'opening_time' => '09:00:00',
                'closing_time' => '23:00:00',
                'rate' => 4,
            ],
            [
                'owner_id' => 3,
                'name' => 'Italian Restaurant',
                'description' => 'Pizza and pasta specialties',
                'logo' => 'restaurants/logos/italian.avif',
                'cover_image' => 'restaurants/covers/italian.avif',
                'address' => 'Amsterdam West',
                'phone' => '0623456789',
                'email' => 'italian@example.com',
                'commission_rate' => 12,
                'is_active' => true,
                'latitude' => 52.3702,
                'longitude' => 4.8952,
                'opening_time' => '10:00:00',
                'closing_time' => '22:00:00',
                'rate' => 5,
            ],
            [
                'owner_id' => 3,
                'name' => 'Burger House',
                'description' => 'Best burgers in town',
                'logo' => 'restaurants/logos/burger.png',
                'cover_image' => 'restaurants/covers/burger.jpg',
                'address' => 'Amsterdam East',
                'phone' => '0634567890',
                'email' => 'burger@example.com',
                'commission_rate' => 8,
                'is_active' => true,
                'latitude' => 52.3600,
                'longitude' => 4.9100,
                'opening_time' => '11:00:00',
                'closing_time' => '01:00:00',
                'rate' => 3,
            ],
            [
                'owner_id' => 3,
                'name' => 'Sushi Bar',
                'description' => 'Fresh sushi daily',
                'logo' => 'restaurants/logos/sushi.webp',
                'cover_image' => 'restaurants/covers/sushi.jpg',
                'address' => 'Amsterdam South',
                'phone' => '0645678901',
                'email' => 'sushi@example.com',
                'commission_rate' => 15,
                'is_active' => true,
                'latitude' => 52.3540,
                'longitude' => 4.8810,
                'opening_time' => '12:00:00',
                'closing_time' => '23:30:00',
                'rate' => 5,
            ],
            [
                'owner_id' => 3,
                'name' => 'Grill House',
                'description' => 'Delicious grilled dishes',
                'logo' => 'restaurants/logos/grill.png',
                'cover_image' => 'restaurants/covers/grill.jpg',
                'address' => 'Amsterdam North',
                'phone' => '0656789012',
                'email' => 'grill@example.com',
                'commission_rate' => 9,
                'is_active' => true,
                'latitude' => 52.3920,
                'longitude' => 4.9000,
                'opening_time' => '13:00:00',
                'closing_time' => '00:00:00',
                'rate' => 4,
            ],
            [
                'owner_id' => 3,
                'name' => 'Vegan Delight',
                'description' => 'Healthy vegan food',
                'logo' => 'restaurants/logos/vegan.webp',
                'cover_image' => 'restaurants/covers/vegan.avif',
                'address' => 'Amsterdam West',
                'phone' => '0667890123',
                'email' => 'vegan@example.com',
                'commission_rate' => 7,
                'is_active' => true,
                'latitude' => 52.3680,
                'longitude' => 4.8800,
                'opening_time' => '08:00:00',
                'closing_time' => '21:00:00',
                'rate' => 4,
            ],
        ];

        DB::table('restaurants')->insert($restaurants);
    }
}