<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The public "المصادر" page: where the food numbers come from, and our own choices. */
class SourcesPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sources_page_shows_the_data_and_our_own_choices(): void
    {
        $this->get(route('site.sources'))->assertOk()
            ->assertSee('من أين نأتي بأرقامنا وحدودنا')
            ->assertSee('id="data"', false)
            // Our own choices, from the config (decision 5a), +300 among them (4a).
            ->assertSee('اختيارات منّا، لا من مرجع طبي')
            ->assertSee('وجبة المطعم تُقارن بـ30% من احتياجك اليومي')
            ->assertSee('لزيادة الوزن نضيف 300 سعرة')
            ->assertSee('لا للحوامل ولا لأصحاب الحالات الصحية')
            ->assertDontSee('id="medical"', false)
            ->assertDontSee('قيد المراجعة');
    }

    public function test_the_footer_link_opens_the_page_not_the_placeholder(): void
    {
        $this->get(route('home'))->assertSee(route('site.sources'), false);
        $this->get(route('site.sources'))->assertDontSee('قريباً');
    }
}
