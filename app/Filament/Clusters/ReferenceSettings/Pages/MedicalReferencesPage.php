<?php

namespace App\Filament\Clusters\ReferenceSettings\Pages;

use App\Auth\Access;
use App\Data\Nutrition\References\MedicalKind;
use App\Data\Nutrition\References\ReferenceCard;
use App\Data\Nutrition\References\ReferenceRule;
use App\Enums\HealthCondition;
use App\Filament\Clusters\ReferenceSettings\ReferenceSettingsCluster;
use App\Services\Nutrition\References\MedicalReferences;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * "المراجع الطبية" (step 3b, decisions 1a 2a): every medical rule, its value, kind, references
 * and status, read only. The numbers live in config/nutrition_criteria.php and every change
 * goes through a pull request, so nothing here can be edited. For the admin and nutritionists.
 */
class MedicalReferencesPage extends Page
{
    protected string $view = 'filament.clusters.reference-settings.pages.medical-references';

    protected static ?string $cluster = ReferenceSettingsCluster::class;

    protected static ?string $slug = 'medical-references';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ShieldCheck;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'المراجع الطبية';

    protected static ?string $title = 'المراجع الطبية';

    /** all | pending */
    public string $status = 'all';

    public string $kind = '';

    /** '' | general | templates | a condition group */
    public string $area = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->isSuperAdmin() || $user->hasRole(Access::NUTRITIONIST));
    }

    public function getSubheading(): string
    {
        return 'للعرض فقط. الأرقام في ملف الإعدادات، وكل تغيير يمر بمراجعة الكود. القاعدة غير الموثّقة لا تدخل في تقييم الوجبات.';
    }

    /** @return array<string, string> */
    public function kinds(): array
    {
        return array_map(fn (array $kind) => $kind[0], MedicalKind::KINDS);
    }

    /** @return array<string, string> */
    public function areas(): array
    {
        return ['general' => 'الاحتياج العام', 'templates' => 'القوالب', ...array_map(fn (string $g) => 'حالات: '.$g, HealthCondition::GROUPS)];
    }

    /** @return array{verified: int, pending: int, total: int} rules counted over all cards, each rule once */
    public function totals(MedicalReferences $references): array
    {
        $rules = [];
        foreach ($references->cards() as $card) {
            foreach ($card->rules as $rule) {
                if (! $rule->infoOnly) {
                    $rules[] = $rule;
                }
            }
        }
        $verified = count(array_filter($rules, fn (ReferenceRule $rule) => $rule->verified));

        return ['verified' => $verified, 'pending' => count($rules) - $verified, 'total' => count($rules)];
    }

    /** @return list<array{card: ReferenceCard, rules: list<ReferenceRule>}> the cards and rules the filters keep */
    public function rows(MedicalReferences $references): array
    {
        $rows = [];
        foreach ($references->cards() as $card) {
            if (! $this->inArea($card)) {
                continue;
            }
            $rules = array_values(array_filter($card->rules, fn (ReferenceRule $rule) => ($this->status !== 'pending' || (! $rule->verified && ! $rule->infoOnly))
                && ($this->kind === '' || ($rule->infoOnly ? 'info' : $rule->kind) === $this->kind)));
            $filtered = $this->status !== 'all' || $this->kind !== '';
            if ($filtered && $rules === [] && ! ($this->status === 'pending' && $this->kind === '' && $card->hasPending() && $card->template !== null)) {
                continue;
            }
            $rows[] = ['card' => $card, 'rules' => $rules];
        }

        return $rows;
    }

    private function inArea(ReferenceCard $card): bool
    {
        return match ($this->area) {
            '' => true,
            'general' => $card->type === 'general',
            'templates' => $card->type === 'template',
            default => $card->type === 'condition' && $card->group === $this->area,
        };
    }

    protected function getViewData(): array
    {
        $references = MedicalReferences::fromConfig();

        return [
            'references' => $references,
            'rows' => $this->rows($references),
            'totals' => $this->totals($references),
        ];
    }
}
