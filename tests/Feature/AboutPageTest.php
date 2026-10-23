<?php

namespace Tests\Feature;

use Tests\TestCase;

/** "About us": the idea, the team, and the two events with their photos. */
class AboutPageTest extends TestCase
{
    public function test_the_page_tells_the_idea_the_team_and_the_events(): void
    {
        $this->withoutVite();

        $this->get(route('site.about'))->assertOk()
            ->assertSee('من قرار صغير… يبدأ الأثر')
            ->assertSee('التغيير ليس زيادة دائماً')
            ->assertDontSee('ORIGIN EFFECT')                 // the concept's internal name stays internal
            ->assertSeeInOrder(['عامر عنيزه', 'Co-Founder &amp; CTO', 'ماريا نظام', 'Graphic Design'], false)
            ->assertSee('من مؤتمر Safire Tech')
            ->assertSee('تقدّم 160 مشروعاً، وتأهّل 16 منها')
            ->assertSee('من مسابقة ReRoot من JCI')
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
