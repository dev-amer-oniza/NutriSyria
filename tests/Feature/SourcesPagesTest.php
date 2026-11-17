<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Filament\Clusters\ReferenceSettings\Pages\MedicalReferencesPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 3b: the public "المصادر" page and the admin's read-only "المراجع الطبية". */
class SourcesPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sources_page_shows_every_limit_with_its_reference_or_says_it_is_pending(): void
    {
        // A rule not verified yet (fat intolerance's 15 g a meal, before step 7 found no figure).
        config(['nutrition_criteria.conditions.fat_intolerance.criteria' => ['meal_fat_g' => ['type' => 'fixed', 'value' => 15]]]);
        $this->get(route('site.sources'))->assertOk()
            ->assertSee('من أين نأتي بأرقامنا وحدودنا')
            ->assertSee('id="general"', false)
            ->assertSee('id="cirrhosis"', false)
            ->assertSee('id="template-celiac"', false)
            // A verified rule: its value, its verbatim sentence and a link to the reference.
            ->assertSee('على الأقل 1.2 غ لكل كغ من الوزن في اليوم')
            ->assertSee('Optimal daily protein intake should not be lower than the recommended 1.2–1.5 g/kg.BW/d', false)
            ->assertSee('https://pmc.ncbi.nlm.nih.gov/articles/PMC6657019/', false)
            // An unverified one says so, and that it does not judge.
            ->assertSee('حتى 15 غ للوجبة')
            ->assertSee('قيد المراجعة')
            ->assertSee('لا يدخل في تقييم الوجبات')
            ->assertSee('موثّق جزئياً 1/2')
            // Our own choices, from the config (decision 5a).
            ->assertSee('اختيارات منّا، لا من مرجع طبي')
            ->assertSee('وجبة المطعم تُقارن بـ30% من احتياجك اليومي')
            ->assertSee('وأليافها 5 غ فأكثر')
            ->assertDontSee('id="liver_cirrhosis"', false);
    }

    public function test_the_footer_link_opens_the_page_not_the_placeholder(): void
    {
        $this->get(route('home'))->assertSee(route('site.sources'), false);
        $this->get(route('site.sources'))->assertDontSee('قريباً');
    }

    public function test_the_admin_and_nutritionists_read_the_references_and_nobody_edits_them(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        $this->get(MedicalReferencesPage::getUrl())->assertOk()
            ->assertSee('المراجع الطبية')
            ->assertSee('تليّف الكبد')
            ->assertSee('EASL · 2019')
            ->assertSee('للعرض فقط');
    }

    public function test_a_nutritionist_reads_it(): void
    {
        $this->signInAs(Access::NUTRITIONIST);
        $this->get(MedicalReferencesPage::getUrl())->assertOk();
    }

    public function test_a_brand_owner_does_not(): void
    {
        $this->signInAs(Access::BRAND_OWNER);
        $this->get(MedicalReferencesPage::getUrl())->assertForbidden();
    }

    public function test_the_filters_keep_what_is_still_pending(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);

        Livewire::test(MedicalReferencesPage::class)
            ->assertSee('الاحتياج العام لكل شخص')
            ->set('status', 'pending')
            ->assertDontSee('الاحتياج العام لكل شخص')
            ->assertSee('حمية البحر المتوسط')
            ->set('status', 'all')
            ->set('area', 'liver')
            ->assertSee('تليّف الكبد')
            ->assertDontSee('حمية البحر المتوسط')
            ->set('area', '')
            ->set('kind', 'strict')
            ->assertSee('السيلياك (حساسية الغلوتين)')
            ->assertDontSee('حمية البحر المتوسط');
    }
}
