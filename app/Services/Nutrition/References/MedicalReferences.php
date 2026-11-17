<?php

namespace App\Services\Nutrition\References;

use App\Data\Nutrition\References\ReferenceCard;
use App\Data\Nutrition\References\ReferenceRule;
use App\Enums\Allergen;
use App\Enums\HealthCondition;

/**
 * Every medical rule we apply, with its references and how far it is verified, read from
 * config/nutrition_criteria.php and config/nutrition_references.php (step 3b of the medical
 * plan). Pure: the admin page "المراجع الطبية" and the public page "المصادر" both show it, so
 * they can never disagree with each other or with what the calculators do.
 *
 * A rule is verified when it names references ('refs'), as in CriteriaCalculator. A card is
 * verified when all its rules are, partly when some are; a condition counts the rules of the
 * template it brings too.
 */
final class MedicalReferences
{
    /** Template groups in the nutritionist's library, in the order shown. */
    public const TEMPLATE_GROUPS = [
        'general' => 'العامة',
        'heart' => 'القلب',
        'metabolic' => 'السكري والتمثيل الغذائي',
        'kidney' => 'الكلى',
        'digestion' => 'الهضم والكبد',
        'other' => 'أخرى',
    ];

    /** @var array<string, ReferenceCard> */
    private array $templateCards = [];

    public function __construct(private readonly array $rules, private readonly array $references) {}

    public static function fromConfig(): self
    {
        return new self(config('nutrition_criteria'), config('nutrition_references'));
    }

    /** @return array<string, array> the references, keyed */
    public function references(): array
    {
        return $this->references;
    }

    public function reference(string $key): ?array
    {
        return $this->references[$key] ?? null;
    }

    /** @return list<ReferenceCard> general needs, then the templates, then the conditions */
    public function cards(): array
    {
        return [$this->general(), ...$this->templates(), ...$this->conditions()];
    }

    /** The needs of everyone, and the rules every person starts from. */
    public function general(): ReferenceCard
    {
        $r = $this->rules;
        $factors = implode(' / ', array_map(fn ($f) => number_format((float) $f, 2), $r['activity_factors']));
        $bmi = array_values(array_filter(array_column($r['bmi']['classes'], 'below')));

        $rules = [
            new ReferenceRule('السعرات اليومية', 'معادلة Mifflin-St Jeor × معامل النشاط ± الهدف', 'guidance',
                $r['criteria']['energy_kcal']['refs'] ?? [], ! empty($r['criteria']['energy_kcal']['refs'])),
            new ReferenceRule('معاملات النشاط', $factors, 'guidance', $r['activity_refs'], ! empty($r['activity_refs'])),
            new ReferenceRule('إنقاص الوزن', $this->number(-$r['goal_adjustment']['lose']).' سعرة أقل في اليوم، لمن مؤشر كتلته '.$r['deficit_min_bmi'].' فأكثر',
                'guidance', $r['goal_refs'], ! empty($r['goal_refs'])),
            new ReferenceRule('زيادة الوزن', '+'.$r['goal_adjustment']['gain'].' سعرة تُعرض فقط', 'guidance', [], false,
                infoOnly: ! $r['surplus_judged'], note: 'لا يوجد إرشاد رسمي لها، فنقيّم الوجبات على سعرات الثبات.'),
            new ReferenceRule('أدنى السعرات المقترحة', $r['min_kcal']['female'].' سعرة للنساء، '.$r['min_kcal']['male'].' للرجال',
                'guidance', $r['min_kcal_refs'], ! empty($r['min_kcal_refs'])),
            new ReferenceRule('مؤشر كتلة الجسم', sprintf('نقص الوزن تحت %s، زيادة الوزن من %s، السمنة من %s', ...array_map(fn ($b) => $this->number($b), array_slice($bmi, 0, 3))),
                'guidance', $r['bmi']['refs'], ! empty($r['bmi']['refs']),
                infoOnly: true, note: 'للعرض فقط، لا يغيّر التقييم. درجتا السمنة 35 و40 لم نقرأ مرجعهما بعد.'),
        ];
        foreach ($r['criteria'] as $key => $definition) {
            if ($definition['default'] !== null && $key !== 'energy_kcal') {
                $rules[] = $this->rule($key, $definition['default'], $definition['refs'] ?? []);
            }
        }

        return $this->card('general', 'general', 'الاحتياج العام لكل شخص', 'general',
            'ما يبدأ منه كل شخص: السعرات من جسمه ونشاطه وهدفه، وتوزيعها بالقالب «'.$r['templates'][$r['default_template']]['label'].'»، وحدود الصوديوم والمشبعة والألياف.',
            $rules);
    }

    /** @return list<ReferenceCard> */
    public function templates(): array
    {
        $cards = [];
        foreach (array_keys(self::TEMPLATE_GROUPS) as $group) {
            foreach ($this->rules['templates'] as $key => $template) {
                if ($template['group'] === $group) {
                    $cards[] = $this->template($key);
                }
            }
        }

        return $cards;
    }

    public function template(string $key): ReferenceCard
    {
        if (isset($this->templateCards[$key])) {
            return $this->templateCards[$key];
        }
        $template = $this->rules['templates'][$key];
        $macros = $template['macros'];

        $rules = [new ReferenceRule('توزيع السعرات',
            "كربوهيدرات {$macros['carbs']}% · بروتين {$macros['protein']}% · دهون {$macros['fat']}%",
            'guidance', $template['refs'] ?? [], ! empty($template['refs']),
            note: empty($template['refs']) ? 'حتى يُوثَّق، تُقيَّم الوجبات بتوزيع «'.$this->rules['templates'][$this->rules['default_template']]['label'].'» الموثّق، إلا إن اختاره لك أخصائي.' : null)];
        foreach ($template['rules'] ?? [] as $criterion => $rule) {
            $rules[] = $this->rule($criterion, $rule, $rule['refs'] ?? [], $rule['bound'] ?? null);
        }
        if ($template['allergens'] ?? []) {
            $names = implode(' و', array_map(fn (string $a) => Allergen::tryFrom($a)?->label() ?? $a, $template['allergens']));
            $rules[] = new ReferenceRule('يتجنّب', $names.' تماماً', 'strict', $template['allergen_refs'] ?? [], ! empty($template['allergen_refs']));
        }
        if ($template['no_deficit'] ?? false) {
            $refs = $template['no_deficit_refs'] ?? [];
            $rules[] = new ReferenceRule('إنقاص الوزن', 'بلا عجز في السعرات', 'guidance', $refs, $refs !== []);
        }

        return $this->templateCards[$key] = $this->card('template-'.$key, 'template', $template['label'], $template['group'],
            $template['description'], $rules, advice: $template['see_nutritionist'] ?? null);
    }

    /** @return list<ReferenceCard> the conditions a person can choose, by group */
    public function conditions(): array
    {
        $cards = [];
        foreach (array_keys(HealthCondition::GROUPS) as $group) {
            foreach (HealthCondition::offered() as $condition) {
                if ($condition->group() === $group && isset($this->rules['conditions'][$condition->value])) {
                    $cards[] = $this->condition($condition);
                }
            }
        }

        return $cards;
    }

    public function condition(HealthCondition $condition): ReferenceCard
    {
        $definition = $this->rules['conditions'][$condition->value];
        $rules = [];
        foreach ($definition['criteria'] ?? [] as $criterion => $rule) {
            $rules[] = $this->rule($criterion, $rule, $rule['refs'] ?? [], $rule['bound'] ?? null);
        }
        if ($definition['no_deficit'] ?? false) {
            $refs = $definition['no_deficit_refs'] ?? [];
            $rules[] = new ReferenceRule('إنقاص الوزن', 'بلا عجز في السعرات', 'guidance', $refs, $refs !== [],
                note: $definition['no_deficit_reason'] ?? null);
        }
        if (isset($definition['energy_min_per_kg'])) {
            $refs = $definition['energy_refs'] ?? [];
            $rules[] = new ReferenceRule('السعرات اليومية',
                'على الأقل '.$definition['energy_min_per_kg'].' سعرة لكل كغ من الوزن، بلا عجز، لمن مؤشر كتلته أقل من '.$definition['energy_min_below_bmi'],
                'guidance', $refs, $refs !== []);
        }

        $template = isset($definition['template']) ? $this->template($definition['template']) : null;

        return $this->card($condition->value, 'condition', $condition->label(), $condition->group(), $condition->hint(),
            $rules, $definition['refs'] ?? [], $definition['see_nutritionist'] ?? $template?->advice,
            $template ? ['key' => $definition['template'], 'label' => $template->title, 'anchor' => $template->anchor] : null,
            $template->rules ?? []);
    }

    /**
     * Where "من أين هذا الحد؟" points for a person: their first condition with a card, else the
     * template they are judged with when it is not the default, else the general needs.
     *
     * @param  list<string>  $conditions
     */
    public function anchorFor(array $conditions, ?string $templateKey): string
    {
        foreach (HealthCondition::offered() as $condition) {
            if (in_array($condition->value, $conditions, true) && isset($this->rules['conditions'][$condition->value])) {
                return $condition->value;
            }
        }

        return $templateKey && $templateKey !== ($this->rules['default_template'] ?? null) && isset($this->rules['templates'][$templateKey])
            ? 'template-'.$templateKey
            : 'general';
    }

    /**
     * @param  list<ReferenceRule>  $rules
     * @param  list<ReferenceRule>  $inherited  the rules of the template a condition brings, counted in its status
     */
    private function card(string $anchor, string $type, string $title, string $group, ?string $description, array $rules,
        array $refs = [], ?string $advice = null, ?array $template = null, array $inherited = []): ReferenceCard
    {
        $counted = array_values(array_filter([...$rules, ...$inherited], fn (ReferenceRule $rule) => ! $rule->infoOnly));
        $verified = count(array_filter($counted, fn (ReferenceRule $rule) => $rule->verified));
        $total = count($counted);

        $status = match (true) {
            $total === 0 => $refs !== [] ? ReferenceCard::VERIFIED : ReferenceCard::GENERAL,
            $verified === $total => ReferenceCard::VERIFIED,
            $verified === 0 => ReferenceCard::PENDING,
            default => ReferenceCard::PARTIAL,
        };

        return new ReferenceCard($anchor, $type, $title, $group, $description, $rules, $refs, $status, $verified, $total, $template, $advice);
    }

    private function rule(string $criterion, array $rule, array $refs, ?string $bound = null): ReferenceRule
    {
        $definition = $this->rules['criteria'][$criterion];
        $bound ??= $definition['bound'];

        return new ReferenceRule(
            label: $definition['label'],
            value: $this->describe($rule, $bound, $definition),
            kind: $bound === 'min' ? 'shortfall' : ($definition['kind'] ?? 'guidance'),
            refs: $refs,
            verified: $refs !== [],
            infoOnly: (bool) ($definition['info_only'] ?? false),
            note: $definition['note'] ?? null,
        );
    }

    /** "حتى 2000 ملغ في اليوم", "على الأقل 1.2 غ لكل كغ من الوزن في اليوم", "حتى 15 غ للوجبة". */
    private function describe(array $rule, string $bound, array $definition): string
    {
        $unit = $definition['unit'];
        $amount = match ($rule['type']) {
            'fixed' => $this->number($rule['value']).' '.$unit,
            'by_sex' => $this->number($rule['female']).' '.$unit.' للنساء، '.$this->number($rule['male']).' للرجال',
            'per_kg' => $this->number($rule['value'] ?? 0).' '.$unit.' لكل كغ من الوزن',
            'energy_pct' => $rule['pct'].'% من السعرات',
            'per_1000' => $this->number($rule['value']).' '.$unit.' لكل 1000 سعرة',
            'macro' => 'حسب توزيع القالب',
            default => $rule['type'],
        };
        if ($rule['type'] === 'macro') {
            return $amount;
        }
        $prefix = match ($bound) {
            'max' => 'حتى ',
            'min' => 'على الأقل ',
            default => '',
        };

        return $prefix.$amount.($definition['scope'] === 'meal' ? ' للوجبة' : ' في اليوم');
    }

    private function number(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
