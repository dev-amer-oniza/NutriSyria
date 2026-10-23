<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Nutrition\Fit\MealFitService;
use App\Services\Site\HealthGoalGuide;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** "Health goals": what each goal changes in our calculations, and where to go next. */
class GoalsController extends Controller
{
    public function __invoke(Request $request, HealthGoalGuide $guide, MealFitService $fits): View
    {
        $user = $request->user();

        return view('site.pages.goals', [
            'goals' => $guide->goals(),
            'conditions' => $guide->conditions(),
            // Meals are judged per person, never per goal (decision 3b): the way to "my meals".
            'next' => match (true) {
                $user === null => ['label' => 'أنشئ حسابك', 'url' => route('site.register'), 'text' => 'أدخل بياناتك وهدفك مرة واحدة، فنقيّم لك كل وجبة.'],
                $fits->sheetFor($user) === null => ['label' => 'أكمل ملفك', 'url' => route('site.onboarding.show', 'body'), 'text' => 'أكمل بياناتك وهدفك، فنقيّم لك كل وجبة.'],
                default => ['label' => 'الوجبات المناسبة لي', 'url' => route('site.meals', ['suits' => 1]), 'text' => 'نقيّم كل وجبة على معاييرك أنت.'],
            },
        ]);
    }
}
