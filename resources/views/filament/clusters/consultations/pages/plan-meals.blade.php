{{-- The plan's meals by time (ClientPlan summary, ViewClient). Param: $meals (NutritionPlanResult::$meals). --}}
<table class="w-full text-sm">
    <thead>
        <tr class="text-gray-500 dark:text-gray-400">
            <th class="py-1 text-start font-medium">الساعة</th>
            <th class="py-1 text-start font-medium">الوجبة</th>
            <th class="py-1 text-start font-medium">سعرة</th>
            <th class="py-1 text-start font-medium">ك / ب / د غ</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($meals as $meal)
            <tr class="border-t border-gray-200 dark:border-white/10">
                <td class="py-1.5 px-1" dir="ltr">{{ $meal['time'] ? substr($meal['time'], 0, 5) : '—' }}</td>
                <td class="px-1 font-semibold">{{ $meal['name'] }} <span class="font-normal text-gray-500 dark:text-gray-400">{{ $meal['share_pct'] }}٪</span></td>
                <td class="px-1">{{ $meal['kcal'] !== null ? number_format($meal['kcal']) : '—' }}</td>
                <td class="px-1">{{ $meal['carbs_g'] }} / {{ $meal['protein_g'] }} / {{ $meal['fat_g'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
