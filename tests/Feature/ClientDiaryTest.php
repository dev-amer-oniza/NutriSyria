<?php

namespace Tests\Feature;

use App\Enums\MealType;
use App\Filament\Clusters\Consultations\Resources\Clients\Pages\ViewClient;
use App\Models\MealLog;
use App\Models\User;
use App\Models\WeightLog;
use App\Services\Consultations\ConsultationService;
use App\Services\Daily\ClientDiary;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConsultationFixtures;
use Tests\TestCase;

/**
 * The client's "يومي" in the nutritionist's file: two weeks of meals against the need, and
 * the last weighings. Only the linked nutritionist reads it (the file's own guard).
 */
class ClientDiaryTest extends TestCase
{
    use ConsultationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 20:00', 'Asia/Damascus'));
    }

    private function ate(User $client, string $day, string $name, ?float $kcal, MealType $type = MealType::Lunch): void
    {
        $log = new MealLog;
        $log->forceFill([
            'user_id' => $client->id, 'eaten_on' => $day, 'eaten_at' => CarbonImmutable::parse($day.' 14:00', 'Asia/Damascus'),
            'meal_type' => $type, 'name' => $name, 'amount' => 1,
            'energy_kcal' => $kcal, 'protein_g' => $kcal ? 20 : null, 'carbohydrate_g' => $kcal ? 60 : null, 'fat_g' => $kcal ? 15 : null,
        ])->save();
    }

    private function weighed(User $client, string $day, float $kg): void
    {
        WeightLog::query()->insert(['user_id' => $client->id, 'weighed_on' => $day, 'weight_kg' => $kg]);
    }

    public function test_the_nutritionist_reads_two_weeks_of_meals_and_the_weighings(): void
    {
        $client = $this->client();
        $reem = $this->nutritionist();
        $link = $this->linked($client, $reem);

        $this->ate($client, '2026-11-25', 'مجدرة', 558);
        $this->ate($client, '2026-11-25', 'شاورما دجاج', null, MealType::Dinner);
        $this->ate($client, '2026-11-23', 'فول مدمس', 337, MealType::Breakfast);
        $this->ate($client, '2026-11-01', 'أقدم من أسبوعين', 900);
        $this->weighed($client, '2026-11-11', 79.0);
        $this->weighed($client, '2026-11-25', 77.6);

        $this->actingAs($reem->user);
        Filament::setCurrentPanel('admin');

        $this->get(ViewClient::getUrl(['record' => $link]))->assertOk()
            ->assertSee('سجل الأكل والوزن')
            ->assertSee('2 من 14')                 // two days with meals counted
            ->assertSee('448')                     // their mean: (558 + 337) / 2
            ->assertSee('الأربعاء 25 تشرين التاني')
            ->assertSee('مجدرة')
            ->assertSee('شاورما دجاج')
            ->assertSee('1 غير محسوبة')
            ->assertSee('ما سجّل شيئاً')
            ->assertSee('77.6 كغ')
            ->assertSee('-1.4 كغ')
            ->assertDontSee('أقدم من أسبوعين');
    }

    public function test_the_diary_costs_two_queries_whatever_was_eaten(): void
    {
        $client = $this->client();
        foreach (range(1, 14) as $i) {
            $this->ate($client, CarbonImmutable::parse('2026-11-25')->subDays($i % 14)->toDateString(), 'وجبة '.$i, 300);
        }

        DB::enableQueryLog();
        $diary = app(ClientDiary::class)->for($client);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(2, $queries);
        $this->assertCount(14, $diary->days);
        $this->assertSame(14, $diary->loggedDays());
    }

    public function test_an_ended_link_shows_no_diary(): void
    {
        $client = $this->client();
        $reem = $this->nutritionist();
        $link = $this->linked($client, $reem);
        $this->ate($client, '2026-11-25', 'مجدرة', 558);
        app(ConsultationService::class)->end($client, $link);

        $this->actingAs($reem->user);
        Filament::setCurrentPanel('admin');
        $this->get(ViewClient::getUrl(['record' => $link]))->assertNotFound();
    }
}
