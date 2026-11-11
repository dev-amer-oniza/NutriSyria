<?php

namespace Tests\Feature;

use Tests\TestCase;

/** "About us": the owner's seven sections, the team, and the two events with their photos. */
class AboutPageTest extends TestCase
{
    public function test_the_page_tells_the_story_in_its_sections_with_the_team_and_the_events(): void
    {
        $this->withoutVite();

        $this->get(route('site.about'))->assertOk()
            // The owner's seven sections, in his order, then the closing.
            ->assertSeeInOrder(['Who We Are', 'مرجعك الغذائي الأول في سوريا', 'Our Story', 'Our Purpose', 'What We Do',
                'Accuracy &amp; Transparency', 'Our Team', 'Join Us', 'من قرار صغير يبدأ الأثر'], false)
            ->assertSee('لذلك قررنا أن نبني المرجع الذي كنا نبحث عنه.')
            ->assertDontSee('ORIGIN EFFECT')                 // the concept's internal name stays internal
            ->assertDontSee('ns-chain', false)               // no tiles on this page (the owner's request)
            ->assertSeeInOrder(['عامر عنيزه', 'Co-Founder &amp; CTO', 'ماريا نظام', 'Art Director', 'كاتيا سليمان', 'Nutrition Data Reviewer'], false)
            ->assertSee('من مؤتمر Safierr Tech')
            ->assertSee('تقدّم 160 مشروعاً، وتأهّل 16 منها')
            ->assertSee('من مسابقة ReRoute من JCI')
            ->assertSee('images/about/safire-tech-640.webp', false)
            ->assertSee('images/about/reroot.jpg', false)
            ->assertDontSee('قريباً');
    }

    public function test_the_photos_are_published_small_and_without_metadata(): void
    {
        foreach (['safire-tech', 'safire-tech-640', 'reroot', 'reroot-420'] as $name) {
            foreach (['jpg', 'webp'] as $ext) {
                $file = public_path("images/about/{$name}.{$ext}");
                $this->assertFileExists($file);
                $this->assertLessThan(200 * 1024, filesize($file), "{$name}.{$ext} stays light");
            }
            $this->assertEmpty(@exif_read_data(public_path("images/about/{$name}.jpg"))['GPSLatitude'] ?? null);
        }
    }
}
