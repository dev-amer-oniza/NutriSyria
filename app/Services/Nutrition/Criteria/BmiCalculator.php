<?php

namespace App\Services\Nutrition\Criteria;

use App\Data\Nutrition\BmiAssessment;
use App\Enums\Goal;

/**
 * Body mass index and its class (pure: no database). The classes, the healthy range and the
 * goals each class warns against are in config/nutrition_criteria.php (bmi), approved by the
 * owner (decisions 1a 2a 3a): shown to the person and their nutritionist, a warning only.
 */
class BmiCalculator
{
    private const WARNINGS = [
        'underweight.lose' => 'وزنك أقل من الطبيعي لطولك، فلا ننصحك بإنقاص الوزن. نقترح أن تختار "المحافظة على وزني"، أو أن تستشير أخصائي تغذية.',
        // A normal weight may lose too (the owner's decision 2a), told so gently.
        'normal.lose' => 'وزنك ضمن الطبيعي لطولك. حسبنا لك إنقاصاً هادئاً كما طلبت، وانتبه ألا تنزل تحت الوزن الصحي لطولك.',
        'gain' => 'وزنك أعلى بكثير من الطبيعي لطولك، فقد لا تناسبك زيادة الوزن. نقترح أن تراجع هدفك مع أخصائي تغذية.',
    ];

    /** @param  array{classes: list<array<string, mixed>>, healthy: array{0: float, 1: float}}  $rules  config('nutrition_criteria.bmi') */
    public function __construct(private readonly array $rules) {}

    public function assess(float $heightCm, float $weightKg, ?Goal $goal = null): ?BmiAssessment
    {
        if ($heightCm <= 0 || $weightKg <= 0) {
            return null;
        }

        $metres = $heightCm / 100;
        $value = round($weightKg / ($metres ** 2), 1);
        [$low, $high] = $this->rules['healthy'];
        $range = [(int) ceil($low * $metres ** 2), (int) floor($high * $metres ** 2)];

        $class = $this->classOf($value);

        return new BmiAssessment(
            $value, $class['key'], $class['label'], $class['clinical'], ...$range,
            warning: $goal && in_array($goal->value, $class['warn'] ?? [], true)
                ? (self::WARNINGS[$class['key'].'.'.$goal->value] ?? self::WARNINGS[$goal->value] ?? null) : null,
        );
    }

    /** @return array<string, mixed> */
    private function classOf(float $value): array
    {
        foreach ($this->rules['classes'] as $class) {
            if ($class['below'] === null || $value < $class['below']) {
                return $class;
            }
        }

        return end($this->rules['classes']);
    }
}
