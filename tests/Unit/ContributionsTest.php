<?php

namespace Tests\Unit;

use App\Services\Nutrition\Contributions;
use PHPUnit\Framework\TestCase;

/** "من أين؟" (step 4): what the site shows of each ingredient's share. */
class ContributionsTest extends TestCase
{
    /** A musabbaha's fat, for the whole recipe of 4 servings (the plan's example). */
    private const FAT = ['fat_g' => [
        ['label' => 'طحينة', 'amount' => 56.0, 'after' => false],
        ['label' => 'زيت زيتون', 'amount' => 28.0, 'after' => true],
        ['label' => 'حمص', 'amount' => 13.2, 'after' => false],
        ['label' => 'كمون', 'amount' => 0.6, 'after' => false],
        ['label' => 'ليمون', 'amount' => 0.2, 'after' => false],
    ]];

    public function test_the_kitchen_shows_grams_and_shares_with_the_small_ones_together(): void
    {
        $rows = Contributions::rows(self::FAT, 1 / 4, ranksOnly: false)['fat_g'];

        $this->assertSame(['طحينة', 'زيت زيتون على الوجه', 'حمص', 'مكوّنات أخرى'], array_column($rows, 'label'));
        $this->assertEqualsWithDelta(14.0, $rows[0]['amount'], 0.001);
        $this->assertEqualsWithDelta(7.0, $rows[1]['amount'], 0.001);
        $this->assertSame([57, 29, 13, 1], array_column($rows, 'pct'));
        $this->assertEqualsWithDelta(24.5, array_sum(array_column($rows, 'amount')), 0.001, 'they add up to the plate');
    }

    public function test_a_restaurant_shows_the_order_only(): void
    {
        $rows = Contributions::rows(self::FAT, 1 / 4, ranksOnly: true)['fat_g'];

        $this->assertSame(['طحينة', 'زيت زيتون على الوجه', 'حمص'], array_column($rows, 'label'));
        $this->assertSame([null, null, null], array_column($rows, 'amount'));
        $this->assertSame([null, null, null], array_column($rows, 'pct'));
    }

    public function test_at_most_three_are_named(): void
    {
        $lines = array_map(fn (int $i) => ['label' => "م$i", 'amount' => 10.0, 'after' => false], range(1, 5));
        $rows = Contributions::rows(['sodium_mg' => $lines], 1.0, ranksOnly: false)['sodium_mg'];

        $this->assertSame(['م1', 'م2', 'م3', 'مكوّنات أخرى'], array_column($rows, 'label'));
        $this->assertSame(20.0, $rows[3]['amount']);
    }
}
