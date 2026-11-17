<?php

namespace Tests\Feature;

use App\Enums\HealthCondition;
use App\Models\User;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConsultationFixtures;
use Tests\TestCase;

/**
 * Step 3 of the medical plan: the liver conditions follow their references, and the people who
 * chose the old "الكبد الوبائي أو التليّف" are asked to pick again (decision 8a).
 */
class LiverUpdateTest extends TestCase
{
    use ConsultationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function withOldChoice(): User
    {
        $user = $this->client();
        $user->profile->update(['health_conditions' => ['liver_cirrhosis', 'diabetes']]);

        return $user->fresh();
    }

    private function bellTitles(User $user): array
    {
        return DB::table('notifications')->where('notifiable_id', $user->id)->pluck('data')
            ->map(fn ($d) => json_decode($d, true)['title'])->all();
    }

    public function test_the_old_choice_is_asked_again_on_every_page_and_once_in_the_bell(): void
    {
        $user = $this->withOldChoice();
        $this->actingAs($user);

        $this->get(route('home'))->assertOk()
            ->assertSee('حدّثنا حالات الكبد لتكون أدق')
            ->assertSee(route('site.liver'), false);
        $this->get(route('site.criteria'))->assertOk();
        $this->assertSame(['حدّثنا حالات الكبد لتكون أدق'], $this->bellTitles($user), 'one notice, not one a page');

        // Until a choice is made: the old choice brings no limits.
        $this->assertNotContains('الكبد الوبائي أو التليّف', app(PersonalCriteria::class)->for($user)->pendingConditions);

        $this->get(route('site.liver'))->assertOk()
            ->assertSee('التهاب كبد فيروسي حاد (A أو E)')
            ->assertSee('التهاب كبد فيروسي مزمن (B أو C)')
            ->assertSee('تليّف الكبد')
            ->assertSee('لا أعرف بعد');
    }

    public function test_picking_replaces_the_old_choice(): void
    {
        $user = $this->withOldChoice();
        $this->actingAs($user);

        $this->put(route('site.liver.update'), [])->assertSessionHasErrors('choice');
        $this->put(route('site.liver.update'), ['choice' => 'fatty_liver'])->assertSessionHasErrors('choice');

        $this->put(route('site.liver.update'), ['choice' => 'hepatitis_acute'])->assertRedirect(route('site.criteria'));
        $this->assertSame(['diabetes', 'hepatitis_acute'], $user->fresh()->profile->health_conditions);
        $this->assertTrue(HealthCondition::HepatitisAcute->isTemporary(), 'asked about every four weeks');

        $this->get(route('home'))->assertDontSee('حدّثنا حالات الكبد لتكون أدق');
        $this->get(route('site.liver'))->assertRedirect(route('site.criteria'));
    }

    public function test_i_dont_know_yet_just_drops_the_old_choice(): void
    {
        $user = $this->withOldChoice();
        $this->actingAs($user);

        $this->put(route('site.liver.update'), ['choice' => 'unknown'])->assertRedirect(route('site.criteria'));
        $this->assertSame(['diabetes'], $user->fresh()->profile->health_conditions);
    }

    public function test_with_a_nutritionist_the_nutritionist_updates_it(): void
    {
        $user = $this->withOldChoice();
        $this->linked($user, $this->nutritionist());
        $this->actingAs($user);

        $this->get(route('home'))->assertOk()
            ->assertSee('سيحدّثها أخصائيّك')
            ->assertDontSee(route('site.liver'), false);
        $this->put(route('site.liver.update'), ['choice' => 'cirrhosis']);
        $this->assertContains('liver_cirrhosis', $user->fresh()->profile->health_conditions, 'locked while linked');
    }
}
