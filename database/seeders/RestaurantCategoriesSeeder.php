<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RestaurantCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'المشروبات',          'img'=>   'https://res.cloudinary.com/dnpqxfirl/image/upload/v1778230627/beautiful-refined-drinks-guests_8353-9107_neasmj.avif'],
            ['name' => 'الوجبات السريعة',   'img'=>   'https://res.cloudinary.com/dnpqxfirl/image/upload/v1775895402/central/categories/RvIneSK7Atq7EhDANFJfzWxv0IDX5d-metaZmFzdGZvb2QuanBn-_qizh9a.jpg'],
            ['name' => 'الحلويات',           'img'=>  'https://res.cloudinary.com/dnpqxfirl/image/upload/v1775894133/central/categories/ibseMj5QkStn1pnrndxZ4l7yoYBI6Q-metaZjMyMzczYWMtYWI3My00ZmM5LWFjOWMtMmY2ODM1NzQzOWZhLTEwMDB4MTAwMC13YkVNNVlidDdZbzJuazVHb3pGTEpQeGZIeWYyNFZ4THQ0U1k0eXhVLndlYnA_-_u1odta.webp'],
            ['name' => 'المأكولات البحرية',  'img'=>  'https://res.cloudinary.com/dnpqxfirl/image/upload/v1775894199/central/categories/ccpfg8SPrYKo7WiI4EFM6qbQoN5rlu-metaTEgtc2VhZm9vZC1ib2lsLWdrdGwtbWVkaXVtU3F1YXJlQXQzWC5qcGc_-_lr49aw.jpg'],
            ['name' => 'مشاوي',              'img'=>  'https://res.cloudinary.com/dnpqxfirl/image/upload/v1775894256/central/categories/nwW1GOY2oATrSPq1oV9fJox3SHJxnI-metaQmxvZy1HcmlsbGluZy1MLmpwZw_-_uszhrv.jpg'],
            ['name' => 'إفطار',              'img' => 'https://res.cloudinary.com/dnpqxfirl/image/upload/v1775894557/central/categories/g7Zx4u22Jsa31NnG7StplCgqK9RtcG-metac3RyZXNzZnJlZWZ1bGxlbmdsaXNfNjc3MjFfMTZ4OS5qcGc_-_meng2k.jpg'],
            ['name' => 'شاورما',             'img' => 'https://res.cloudinary.com/dnpqxfirl/image/upload/v1775895013/central/categories/1XV62UxJ90azlZafIhioOAo2nYnX8R-metaU2hhd2FybWEuanBn-_zinjhw.jpg'],
            ['name' => 'برغر',               'img' => 'https://res.cloudinary.com/dnpqxfirl/image/upload/v1775895074/central/categories/uk2rZfTjiFU9MHXI6HphGka1py1vM6-metaMzYwX0ZfMjE3MzQzMjc5X0Izb1VNZW16V2p5Y0RxMFF4NmtUN2x0NFJoWVlBcGNCLmpwZw_-_yj9czz.jpg'],
            ['name' => 'بيتزا',              'img' => 'https://res.cloudinary.com/dnpqxfirl/image/upload/v1775895202/central/categories/RkgSjXbT21nd7JCfRP0ffbBX2ThzRp-metacGl6emEuanBn-_qzzsb9.jpg'],
            ['name' => 'كوكتيلات و عصائر',   'img'=>  'https://res.cloudinary.com/dnpqxfirl/image/upload/v1775894037/central/categories/3IHXACSCkiWSity8Y5DtKJvERuys7R-metac2Vhc29uYWwtZHJpbmstMS5wbmc_-_mmpbdg.png'],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->insert([
                'name' => $category['name'],
                'img' => $category['img'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}