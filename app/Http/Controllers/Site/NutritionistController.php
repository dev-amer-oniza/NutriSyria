<?php

namespace App\Http\Controllers\Site;

use App\Enums\NutritionistSpecialty;
use App\Enums\SyrianGovernorate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Consultations\NutritionistSearchRequest;
use App\Services\Consultations\ConsultationService;
use App\Services\Consultations\NutritionistDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** The site's nutritionist directory and each nutritionist's page. */
class NutritionistController extends Controller
{
    public function index(NutritionistSearchRequest $request, NutritionistDirectory $directory): View
    {
        return view('site.pages.nutritionists.index', [
            'profiles' => $directory->listed($request->specialty(), $request->city()),
            'specialty' => $request->specialty(),
            'city' => $request->city(),
            'specialties' => NutritionistSpecialty::options(),
            'cities' => SyrianGovernorate::options(),
        ]);
    }

    public function show(Request $request, int $profile, NutritionistDirectory $directory, ConsultationService $consultations): View
    {
        return view('site.pages.nutritionists.show', [
            'profile' => $directory->find($profile) ?? abort(404),
            // The signed-in person's open request or link decides the booking button.
            'current' => $request->user() ? $consultations->current($request->user()) : null,
        ]);
    }
}
