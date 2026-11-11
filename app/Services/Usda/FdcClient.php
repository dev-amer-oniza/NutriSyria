<?php

namespace App\Services\Usda;

use App\Exceptions\FdcUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * USDA FoodData Central's API: search and one food's details. Only the releases agreed for
 * ingredients are asked for (Foundation and SR Legacy). Answers are kept for a day, so the
 * same search does not spend the hourly quota twice.
 */
class FdcClient
{
    /** FDC dataType => data_sources.code */
    public const DATA_TYPES = ['Foundation' => 'usda_fdc_foundation', 'SR Legacy' => 'usda_fdc_sr_legacy'];

    public const PAGE_SIZE = 25;

    private const CACHE_HOURS = 24;

    /** Energy first as reported, then the Atwater figures Foundation foods carry instead. */
    private const SUMMARY = ['kcal' => [1008, 2048, 2047], 'protein' => [1003], 'fat' => [1004], 'carbs' => [1005]];

    public function configured(): bool
    {
        return filled(config('services.fdc.key'));
    }

    /**
     * @return array{hits: int, pages: int, page: int, foods: list<array{fdcId: int, description: string, dataType: string, published: ?string, category: ?string, kcal: ?float, protein: ?float, fat: ?float, carbs: ?float}>}
     */
    public function search(string $query, int $page = 1): array
    {
        $query = trim($query);
        $key = 'fdc.search.'.md5(mb_strtolower($query)).'.'.$page;

        return Cache::remember($key, now()->addHours(self::CACHE_HOURS), function () use ($query, $page) {
            $data = $this->get('foods/search', [
                'query' => $query,
                'dataType' => implode(',', array_keys(self::DATA_TYPES)),
                'pageSize' => self::PAGE_SIZE,
                'pageNumber' => $page,
            ]);

            return [
                'hits' => (int) ($data['totalHits'] ?? 0),
                'pages' => (int) ($data['totalPages'] ?? 0),
                'page' => $page,
                'foods' => array_values(array_map(self::summary(...), array_filter(
                    $data['foods'] ?? [],
                    fn (array $food) => isset(self::DATA_TYPES[$food['dataType'] ?? '']),
                ))),
            ];
        });
    }

    /** One food as USDA describes it (nutrients, portions). */
    public function food(int $fdcId): array
    {
        return Cache::remember("fdc.food.$fdcId", now()->addHours(self::CACHE_HOURS), fn () => $this->get("food/$fdcId"));
    }

    private static function summary(array $food): array
    {
        $amounts = [];
        foreach ($food['foodNutrients'] ?? [] as $n) {
            if (isset($n['nutrientId'], $n['value'])) {
                $amounts[(int) $n['nutrientId']] = (float) $n['value'];
            }
        }
        $pick = function (array $ids) use ($amounts) {
            foreach ($ids as $id) {
                if (isset($amounts[$id])) {
                    return $amounts[$id];
                }
            }

            return null;
        };

        return [
            'fdcId' => (int) $food['fdcId'],
            'description' => (string) ($food['description'] ?? ''),
            'dataType' => (string) $food['dataType'],
            'published' => $food['publishedDate'] ?? null,
            'category' => $food['foodCategory'] ?? null,
        ] + array_map($pick, self::SUMMARY);
    }

    private function get(string $path, array $params = []): array
    {
        if (! $this->configured()) {
            throw new FdcUnavailable('مفتاح USDA غير موجود. أضف FDC_API_KEY إلى ملف .env (المفتاح مجاني من api.data.gov/signup).');
        }

        try {
            // The key goes in a header, so it never appears in a logged address.
            $response = Http::withHeaders(['X-Api-Key' => config('services.fdc.key')])
                ->acceptJson()->timeout(20)->retry(2, 400, throw: false)
                ->get(rtrim(config('services.fdc.url'), '/').'/'.$path, $params);
        } catch (ConnectionException) {
            throw new FdcUnavailable('تعذّر الاتصال بموقع USDA. تأكد من الإنترنت ثم حاول مرة أخرى.');
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        throw new FdcUnavailable(match ($response->status()) {
            401, 403 => 'مفتاح USDA غير صحيح أو موقوف. راجع FDC_API_KEY في ملف .env.',
            404 => 'هذا الغذاء غير موجود في USDA.',
            429 => 'تجاوزنا حد الطلبات المسموح من USDA (1000 في الساعة). حاول بعد قليل.',
            default => 'موقع USDA لا يجيب الآن (خطأ '.$response->status().'). حاول بعد قليل.',
        });
    }
}
