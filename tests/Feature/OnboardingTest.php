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

    /** The design's example person: man, 30, 178 cm, 80 kg, moderately active, losing weight. */
    private function completeSetUp(array $health = []): void
    {
        $this->post('/onboarding/body', ['sex' => 'male', 'birth_year' => now()->year - 30, 'height_cm' => '178', 'weight_kg' => '80'])
            ->assertRedirect('/onboarding/activity');
        $this->post('/onboarding/activity', ['activity_level' => 'moderate', 'goal' => 'lose'])
            ->assertRedirect('/onboarding/health');
        $this->post('/onboarding/health', $health)->assertRedirect('/onboarding/done');
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

        $this->get('/onboarding/done')->assertOk()
            ->assertSee('2,240')
            ->assertSee('672')
            ->assertSee('الاستشارة الأولى مجانية');
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
        $this->post('/onboarding/activity', ['activity_level' => 'lazy', 'goal' => 'fly'])
            ->assertSessionHasErrors(['activity_level', 'goal']);
    }

    public function test_health_data_needs_consent_is_encrypted_and_can_be_deleted(): void
    {
        $this->post('/onboarding/body', ['sex' => 'male', 'birth_year' => 1990, 'height_cm' => 178, 'weight_kg' => 80]);
        $this->post('/onboarding/activity', ['activity_level' => 'moderate', 'goal' => 'lose']);

        $this->post('/onboarding/health', ['conditions' => ['diabetes'], 'allergies' => ['sesame']])
            ->assertSessionHasErrors('consent');
        $this->post('/onboarding/health', ['conditions' => ['pregnant_or_breastfeeding'], 'consent' => '1'])
            ->assertSessionHasErrors('conditions.0');

        $this->post('/onboarding/health', ['conditions' => ['diabetes'], 'allergies' => ['sesame'], 'consent' => '1'])
            ->assertRedirect('/onboarding/done');

        $profile = $this->user->fresh()->profile;
        $this->assertSame(['diabetes'], $profile->health_conditions);
        $this->assertNotNull($profile->health_consent_at);
        $raw = DB::connection('central')->table('user_profiles')->value('health_conditions');
        $this->assertStringNotContainsString('diabetes', $raw, 'stored encrypted');

        $sheet = app(PersonalCriteria::class)->for($this->user->fresh());
        $this->assertSame(60.0, $sheet->get('meal_carbohydrate_g')->value);
        $this->assertSame(['sesame'], $sheet->allergens);

        $this->delete('/account/health')->assertSessionHas('status');
        $profile->refresh();
        $this->assertNull($profile->health_conditions);
        $this->assertNull($profile->allergies);
        $this->assertNull($profile->health_consent_at);
        $this->assertSame(80.0, $profile->weight_kg, 'body data stays');
    }

    public function test_under_18_gets_no_personal_calculation(): void
    {
        $this->post('/onboarding/body', ['sex' => 'female', 'birth_year' => now()->year - 16, 'height_cm' => 160, 'weight_kg' => 50]);
        $this->post('/onboarding/activity', ['activity_level' => 'light', 'goal' => 'maintain']);
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

    public function test_a_person_changes_a_criterion_and_puts_it_back(): void
    {
        $this->completeSetUp();

        $this->put('/account/criteria/sodium_mg', ['value' => '١٨٠٠'])->assertSessionHas('status');
        $sodium = app(PersonalCriteria::class)->for($this->user->fresh())->get('sodium_mg');
        $this->assertSame(1800.0, $sodium->value);
        $this->assertSame(CriterionSource::User, $sodium->source);
        $this->assertSame(2000.0, $sodium->defaultValue);

        $this->put('/account/criteria/sodium_mg', ['value' => '99999'])->assertSessionHasErrors('value');
        $this->put('/account/criteria/not_a_criterion', ['value' => '1'])->assertNotFound();

        // A criterion with no default, switched on by the person.
        $this->put('/account/criteria/meal_carbohydrate_g', ['value' => '70']);
        $this->assertSame(70.0, app(PersonalCriteria::class)->for($this->user->fresh())->get('meal_carbohydrate_g')->value);

        $this->delete('/account/criteria/sodium_mg')->assertSessionHas('status');
        $this->assertSame(CriterionSource::Default, app(PersonalCriteria::class)->for($this->user->fresh())->get('sodium_mg')->source);

        $this->assertSame(3, UserCriterionChange::where('user_id', $this->user->id)->count(), 'set, set, reset are logged');
        $this->get('/account/criteria')->assertOk()->assertSee('معاييري')->assertSee('كربوهيدرات الوجبة');
    }

    public function test_a_nutritionist_value_wins_and_is_locked_for_the_person(): void
    {
        $this->completeSetUp();
        $nutritionist = User::forceCreate(['name' => 'د. ريم', 'email' => 'reem@example.com', 'password' => 'x-secret-1', 'is_active' => true]);
        $criteria = app(PersonalCriteria::class);

        $this->put('/account/criteria/protein_g', ['value' => 110]);
        $criteria->setByNutritionist($this->user, 'protein_g', 120, $nutritionist, 'حسب تحاليلك');

        $protein = $criteria->for($this->user->fresh())->get('protein_g');
        $this->assertSame(120.0, $protein->value);
        $this->assertSame(CriterionSource::Nutritionist, $protein->source);
        $this->assertSame('حسب تحاليلك', $protein->note);

        $this->put('/account/criteria/protein_g', ['value' => 90])->assertSessionHas('error');
        $this->delete('/account/criteria/protein_g')->assertSessionHas('error');
        $this->assertSame(120.0, $criteria->for($this->user->fresh())->get('protein_g')->value);
        $this->get('/account/criteria')->assertSee('يغيّره أخصائيّك فقط');

        // Ending the link: the person's own value comes back.
        $criteria->clearNutritionist($this->user, $nutritionist);
        $this->assertSame(110.0, $criteria->for($this->user->fresh())->get('protein_g')->value);
    }

    public function test_the_health_step_offers_digestion_conditions_with_the_others(): void
    {
        $this->post('/onboarding/body', ['sex' => 'male', 'birth_year' => 1990, 'height_cm' => 178, 'weight_kg' => 80]);
        $this->post('/onboarding/activity', ['activity_level' => 'moderate', 'goal' => 'maintain']);

        $this->get('/onboarding/health')->assertOk()
            ->assertSee('الهضم والدسم')
            ->assertSee('استئصال المرارة')
            ->assertSee('عدم تحمّل الدسم')
            ->assertSee('فقر الدم')
            ->assertDontSee('حامل أو مرضعة');

        $this->post('/onboarding/health', ['conditions' => ['gallbladder_removed', 'anemia'], 'consent' => '1'])
            ->assertRedirect('/onboarding/done');

        $sheet = app(PersonalCriteria::class)->for($this->user->fresh());
        $this->assertSame(20.0, $sheet->get('meal_fat_g')->value);
        $this->assertSame(8.0, $sheet->get('iron_mg')->value);
        $this->get('/account/criteria')->assertSee('دهون الوجبة')->assertSee('الحديد');
    }
}
