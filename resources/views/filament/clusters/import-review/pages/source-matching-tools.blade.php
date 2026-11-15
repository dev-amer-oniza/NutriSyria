<x-filament-panels::page>
    @include('filament.clusters.import-review.components.import-steps', ['current' => 2])

    <x-filament::section heading="المصدر" description="يُختار مرة واحدة ويُستعمل في الخطوتين.">
        {{ $this->sourceForm }}
    </x-filament::section>

    <x-filament::section heading="الخطوة 1: اقتراح المطابقة" description="ارفع قائمة الأغذية، فيقترح النظام لكل غذاء وشكل أقرب عنصر في USDA وتنزل لك المسودة.">
        <form wire:submit.prevent="generateDraft">
            {{ $this->matchForm }}
            <div class="mt-4">
                <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray">
                    اقترح المطابقة ونزّل المسودة
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    <x-filament::section heading="الخطوة 2: ابنِ الدفعة من المسودة المراجَعة" description="افتح المسودة، ضع yes جنب الاختيار الصحيح لكل غذاء (الأكيد معلَّم سلفاً)، ثم ارفعها هنا.">
        <form wire:submit.prevent="buildBundle">
            {{ $this->buildForm }}
            <div class="mt-4">
                <x-filament::button type="submit" color="success" icon="heroicon-o-check">
                    ابنِ الدفعة وافحصها
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    @if ($errorMessage)
        <x-filament::section>
            <pre class="whitespace-pre-wrap text-sm text-danger-600">{{ $errorMessage }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
