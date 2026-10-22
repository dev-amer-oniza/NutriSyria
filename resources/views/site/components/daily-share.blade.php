{{-- Share of one person's daily needs in a serving. Param $share: energy, protein, carbs, fat (percent, or null when the meal lacks the value). --}}
<dl class="ns-share">
    @foreach (['energy' => 'الطاقة', 'protein' => 'بروتين', 'carbs' => 'كربوهيدرات', 'fat' => 'دهون'] as $field => $label)
        <div>
            <dt>{{ $label }}</dt>
            <dd>
                <span><span class="ns-num">{{ isset($share[$field]) ? $share[$field].'%' : '—' }}</span></span>
                <span class="ns-share__track"><span class="ns-share__fill" style="width: {{ min(100, $share[$field] ?? 0) }}%"></span></span>
            </dd>
        </div>
    @endforeach
</dl>
