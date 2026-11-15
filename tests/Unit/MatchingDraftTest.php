<?php

namespace Tests\Unit;

use App\Services\Import\MatchingDraft;
use PHPUnit\Framework\TestCase;

/** A real draft from `usda_to_template.py suggest` (lentils, chickpeas, dried thyme, eggs, labneh). */
class MatchingDraftTest extends TestCase
{
    private function draft(): MatchingDraft
    {
        return MatchingDraft::fromCsv((string) file_get_contents(__DIR__.'/../Fixtures/matching/draft.csv'));
    }

    private function rowOf(MatchingDraft $draft, string $fdcId): int
    {
        foreach ($draft->rows() as $i => $row) {
            if ($row['fdc_id'] === $fdcId) {
                return $i;
            }
        }
        $this->fail("no row {$fdcId}");
    }

    public function test_the_draft_is_read_with_its_arabic_names_and_groups(): void
    {
        $draft = $this->draft();

        $this->assertCount(15, $draft->rows());
        $this->assertSame('عدس', $draft->rows()[0]['food_name_ar']); // BOM stripped
        $this->assertSame(
            ['عدس|raw', 'عدس|boiled', 'حمص|raw', 'حمص|boiled', 'زعتر يابس|dried', 'بيض|raw', 'بيض|boiled', 'لبنة|any'],
            array_column($draft->groups(), 'key'),
        );
    }

    public function test_the_tools_picks_are_kept(): void
    {
        $draft = $this->draft();
        $picks = $draft->initialPicks();

        $this->assertCount(11, $picks);
        $this->assertArrayNotHasKey($this->rowOf($draft, '748967'), $picks); // weak name match: not pre-marked
        $this->assertSame(['pairs' => 8, 'chosen' => 8, 'undecided' => 0, 'no_candidate' => 0], $draft->summary($picks));
        $this->assertSame([], $draft->problems($picks));
    }

    public function test_picking_another_row_moves_the_choice_within_the_same_release(): void
    {
        $draft = $this->draft();
        $saltless = $this->rowOf($draft, '172421');
        $salted = $this->rowOf($draft, '175254');

        $picks = $draft->toggle($draft->initialPicks(), $salted);

        $this->assertTrue($picks[$salted]);
        $this->assertArrayNotHasKey($saltless, $picks);
    }

    public function test_foundation_and_sr_legacy_are_picked_side_by_side(): void
    {
        $draft = $this->draft();
        $picks = $draft->initialPicks();
        $foundationEgg = $this->rowOf($draft, '748967');
        $legacyEgg = $this->rowOf($draft, '171287');

        $picks = $draft->toggle($picks, $foundationEgg);

        $this->assertTrue($picks[$foundationEgg]);
        $this->assertTrue($picks[$legacyEgg]);
    }

    public function test_clicking_a_pick_again_drops_it(): void
    {
        $draft = $this->draft();
        $thyme = $this->rowOf($draft, '171328');

        $picks = $draft->toggle($draft->initialPicks(), $thyme);

        $this->assertArrayNotHasKey($thyme, $picks);
        $this->assertSame(1, $draft->summary($picks)['undecided']);
    }

    public function test_the_same_usda_item_for_two_foods_is_refused(): void
    {
        // A draft edited by hand: the boiled-lentils item also marked as the raw lentils of SR Legacy.
        $csv = str_replace('yes,,عدس,Lentils,raw,raw,172420', 'yes,,عدس,Lentils,raw,raw,172421', (string) file_get_contents(__DIR__.'/../Fixtures/matching/draft.csv'));
        $twice = MatchingDraft::fromCsv($csv);

        $problems = $twice->problems($twice->initialPicks());

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('172421', $problems[0]);
    }

    public function test_nothing_picked_is_a_problem(): void
    {
        $this->assertSame(['لم تختر أي غذاء بعد.'], $this->draft()->problems([]));
    }

    public function test_the_reviewed_csv_carries_only_the_chosen_rows_as_yes(): void
    {
        $draft = $this->draft();
        $picks = $draft->toggle($draft->initialPicks(), $this->rowOf($draft, '171328')); // drop thyme

        $reviewed = MatchingDraft::fromCsv($draft->toCsv($picks));

        $this->assertSame($draft->rows()[0]['description'], $reviewed->rows()[0]['description']);
        $this->assertCount(10, $reviewed->initialPicks());
        $this->assertSame('', $reviewed->rows()[$this->rowOf($reviewed, '171328')]['pick']);
        $this->assertStringStartsWith("\xEF\xBB\xBFpick,hint,food_name_ar", $draft->toCsv($picks));
    }

    public function test_the_tools_english_is_shown_in_arabic(): void
    {
        $this->assertSame('الاسم لا يطابق تماماً: تأكد', MatchingDraft::hintAr('weak name match: check'));
        $this->assertSame('تحليل ناقص: لا البروتين، الدهون', MatchingDraft::hintAr('incomplete analysis: no protein, fat'));
        $this->assertSame('العنصر نفسه مختار من قبل لـعدس (raw)', MatchingDraft::hintAr('same USDA item already picked for عدس (raw): choose another or drop this row'));
        $this->assertSame('SR Legacy', MatchingDraft::sourceLabel('usda_fdc_sr_legacy'));
        $this->assertSame('الطاقة', MatchingDraft::nutrientsAr('energy'));
    }
}
