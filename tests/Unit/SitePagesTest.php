<?php

namespace Tests\Unit;

use App\Support\Site\Pages;
use Illuminate\Http\Request;
use Tests\TestCase;

class SitePagesTest extends TestCase
{
    public function test_a_list_is_cut_into_pages_that_keep_the_search(): void
    {
        $page = Pages::of(range(1, 50), Request::create('/meals', 'GET', ['q' => 'حمص', 'page' => 2]));

        $this->assertSame(range(25, 48), $page->items());
        $this->assertSame(3, $page->lastPage());
        $this->assertSame(50, $page->total());
        $this->assertStringContainsString('q=', $page->nextPageUrl());
        $this->assertStringContainsString('page=3', $page->nextPageUrl());
    }

    public function test_a_page_out_of_range_or_not_a_number_falls_back_to_a_real_page(): void
    {
        $this->assertSame(3, Pages::of(range(1, 50), Request::create('/meals', 'GET', ['page' => 99]))->currentPage());
        $this->assertSame(1, Pages::of(range(1, 50), Request::create('/meals', 'GET', ['page' => 'abc']))->currentPage());
        $this->assertSame(1, Pages::of(range(1, 50), Request::create('/meals', 'GET', ['page' => -4]))->currentPage());
        $this->assertSame(1, Pages::of([], Request::create('/meals', 'GET', ['page' => 2]))->currentPage());
    }
}
