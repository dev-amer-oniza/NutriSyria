<?php

namespace Tests\Unit;

use App\Data\Nutrition\References\ReferenceCard;
use App\Enums\HealthCondition;
use App\Services\Nutrition\References\MedicalReferences;
use PHPUnit\Framework\TestCase;

/** The references pages read the same rules the calculators apply (step 3b). */
class MedicalReferencesTest extends TestCase
{
    private MedicalReferences $references;

    protected function setUp(): void
    {
        parent::setUp();
        $this->references = new MedicalReferences(
            require __DIR__.'/../../config/nutrition_criteria.php',
            require __DIR__.'/../../config/nutrition_references.php',
        );
    }

    public function test_every_named_reference_is_in_the_register(): void
    {
        foreach ($this->references->cards() as $card) {
            foreach ($card->refs as $key) {
                $this->assertNotNull($this->references->reference($key), "{$card->anchor}: $key");
            }
            foreach ($card->rules as $rule) {
                foreach ($rule->refs as $key) {
                    $this->assertNotNull($this->references->reference($key), "{$card->anchor}: $key");
                }
                $this->assertSame($rule->refs !== [], $rule->verified, "{$card->anchor}: {$rule->label}");
            }
        }
    }

    public function test_the_general_needs_are_verified_with_their_numbers(): void
    {
        $general = $this->references->general();
        $rules = array_column(array_map(fn ($r) => (array) $r, $general->rules), null, 'label');

        $this->assertSame(ReferenceCard::VERIFIED, $general->status);
        $this->assertSame('حتى 2000 ملغ في اليوم', $rules['الصوديوم']['value']);
        $this->assertSame('على الأقل 14 غ لكل 1000 سعرة في اليوم', $rules['الألياف']['value']);
        $this->assertSame('1.40 / 1.55 / 1.75 / 2.00 / 2.20', $rules['معاملات النشاط']['value']);
        $this->assertTrue($rules['السكريات الكلية']['infoOnly'], 'sugars are shown, never judge');
        $this->assertTrue($rules['زيادة الوزن']['infoOnly'], 'no guideline for +300');
    }

    public function test_a_template_is_verified_only_with_all_its_rules(): void
    {
        $this->assertSame(ReferenceCard::VERIFIED, $this->references->template('balanced')->status);

        $celiac = $this->references->template('celiac');
        $this->assertSame(ReferenceCard::PARTIAL, $celiac->status);
        $this->assertSame('موثّق جزئياً 1/2', $celiac->statusLabel());

        $ckd = $this->references->template('ckd');
        $this->assertSame(ReferenceCard::PENDING, $ckd->status);
        $this->assertSame('حتى 0.6 غ لكل كغ من الوزن في اليوم', $ckd->rules[1]->value);
    }

    public function test_a_condition_counts_the_template_it_brings(): void
    {
        $celiac = $this->references->condition(HealthCondition::Celiac);
        $this->assertSame(['key' => 'celiac', 'label' => 'السيلياك (حساسية الغلوتين)', 'anchor' => 'template-celiac'], $celiac->template);
        $this->assertSame(ReferenceCard::PARTIAL, $celiac->status);

        // A condition whose own rule is not verified yet reads as pending.
        $rules = require __DIR__.'/../../config/nutrition_criteria.php';
        $rules['conditions']['fat_intolerance'] = ['criteria' => ['meal_fat_g' => ['type' => 'fixed', 'value' => 15]]];
        $pending = new MedicalReferences($rules, require __DIR__.'/../../config/nutrition_references.php');
        $this->assertSame(ReferenceCard::PENDING, $pending->condition(HealthCondition::FatIntolerance)->status);
    }

    public function test_the_liver_conditions_read_as_their_references_say(): void
    {
        $cirrhosis = $this->references->condition(HealthCondition::Cirrhosis);
        $this->assertSame(ReferenceCard::VERIFIED, $cirrhosis->status);
        $this->assertSame('على الأقل 1.2 غ لكل كغ من الوزن في اليوم', $cirrhosis->rules[0]->value);
        $this->assertSame(['easl_nutrition_2019', 'espen_liver_2019'], $cirrhosis->rules[1]->refs);

        $acute = $this->references->condition(HealthCondition::HepatitisAcute);
        $this->assertSame([ReferenceCard::VERIFIED, []], [$acute->status, $acute->rules], 'no figures of its own, by its references');
        $this->assertNotEmpty($acute->refs);

        $this->assertSame(ReferenceCard::GENERAL, $this->references->condition(HealthCondition::Jaundice)->status);
    }

    public function test_the_old_liver_choice_has_no_card(): void
    {
        $anchors = array_map(fn (ReferenceCard $card) => $card->anchor, $this->references->conditions());

        $this->assertNotContains('liver_cirrhosis', $anchors);
        $this->assertCount(count(HealthCondition::offered()), $anchors);
    }

    public function test_the_link_opens_the_persons_own_card(): void
    {
        $this->assertSame('cirrhosis', $this->references->anchorFor(['liver_cirrhosis', 'cirrhosis'], 'balanced'));
        $this->assertSame('template-dash', $this->references->anchorFor([], 'dash'));
        $this->assertSame('general', $this->references->anchorFor([], 'balanced'));
        $this->assertSame('general', $this->references->anchorFor([], null));
    }
}
