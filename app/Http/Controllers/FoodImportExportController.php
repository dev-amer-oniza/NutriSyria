<?php

namespace App\Http\Controllers;

use App\Services\FoodImportExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FoodImportExportController extends Controller
{
    public function __construct(
        protected FoodImportExportService $service
    ) {}

    public function exportFoods(): BinaryFileResponse
    {
        return $this->service->exportFoods();
    }

    public function exportFoodNutrients(): BinaryFileResponse
    {
        return $this->service->exportFoodNutrients();
    }

    public function importFoods(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        $result = $this->service->importFoods($request->file('file')->getRealPath());

        return back()->with('status', sprintf(
            '%d foods imported, %d skipped.',
            $result['imported'],
            $result['skipped']
        ));
    }

    public function importFoodNutrients(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        $result = $this->service->importFoodNutrients($request->file('file')->getRealPath());

        return back()->with('status', sprintf(
            '%d food nutrients imported, %d skipped.',
            $result['imported'],
            $result['skipped']
        ));
    }
}
