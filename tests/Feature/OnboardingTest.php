<?php

namespace Tests\Feature;

use App\Enums\CriterionSource;
use App\Models\User;
use App\Models\UserCriterionChange;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::forceCreate([
            'name' => 'سامر', 'email' => 'samer@example.com', 'password' => 'olive-oil-9',
            'is_active' => true, 'email_verified_at' => now(),
        ]);
        $this->actingAs($this->user);
    }

    /** The student: university 3 days a week, weights 3 × 60 min and cardio 3 × 15 min. */
    private const STUDENT = [
        'daily_life' => 'seated', 'work_days' => '3',
        'exercises' => [['type' => 'weights', 'times' => '3', 'minutes' => '60'], ['type' => 'cardio', 'times' => '3', 'minutes' => '15']],
    ];

    /** The student (CriteriaCalculatorTest): man, 24, 170 cm, 68 kg, losing weight. */
    private function completeSetUp(array $health = []): void
    {
        $this->post('/onboarding/body', ['sex' => 'male', 'birth_year' => now()->year - 24, 'height_cm' => '170', 'weight_kg' => '68'])
            ->assertRedirect('/onboarding/activity');
        $this->post('/onboarding/activity', self::STUDENT + ['goal' => 'lose'])
            ->assertRedirect('/onboarding/health');
        $this->post('/onboarding/health', $health)->assertRedirect('/onboarding/done');
    }

    public function test_a_normal_weight_may_lose_and_the_surplus_counts(): void
    {
        // 62 kg at 178 cm: BMI 19.6. The deficit applies, told gently (decision 2a).
        $this->post('/onboarding/body', ['sex' => 'male', 'birth_year' => now()->year - 30, 'height_cm' => '178', 'weight_kg' => '62']);
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'lose']);
        $this->post('/onboarding/health', []);
        $this->get('/onboarding/done')->assertOk()
            ->assertSee('لهدف إنقاص الوزن')
            ->assertSee('وزنك ضمن الطبيعي لطولك');

        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'gain']);
        $this->get('/onboarding/done')->assertOk()
            ->assertSee('لهدف زيادة الوزن (رقم اختيار منّا')
            ->assertSee('للمعلومة');                                // the total sugars
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        auth()->logout();

        $this->get('/onboarding/body')->assertRedirect('/login');
        $this->get('/account/criteria')->assertRedirect('/login');
    }

    public function test_the_three_steps_give_the_daily_needs(): void
    {
        $this->get('/onboarding/body')->assertOk()->assertSee('عرّفنا عليك');
        $this->completeSetUp();

        $profile = $this->user->fresh()->profile;
        $this->assertNotNull($profile->completed_at);
        $this->assertNull($profile->health_consent_at);

        // BMR 1628; the day × 1.456 = 2369; + 88 of exercise = 2457; − 500 = 1957 (a meal: 587).
        $this->get('/onboarding/done')->assertOk()
            ->assertSeeInOrder(['كيف حسبناها', '1,628', '1.456', '2,369', 'رياضتك', '88', '2,457', 'لهدف إنقاص الوزن', '500'])
            ->assertSee('1,957')
            ->assertSee('587')
            ->assertSee('النشاط الرياضي')                              // training 3 days a week
            ->assertSee('الاستشارة الأولى مجانية');

        $profile = $this->user->fresh()->profile;
        $this->assertSame([3, 'weights', 3, 60], [$profile->work_days, $profile->exercises[0]['type'], $profile->exercises[0]['times'], $profile->exercises[0]['minutes']]);
    }

    public function test_the_day_and_the_exercise_are_two_questions(): void
    {
        $this->post('/onboarding/body', ['sex' => 'male', 'birth_year' => now()->year - 24, 'height_cm' => '170', 'weight_kg' => '70']);
        $this->get('/onboarding/activity')->assertOk()
            ->assertSeeInOrder(['نهارك بلا رياضة', 'بالبيت أغلب الوقت', 'شغل جسدي', 'كم يوم بالأسبوع', 'الرياضة والتمرين', 'هدفك'])
            ->assertSee('مشي عادي')->assertSee('حديد عادي');

        // A job needs its days a week; at home it does not, and a blank exercise row is no exercise.
        $this->post('/onboarding/activity', ['daily_life' => 'seated', 'goal' => 'maintain'])->assertSessionHasErrors('work_days');
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'work_days' => '5', 'goal' => 'maintain', 'exercises' => [['type' => '', 'times' => '', 'minutes' => '']]])
            ->assertRedirect('/onboarding/health');
        $profile = $this->user->fresh()->profile;
        $this->assertSame([null, null], [$profile->work_days, $profile->exercises]);

        // At most three sports, each with its times and minutes, in Arabic digits too.
        $sport = ['type' => 'walk', 'times' => '٢', 'minutes' => '٦٠'];
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'maintain', 'exercises' => [$sport, $sport, $sport, $sport]])->assertSessionHasErrors('exercises');
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'maintain', 'exercises' => [['type' => 'flying', 'times' => '0', 'minutes' => '1']]])
            ->assertSessionHasErrors(['exercises.0.type', 'exercises.0.times', 'exercises.0.minutes']);
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'maintain', 'exercises' => [$sport]])->assertRedirect('/onboarding/health');
        $this->assertSame([['type' => 'walk', 'times' => 2, 'minutes' => 60]], $this->user->fresh()->profile->exercises);

        // Amer: 2339 kcal (CriteriaCalculatorTest).
        $this->post('/onboarding/health', []);
        $this->get('/onboarding/done')->assertOk()->assertSee('2,339')->assertDontSee('رياضتك، بمعدّل اليوم</span> <b>+ <span class="ns-num">0', false);
    }

    public function test_people_from_before_answer_the_activity_step_again(): void
    {
        // The old single level is gone: a profile without the day goes back to the activity step.
        $this->user->profile()->create(['sex' => 'male', 'birth_year' => now()->year - 30, 'height_cm' => 178, 'weight_kg' => 80, 'goal' => 'lose', 'completed_at' => now()]);

        $this->get('/onboarding/done')->assertRedirect('/onboarding/activity');
        $this->get('/account/criteria')->assertRedirect();
        $this->get('/')->assertSee('أكمل ملفك');
    }

    public function test_arabic_digits_and_a_comma_are_read_as_numbers(): void
    {
        $this->post('/onboarding/body', ['sex' => 'female', 'birth_year' => '١٩٩٤', 'height_cm' => '١٦٥', 'weight_kg' => '٦٢٫٥'])
            ->assertRedirect('/onboarding/activity');

        $profile = $this->user->fresh()->profile;
        $this->assertSame(1994, $profile->birth_year);
        $this->assertSame(62.5, $profile->weight_kg);
    }

    public function test_a_step_cannot_be_skipped_and_bad_values_are_refused(): void
    {
        $this->get('/onboarding/health')->assertRedirect('/onboarding/body');
        $this->get('/onboarding/nonsense')->assertNotFound();

        $this->post('/onboarding/body', ['sex' => 'other', 'birth_year' => 1800, 'height_cm' => 20, 'weight_kg' => 900])
            ->assertSessionHasErrors(['sex', 'birth_year', 'height_cm', 'weight_kg']);
        $this->post('/onboarding/activity', ['daily_life' => 'lazy', 'goal' => 'fly'])
            ->assertSessionHasErrors(['daily_life', 'goal']);
    }

    public function test_allergies_need_consent_are_encrypted_and_can_be_deleted(): void
    {
        $this->post('/onboarding/body', ['sex' => 'male', 'birth_year' => 1990, 'height_cm' => 178, 'weight_kg' => 80]);
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'lose']);

        $this->post('/onboarding/health', ['allergies' => ['sesame']])->assertSessionHasErrors('consent');
        $this->post('/onboarding/health', ['allergies' => ['sesame'], 'consent' => '1'])->assertRedirect('/onboarding/done');

        $profile = $this->user->fresh()->profile;
        $this->assertSame(['sesame'], $profile->allergies);
        $this->assertNotNull($profile->health_consent_at);
        $this->assertStringNotContainsString('sesame', DB::table('user_profiles')->value('allergies'), 'stored encrypted');
        $this->assertSame(['sesame'], app(PersonalCriteria::class)->for($this->user->fresh())->allergens);

        $this->delete('/account/health')->assertSessionHas('status');
        $profile->refresh();
        $this->assertNull($profile->allergies);
        $this->assertNull($profile->health_consent_at);
        $this->assertSame(80.0, $profile->weight_kg, 'body data stays');
    }

    public function test_under_18_gets_no_personal_calculation(): void
    {
        $this->post('/onboarding/body', ['sex' => 'female', 'birth_year' => now()->year - 16, 'height_cm' => 160, 'weight_kg' => 50]);
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'maintain']);
        $this->post('/onboarding/health', []);

        $this->get('/onboarding/done')->assertOk()->assertSee('للبالغين');
        $this->assertNull(app(PersonalCriteria::class)->for($this->user->fresh()));
    }

    public function test_a_reminder_shows_until_the_set_up_is_done(): void
    {
        $this->get('/')->assertSee('أكمل ملفك');
        $this->completeSetUp();
        $this->get('/')->assertDontSee('أكمل ملفك');
    }

    public function test_a_person_reads_their_criteria_and_changes_them_through_the_profile_only(): void
    {
        $this->completeSetUp();

        $this->get('/account/criteria')->assertOk()
            ->assertSee('معاييري')
            ->assertSee('الصوديوم')
            ->assertSee('لتغييرها عدّل بياناتك')
            ->assertDontSee('هذا الرقم حدّده لي طبيبي')
            ->assertDontSee('تغيير القالب');
        $this->put('/account/criteria/sodium_mg', ['value' => '1800'])->assertNotFound();
        $this->put('/account/template', ['template' => 'balanced'])->assertNotFound();
    }

    public function test_a_nutritionist_value_wins_until_the_link_ends(): void
    {
        $this->completeSetUp();
        $nutritionist = User::forceCreate(['name' => 'د. ريم', 'email' => 'reem@example.com', 'password' => 'x-secret-1', 'is_active' => true]);
        $criteria = app(PersonalCriteria::class);

        $criteria->setByNutritionist($this->user, 'protein_g', 120, $nutritionist, 'حسب تحاليلك');

        $protein = $criteria->for($this->user->fresh())->get('protein_g');
        $this->assertSame(120.0, $protein->value);
        $this->assertSame(CriterionSource::Nutritionist, $protein->source);
        $this->assertSame('حسب تحاليلك', $protein->note);
        $this->get('/account/criteria')->assertSee('حدده أخصائيّك');

        // Ending the link: the computed value comes back.
        $criteria->clearNutritionist($this->user, $nutritionist);
        $this->assertSame(CriterionSource::Default, $criteria->for($this->user->fresh())->get('protein_g')->source);
        $this->assertSame(2, UserCriterionChange::where('user_id', $this->user->id)->count(), 'set and reset are logged');
    }

    public function test_the_health_step_offers_allergies_only_and_says_who_the_platform_is_for(): void
    {
        $this->post('/onboarding/body', ['sex' => 'female', 'birth_year' => 1990, 'height_cm' => 165, 'weight_kg' => 60]);
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'maintain']);

        $this->get('/onboarding/health')->assertOk()
            ->assertSee('حساسيات')
            ->assertSee('السمسم')
            ->assertSee('إن كنتِ حاملاً أو مرضعة')
            ->assertDontSee('السكري')
            ->assertDontSee('غسيل الكلى');
    }

    public function test_a_nutritionist_template_is_locked_for_the_person(): void
    {
        $this->completeSetUp();
        $nutritionist = User::forceCreate(['name' => 'د. ريم', 'email' => 'reem2@example.com', 'password' => 'x-secret-1', 'is_active' => true]);
        $criteria = app(PersonalCriteria::class);

        config(['nutrition_criteria.templates.gain.label' => 'قالب الأخصائي']);
        $criteria->setTemplateByNutritionist($this->user, 'gain', $nutritionist, 'حسب تحاليلك');
        $this->assertSame('gain', $criteria->for($this->user->fresh())->template['key']);

        $this->get('/account/criteria')->assertSee('قالب الأخصائي')->assertSee('اختاره أخصائيّك')->assertSee('ملاحظة أخصائيّك: حسب تحاليلك');

        // Back to what the answers give: training 3 days a week.
        $criteria->clearNutritionist($this->user, $nutritionist);
        $this->assertSame('sport', $criteria->for($this->user->fresh())->template['key']);
    }
}
