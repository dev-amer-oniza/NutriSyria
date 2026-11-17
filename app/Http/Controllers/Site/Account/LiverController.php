<?php

namespace App\Http\Controllers\Site\Account;

use App\Http\Controllers\Controller;
use App\Services\Nutrition\LiverUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Picking again for those who chose the old "الكبد الوبائي أو التليّف" (step 3, decision 8a). */
class LiverController extends Controller
{
    public function show(Request $request, LiverUpdate $liver): View|RedirectResponse
    {
        if (! $liver->isPending($request->user())) {
            return redirect()->route('site.criteria');
        }

        return view('site.pages.account.liver', ['choices' => LiverUpdate::CHOICES]);
    }

    public function update(Request $request, LiverUpdate $liver): RedirectResponse
    {
        $choice = $request->validate([
            'choice' => ['required', Rule::in([...array_map(fn ($c) => $c->value, LiverUpdate::CHOICES), LiverUpdate::UNKNOWN])],
        ], ['choice.required' => 'اختر واحدة من الحالات، أو «لا أعرف بعد».'])['choice'];

        $liver->choose($request->user(), $choice);

        return redirect()->route('site.criteria')->with('status', 'حُدّثت حالتك. ستُقيَّم الوجبات عليها من الآن.');
    }
}
