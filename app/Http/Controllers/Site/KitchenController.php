<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\Site\MenuView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** "المطبخ السوري": the platform's own dishes, in their sections (the owner's decision 2a). */
class KitchenController extends Controller
{
    public function __invoke(Request $request, MenuView $menus): View
    {
        $kitchen = Brand::platform();
        $menu = $menus->for($kitchen->id, $request->user());

        return view('site.pages.kitchen', [
            'kitchen' => $kitchen,
            'sections' => $menu['sections'],
            'count' => $menu['count'],
        ]);
    }
}
