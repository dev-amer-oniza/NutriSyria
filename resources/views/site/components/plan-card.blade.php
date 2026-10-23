{{--
    The nutritionist's plan as the person sees it on "my criteria": the results only (decision 3a),
    while the link lasts (4a). Params: $plan (NutritionPlanResult), $by (the nutritionist's name).
--}}
<section class="ns-plan" id="plan" aria-labelledby="plan-title">
    <div class="ns-plan__head">
        <h2 class="ns-plan__title" id="plan-title">خطتك من أخصائيّك</h2>
        <p class="ns-plan__by">وضعها {{ $by }}. نقيّم وجبات المطاعم على غدائك فيها.</p>
    </div>

    <dl class="ns-plan__grid">
        <div><dt>السعرات في اليوم</dt><dd><span class="ns-num">{{ number_format($plan->target) }}</span></dd></div>
        <div><dt>الكربوهيدرات</dt><dd><span class="ns-num">{{ $plan->macros['carbs']['g'] }}</span> غ</dd></div>
        <div><dt>البروتين</dt><dd><span class="ns-num">{{ $plan->macros['protein']['g'] }}</span> غ</dd></div>
        <div><dt>الدهون</dt><dd><span class="ns-num">{{ $plan->macros['fat']['g'] }}</span> غ</dd></div>
        @if ($plan->fluids['ml'])
            <div><dt>السوائل</dt><dd><span class="ns-num">{{ rtrim(rtrim(number_format($plan->fluids['ml'] / 1000, 2), '0'), '.') }}</span> ل</dd></div>
        @endif
    </dl>

    @if ($plan->meals)
        <div class="ns-plan__meals">
            <table>
                <caption>وجباتك في اليوم</caption>
                <thead>
                    <tr><th scope="col">الساعة</th><th scope="col">الوجبة</th><th scope="col">سعرة</th><th scope="col">كربوهيدرات</th><th scope="col">بروتين</th><th scope="col">دهون</th></tr>
                </thead>
                <tbody>
                    @foreach ($plan->meals as $meal)
                        <tr @class(['is-lunch' => $meal['type'] === 'lunch'])>
                            <td><span class="ns-num" dir="ltr">{{ $meal['time'] ? substr($meal['time'], 0, 5) : '—' }}</span></td>
                            <th scope="row">{{ $meal['name'] }}</th>
                            <td><span class="ns-num">{{ $meal['kcal'] !== null ? number_format($meal['kcal']) : '—' }}</span></td>
                            <td><span class="ns-num">{{ $meal['carbs_g'] }}</span> غ</td>
                            <td><span class="ns-num">{{ $meal['protein_g'] }}</span> غ</td>
                            <td><span class="ns-num">{{ $meal['fat_g'] }}</span> غ</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
