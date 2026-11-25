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

    /** The student: university 3 days a week, weights 3 × 60 min and cardio 3 × 15 min, the same 3 days. */
    private const STUDENT = [
        'daily_life' => 'seated', 'work_days' => '3',
        'training_days' => '3', 'sports' => ['weights', 'cardio'],
        'sport_days' => ['weights' => '3', 'cardio' => '3'], 'sport_minutes' => ['weights' => '60', 'cardio' => '15'],
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
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'lose', 'training_days' => '0']);
        $this->post('/onboarding/health', []);
        $this->get('/onboarding/done')->assertOk()
            ->assertSee('لهدف إنقاص الوزن')
            ->assertSee('وزنك ضمن الطبيعي لطولك');

        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'gain', 'training_days' => '0']);
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
            ->assertSeeInOrder(['تمرينك', 'خفيف', '32', 'دقيقة في اليوم', '3–5', 'غ كربوهيدرات لكل كغ', '3.4'])  // acsm_2016
            ->assertSee('الاستشارة الأولى مجانية');

        $profile = $this->user->fresh()->profile;
        $this->assertSame([3, 'weights', 3, 60], [$profile->work_days, $profile->exercises[0]['type'], $profile->exercises[0]['times'], $profile->exercises[0]['minutes']]);
    }

    public function test_the_day_and_the_exercise_are_two_questions(): void
    {
        $this->post('/onboarding/body', ['sex' => 'male', 'birth_year' => now()->year - 24, 'height_cm' => '170', 'weight_kg' => '70']);
        $this->get('/onboarding/activity')->assertOk()
            ->assertSeeInOrder(['نهارك بلا رياضة', 'بالبيت أغلب الوقت', 'شغل جسدي', 'كم يوم بالأسبوع', 'الرياضة والتمرين', 'كم يوم بالأسبوع بتتمرّن؟', 'ولا يوم', 'شو بتعمل بهالأيام؟', 'هدفك'])
            ->assertSee('مشي عادي')->assertSee('حديد عادي')->assertSee('رياضات تانية')
            // No table, example or "add a row" any more.
            ->assertDontSee('شوف مثال')->assertDontSee('أضف رياضة');

        // A job needs its days a week; at home it does not. "ولا يوم" is no exercise, whatever was ticked.
        $this->post('/onboarding/activity', ['daily_life' => 'seated', 'training_days' => '0', 'goal' => 'maintain'])->assertSessionHasErrors('work_days');
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'maintain'])->assertSessionHasErrors('training_days');
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'work_days' => '5', 'goal' => 'maintain', 'training_days' => '0', 'sports' => ['walk']])
            ->assertRedirect('/onboarding/health');
        $profile = $this->user->fresh()->profile;
        $this->assertSame([null, null, null], [$profile->work_days, $profile->exercises, $profile->training_days]);

        // Amer: walking two days an hour, in Arabic digits too.
        $walk = ['daily_life' => 'home', 'goal' => 'maintain', 'training_days' => '٢', 'sports' => ['walk'], 'sport_days' => ['walk' => '٢'], 'sport_minutes' => ['walk' => '٦٠']];
        $this->post('/onboarding/activity', $walk)->assertRedirect('/onboarding/health');
        $this->assertSame([['type' => 'walk', 'times' => 2, 'minutes' => 60]], $this->user->fresh()->profile->exercises);

        // Amer: 2339 kcal (CriteriaCalculatorTest).
        $this->post('/onboarding/health', []);
        $this->get('/onboarding/done')->assertOk()->assertSee('2,339');
    }

    public function test_each_sport_needs_its_days_and_minutes_within_the_training_days(): void
    {
        $this->post('/onboarding/body', ['sex' => 'male', 'birth_year' => now()->year - 24, 'height_cm' => '170', 'weight_kg' => '70']);
        $base = ['daily_life' => 'home', 'goal' => 'maintain', 'training_days' => '3'];

        $this->post('/onboarding/activity', $base)->assertSessionHasErrors(['sports' => 'اختار شو بتعمل بأيام تمرينك.']);
        $this->post('/onboarding/activity', $base + ['sports' => ['flying']])->assertSessionHasErrors('sports.0');
        $this->post('/onboarding/activity', $base + ['sports' => ['walk']])
            ->assertSessionHasErrors(['sport_days.walk', 'sport_minutes.walk']);
        // More days than the training days, or a minute count we do not offer.
        $this->post('/onboarding/activity', $base + ['sports' => ['walk'], 'sport_days' => ['walk' => '5'], 'sport_minutes' => ['walk' => '37']])
            ->assertSessionHasErrors(['sport_days.walk', 'sport_minutes.walk']);
        // At most five sports.
        $six = ['walk', 'brisk_walk', 'jog', 'run', 'weights', 'gym'];
        $this->post('/onboarding/activity', $base + ['sports' => $six, 'sport_days' => array_fill_keys($six, '1'), 'sport_minutes' => array_fill_keys($six, '30')])
            ->assertSessionHasErrors('sports');
        // Three training days, but the sports cover two.
        $this->post('/onboarding/activity', $base + ['sports' => ['walk'], 'sport_days' => ['walk' => '2'], 'sport_minutes' => ['walk' => '30']])
            ->assertSessionHasErrors(['training_days' => 'قلت إنك بتتمرّن 3 أيام، ووزّعت رياضاتك على 2 بس. زيد أيام رياضة، أو قلّل أيام التمرين.']);
        $this->assertNull($this->user->fresh()->profile->exercises);
    }

    public function test_the_owners_example_is_two_sports_on_four_days(): void
    {
        // Three days of jogging and brisk walking, half an hour each, and a fourth day of an hour's
        // walk: four training days, jogging 3 × 30, brisk walking 4 days at about 45 minutes.
        $this->post('/onboarding/body', ['sex' => 'male', 'birth_year' => now()->year - 24, 'height_cm' => '170', 'weight_kg' => '70']);
        $this->post('/onboarding/activity', [
            'daily_life' => 'home', 'goal' => 'maintain', 'training_days' => '4', 'sports' => ['jog', 'brisk_walk'],
            'sport_days' => ['jog' => '3', 'brisk_walk' => '4'], 'sport_minutes' => ['jog' => '30', 'brisk_walk' => '45'],
        ])->assertRedirect('/onboarding/health');

        $profile = $this->user->fresh()->profile;
        $this->assertSame([['type' => 'jog', 'times' => 3, 'minutes' => 30], ['type' => 'brisk_walk', 'times' => 4, 'minutes' => 45]], $profile->exercises);
        $this->assertSame(4, $profile->training_days);
        $this->assertStringContainsString('4 أيام تمرين بالأسبوع', $profile->activitySummary());

        // Coming back: the cards are chosen, with their days and minutes.
        $this->get('/onboarding/activity')->assertOk()
            ->assertSee('\u0022days\u0022:4', false)
            ->assertSee('\u0022chosen\u0022:[\u0022jog\u0022,\u0022brisk_walk\u0022]', false)
            ->assertSee('\u0022brisk_walk\u0022:{\u0022days\u0022:4,\u0022minutes\u0022:45}', false);
    }

    public function test_rows_from_before_open_as_one_card_a_sport(): void
    {
        // Saved with the old rows: walking 3 days × 30 and one day × 60, four days in all.
        $this->user->profile()->create([
            'sex' => 'male', 'birth_year' => now()->year - 24, 'height_cm' => 170, 'weight_kg' => 70,
            'daily_life' => 'home', 'goal' => 'maintain', 'training_days' => 4,
            'exercises' => [['type' => 'jog', 'times' => 3, 'minutes' => 30], ['type' => 'brisk_walk', 'times' => 3, 'minutes' => 30], ['type' => 'brisk_walk', 'times' => 1, 'minutes' => 60]],
        ]);

        $this->get('/onboarding/activity')->assertOk()
            ->assertSee('\u0022chosen\u0022:[\u0022jog\u0022,\u0022brisk_walk\u0022]', false)
            ->assertSee('\u0022brisk_walk\u0022:{\u0022days\u0022:4,\u0022minutes\u0022:45}', false);
    }

    public function test_two_sports_on_different_days_bring_the_sport_template(): void
    {
        // Walking two days and the gym two other days: four training days, the sport template.
        $this->post('/onboarding/body', ['sex' => 'male', 'birth_year' => now()->year - 24, 'height_cm' => '170', 'weight_kg' => '70']);
        $sports = ['sports' => ['walk', 'gym'], 'sport_days' => ['walk' => '2', 'gym' => '2'], 'sport_minutes' => ['walk' => '45', 'gym' => '60']];
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'maintain', 'training_days' => '4'] + $sports);
        $this->post('/onboarding/health', []);
        $this->assertSame('sport', app(PersonalCriteria::class)->for($this->user->fresh())->template['key']);

        // The same two sports on the same two days: two training days, the goal's template.
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'maintain', 'training_days' => '2'] + $sports);
        $this->assertSame('balanced', app(PersonalCriteria::class)->for($this->user->fresh())->template['key']);
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
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'lose', 'training_days' => '0']);

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
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'maintain', 'training_days' => '0']);
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
        $this->post('/onboarding/activity', ['daily_life' => 'home', 'goal' => 'maintain', 'training_days' => '0']);

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
