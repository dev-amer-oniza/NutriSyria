<?php

namespace Tests\Feature;

use App\Support\Site\ChainPatterns;
use Tests\TestCase;

class SiteHomeTest extends TestCase
{
    public function test_home_page_renders_the_public_site(): void
    {
        $this->withoutVite()
            ->get('/')
            ->assertOk()
            ->assertSee('اعرف ما في وجبتك', false)
            ->assertSee('class="ns-dish"', false)
            ->assertSee(config('site.fit_levels.3'))
            ->assertSee(config('site.verification.estimated.label'));
    }

    public function test_site_links_are_routes_in_web_php(): void
    {
        $this->withoutVite()
            ->get(route('site.meals'))
            ->assertOk()
            ->assertSee('الوجبات');

        $this->withoutVite()
            ->get('/')
            ->assertDontSee('href="#"', false);
    }

    public function test_chain_patterns_parse_colour_shape_and_distance(): void
    {
        $tiles = ChainPatterns::get('step-1')['tiles'];
        $this->assertSame([['class' => 'ns-tile ns-tile--terracotta ns-tile--q-bl', 'd' => null]], $tiles);

        $hero = ChainPatterns::get('hero');
        $this->assertSame(7, $hero['cols']);
        $this->assertCount(63, $hero['tiles']);
        $this->assertSame(['class' => 'ns-tile ns-tile--empty', 'd' => null], $hero['tiles'][0]);
        $this->assertSame(['class' => 'ns-tile ns-tile--q-bl', 'd' => 3], $hero['tiles'][1]);
    }

    public function test_dish_cover_is_stable_for_the_same_key(): void
    {
        $this->assertSame(ChainPatterns::dishCover('mujaddara'), ChainPatterns::dishCover('mujaddara'));
        $this->assertMatchesRegularExpression('/^dish-[1-4]$/', ChainPatterns::dishCover(42));
    }
}
