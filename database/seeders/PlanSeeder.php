<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ── Features ─────────────────────────────────────────
        $features = [
            ['name' => 'Max branches', 'code' => 'MAX_BRANCHES', 'type' => 'limit'],
            ['name' => 'Max menu items', 'code' => 'MAX_MENU_ITEMS', 'type' => 'limit'],
            ['name' => 'Meal analysis', 'code' => 'MEAL_ANALYSIS', 'type' => 'boolean'],
            ['name' => 'Advanced orders', 'code' => 'ADVANCED_ORDERS', 'type' => 'boolean'],
            ['name' => 'Priority support', 'code' => 'PRIORITY_SUPPORT', 'type' => 'boolean'],
        ];

        foreach ($features as $f) {
            Feature::updateOrCreate(['code' => $f['code']], $f);
        }

        $maxBranches = Feature::where('code', 'MAX_BRANCHES')->first();
        $maxMenuItems = Feature::where('code', 'MAX_MENU_ITEMS')->first();
        $mealAnalysis = Feature::where('code', 'MEAL_ANALYSIS')->first();
        $advOrders = Feature::where('code', 'ADVANCED_ORDERS')->first();
        $prioritySup = Feature::where('code', 'PRIORITY_SUPPORT')->first();

        // ── Plans ────────────────────────────────────────────
        $free = Plan::updateOrCreate(['code' => 'FREE'], [
            'name' => 'Free',
            'price' => 0,
            'billing_interval' => 'monthly',
            'is_active' => true,
        ]);

        $starter = Plan::updateOrCreate(['code' => 'STARTER'], [
            'name' => 'Starter',
            'price' => 19,
            'billing_interval' => 'monthly',
            'is_active' => true,
        ]);

        $pro = Plan::updateOrCreate(['code' => 'PRO'], [
            'name' => 'Pro',
            'price' => 49,
            'billing_interval' => 'monthly',
            'is_active' => true,
        ]);

        // ── Attach features ──────────────────────────────────
        $free->features()->syncWithoutDetaching([
            $maxBranches->id => ['value' => '1'],
            $maxMenuItems->id => ['value' => '30'],
            $mealAnalysis->id => ['value' => 'false'],
            $advOrders->id => ['value' => 'false'],
            $prioritySup->id => ['value' => 'false'],
        ]);

        $starter->features()->syncWithoutDetaching([
            $maxBranches->id => ['value' => '3'],
            $maxMenuItems->id => ['value' => '200'],
            $mealAnalysis->id => ['value' => 'true'],
            $advOrders->id => ['value' => 'true'],
            $prioritySup->id => ['value' => 'false'],
        ]);

        $pro->features()->syncWithoutDetaching([
            $maxBranches->id => ['value' => '-1'],   // -1 = unlimited
            $maxMenuItems->id => ['value' => '-1'],
            $mealAnalysis->id => ['value' => 'true'],
            $advOrders->id => ['value' => 'true'],
            $prioritySup->id => ['value' => 'true'],
        ]);
    }
}
