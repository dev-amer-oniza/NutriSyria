<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Enums\ConsultationStatus;
use App\Enums\CriterionSource;
use App\Filament\Clusters\Consultations\Resources\Clients\ClientResource;
use App\Filament\Clusters\Consultations\Resources\Clients\Pages\ListClients;
use App\Filament\Clusters\Consultations\Resources\Clients\Pages\ViewClient;
use App\Notifications\SiteNotification;
use App\Services\Consultations\ClientWorkspace;
use App\Services\Consultations\ConsultationService;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Concerns\ConsultationFixtures;
use Tests\TestCase;

class ClientWorkspaceTest extends TestCase
{
    use ConsultationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function signInAsNutritionist(\App\Models\User $nutritionist): void
    {
        $this->actingAs($nutritionist);
        Filament::setCurrentPanel('admin');
    }

    public function test_my_clients_lists_only_my_accepted_links(): void
    {
        $reem = $this->nutritionist();
        $mine = $this->linked($this->client(), $reem);
        $waiting = $this->pending($this->client('lina@example.com'), $reem, '+963944444444');
        $ended = $this->linked($this->client('huda@example.com'), $reem, '+963955555555');
        app(ConsultationService::class)->end($ended->user, $ended);
        $someoneElses = $this->linked($this->client('maya@example.com'), $this->nutritionist('omar@example.com'), '+963966666666');

        $this->signInAsNutritionist($reem->user);

        Livewire::test(ListClients::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$waiting, $ended, $someoneElses])
            ->assertSee('السكري (تحكّم بالكربوهيدرات)');
    }

    public function test_the_client_file_shows_the_health_record_and_criteria(): void
    {
        $reem = $this->nutritionist();
        $link = $this->linked($this->client(), $reem);
        $this->signInAsNutritionist($reem->user);

        $this->get(ViewClient::getUrl(['record' => $link]))
            ->assertOk()
            ->assertSee('السجل الصحي')
            ->assertSee('السكري')
            ->assertSee('الفول السوداني')
            ->assertSee('أريد خطة لإنقاص الوزن مع السكري.')
            ->assertSee('28.7 · زيادة وزن') // BMI: 78 / 1.65², the clinical class for the nutritionist
            ->assertSee('الوزن الصحي لطوله')
            ->assertSee('الصوديوم');
    }

    public function test_another_nutritionist_or_an_ended_link_is_not_found(): void
    {
        $link = $this->linked($this->client(), $this->nutritionist());
        $omar = $this->nutritionist('omar@example.com');
        $this->signInAsNutritionist($omar->user);

        $this->get(ViewClient::getUrl(['record' => $link]))->assertNotFound();

        $this->expectException(NotFoundHttpException::class);
        app(ClientWorkspace::class)->setCriterion($omar->user, $link, 'sodium_mg', 1500);
    }

    public function test_an_ended_link_closes_the_file(): void
    {
        $reem = $this->nutritionist();
        $link = $this->linked($this->client(), $reem);
        app(ConsultationService::class)->end($link->user, $link);
        $this->signInAsNutritionist($reem->user);

        $this->get(ViewClient::getUrl(['record' => $link]))->assertNotFound();
    }

    public function test_the_admin_does_not_read_client_files(): void
    {
        $link = $this->linked($this->client(), $this->nutritionist());
        $this->signInAs(Access::SUPER_ADMIN);

        $this->assertFalse(ClientResource::canAccess());
        $this->get(ViewClient::getUrl(['record' => $link]))->assertNotFound();
    }

    public function test_the_nutritionist_chooses_the_template_with_a_note_and_the_person_sees_it(): void
    {
        $reem = $this->nutritionist();
        $link = $this->linked($this->client(), $reem);
        $this->signInAsNutritionist($reem->user);

        Livewire::test(ViewClient::class, ['record' => $link->id])
            ->callAction('chooseTemplate', ['template' => 'mediterranean', 'note' => 'أسهل عليك مع أكلنا اليومي.'])
            ->assertHasNoActionErrors()
            ->assertNotified('تغيّر القالب، وأُعيد حساب معاييره.');

        $sheet = app(PersonalCriteria::class)->for($link->user);
        $this->assertSame('mediterranean', $sheet->template['key']);
        $this->assertSame(CriterionSource::Nutritionist, $sheet->template['source']);
        $this->assertTrue(app(PersonalCriteria::class)->templateLockedFor($link->user));

        Notification::assertSentTo($link->user, SiteNotification::class, fn ($n) => str_contains($n->title, 'حدّث'));

        Livewire::test(ViewClient::class, ['record' => $link->id])
            ->callAction('resetTemplate')
            ->assertNotified('عاد القالب.');
        $this->assertSame('diabetes', app(PersonalCriteria::class)->for($link->user)->template['key']);
    }

    public function test_the_nutritionist_sets_and_resets_a_criterion(): void
    {
        $reem = $this->nutritionist();
        $link = $this->linked($this->client(), $reem);
        $this->signInAsNutritionist($reem->user);

        Livewire::test(ViewClient::class, ['record' => $link->id])
            ->callAction(TestAction::make('editCriterion')->arguments(['criterion' => 'sodium_mg']), ['value' => 100])
            ->assertHasActionErrors(['value']);

        Livewire::test(ViewClient::class, ['record' => $link->id])
            ->callAction(TestAction::make('editCriterion')->arguments(['criterion' => 'sodium_mg']), ['value' => 1500, 'note' => 'ضغطك مرتفع.'])
            ->assertHasNoActionErrors();

        $criteria = app(PersonalCriteria::class);
        $sodium = $criteria->for($link->user)->get('sodium_mg');
        $this->assertSame(1500.0, $sodium->value);
        $this->assertSame('ضغطك مرتفع.', $sodium->note);
        $this->assertTrue($criteria->lockedFor($link->user, 'sodium_mg'));

        Livewire::test(ViewClient::class, ['record' => $link->id])
            ->callAction(TestAction::make('resetCriterion')->arguments(['criterion' => 'sodium_mg']));
        $this->assertFalse($criteria->lockedFor($link->user, 'sodium_mg'));
    }

    public function test_one_email_per_editing_session(): void
    {
        $reem = $this->nutritionist();
        $link = $this->linked($this->client(), $reem);
        $workspace = app(ClientWorkspace::class);
        $updates = fn () => Notification::sent($link->user, SiteNotification::class)
            ->filter(fn ($n) => str_contains($n->title, 'حدّث'))->count();

        $workspace->setCriterion($reem->user, $link, 'sodium_mg', 1500);
        $workspace->setCriterion($reem->user, $link, 'fiber_g', 30);
        $workspace->chooseTemplate($reem->user, $link, 'dash');
        $this->assertSame(1, $updates());

        $this->travel(31)->minutes();
        $workspace->setCriterion($reem->user, $link, 'sodium_mg', 1400);
        $this->assertSame(2, $updates());
    }

    public function test_the_person_sees_the_nutritionists_values_locked_with_the_notes(): void
    {
        $reem = $this->nutritionist();
        $link = $this->linked($this->client(), $reem);
        $workspace = app(ClientWorkspace::class);
        $workspace->chooseTemplate($reem->user, $link, 'dash', 'لضبط الضغط.');
        $workspace->setCriterion($reem->user, $link, 'sodium_mg', 1500, 'ضغطك مرتفع.');

        $this->actingAs($link->user)->get(route('site.criteria'))
            ->assertOk()
            ->assertSee('ملاحظة أخصائيّك: لضبط الضغط.')
            ->assertSee('ضغطك مرتفع.')
            ->assertSee('يغيّره أخصائيّك فقط');

        $this->put(route('site.criteria.update', 'sodium_mg'), ['value' => 2500])->assertSessionHas('error');
    }

    public function test_the_nutritionist_ends_the_link_with_a_reason(): void
    {
        $reem = $this->nutritionist();
        $link = $this->linked($this->client(), $reem);
        app(ClientWorkspace::class)->setCriterion($reem->user, $link, 'sodium_mg', 1500);
        $this->signInAsNutritionist($reem->user);

        Livewire::test(ViewClient::class, ['record' => $link->id])
            ->callAction('endLink', ['reason' => 'أنهينا الخطة بنجاح.'])
            ->assertRedirect(ClientResource::getUrl());

        $link->refresh();
        $this->assertSame(ConsultationStatus::Ended, $link->status);
        $this->assertSame('أنهينا الخطة بنجاح.', $link->end_reason);
        $this->assertSame($reem->user_id, (int) $link->ended_by);
        $this->assertFalse(app(PersonalCriteria::class)->lockedFor($link->user, 'sodium_mg'));
        Notification::assertSentTo($link->user, SiteNotification::class,
            fn ($n) => str_contains($n->title, 'أنهى') && $n->body === 'أنهينا الخطة بنجاح.');
    }
}
