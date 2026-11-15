<?php

namespace Tests\Unit;

use App\Services\Usda\FdcSearchFilter;
use PHPUnit\Framework\TestCase;

/**
 * Real USDA descriptions (chickpeas, lentils, hummus). The expected orders are what the
 * matching tool (usda_to_template.py score_description / fit_form) gives for the same words,
 * so the search page and the matching table agree.
 */
class FdcSearchFilterTest extends TestCase
{
    private function foods(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../Fixtures/fdc/legume-descriptions.json'), true);
    }

    private function ids(array $result): array
    {
        return array_column($result['shown'], 'fdcId');
    }

    private function reasonFor(array $result, int $fdcId): ?string
    {
        foreach ($result['dropped'] as $d) {
            if ($d['food']['fdcId'] === $fdcId) {
                return $d['reason'];
            }
        }

        return null;
    }

    public function test_boiled_chickpeas_without_salt_come_first_and_only_boiled_ones_show(): void
    {
        $result = (new FdcSearchFilter)->apply($this->foods(), 'chickpeas', 'boiled');

        $this->assertSame([173757, 173799], $this->ids($result));
        $this->assertSame('الشكل: معلّب', $this->reasonFor($result, 173800));
        $this->assertSame('الشكل: نيء', $this->reasonFor($result, 173756));
        $this->assertSame('ينقصها: chickpea', $this->reasonFor($result, 172420)); // lentils
    }

    public function test_every_word_must_be_there_and_the_order_is_the_tools(): void
    {
        $this->assertSame(
            [173757, 2644282, 173756, 173800, 2644288, 175206, 173801, 175250, 174288, 173799],
            $this->ids((new FdcSearchFilter)->apply($this->foods(), 'chickpeas')),
        );
        // "chickpeas, cooked": both words, so no raw or canned ones.
        $this->assertSame([173757, 173799], $this->ids((new FdcSearchFilter)->apply($this->foods(), 'chickpeas, cooked')));
    }

    public function test_raw_takes_dry_items_too_and_excluded_words_go(): void
    {
        $this->assertSame([2644283, 172420, 168427, 174284, 171577], $this->ids((new FdcSearchFilter)->apply($this->foods(), 'lentils', 'raw')));

        $result = (new FdcSearchFilter)->apply($this->foods(), 'lentils', 'cooked', FdcSearchFilter::excludeList('sprouted; soup'));
        $this->assertSame([172421, 175254], $this->ids($result));
        $this->assertSame('فيها «sprouted» (استبعدتها)', $this->reasonFor($result, 168428));
    }

    public function test_food_outside_a_syrian_kitchen_never_shows(): void
    {
        $foods = [...$this->foods(), ['fdcId' => 1, 'description' => 'Babyfood, dinner, chickpeas and rice', 'dataType' => 'SR Legacy']];

        $result = (new FdcSearchFilter)->apply($foods, 'chickpeas');

        $this->assertNotContains(1, $this->ids($result));
        $this->assertSame('خارج مطبخنا («babyfood»)', $this->reasonFor($result, 1));
    }

    public function test_one_source_only(): void
    {
        $result = (new FdcSearchFilter)->apply($this->foods(), 'chickpeas', null, [], ['Foundation']);

        $this->assertSame([2644282, 2644288], $this->ids($result));
        $this->assertSame('من مصدر غير مختار (SR Legacy)', $this->reasonFor($result, 173757));
    }

    public function test_an_unknown_form_means_any(): void
    {
        $this->assertCount(10, (new FdcSearchFilter)->apply($this->foods(), 'chickpeas', 'sideways')['shown']);
    }
}
