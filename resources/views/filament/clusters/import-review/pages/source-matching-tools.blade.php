<x-filament-panels::page>
    <x-filament::section heading="الخطوة 1: توليد مسودة المطابقة">
        <form wire:submit.prevent="generateDraft">
            {{ $this->matchForm }}
            <div class="mt-4">
                <x-filament::button type="submit" color="primary">
                    توليد المسودة وتنزيلها
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    <x-filament::section heading="الخطوة 2: بناء الحزمة من المسودة المراجَعة" class="mt-6">
        <form wire:submit.prevent="buildBundle">
            {{ $this->buildForm }}
            <div class="mt-4">
                <x-filament::button type="submit" color="success">
                    بناء الحزمة والانتقال للفحص
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    @if ($errorMessage)
        <x-filament::section class="mt-6">
            <pre class="text-danger-600 text-sm whitespace-pre-wrap">{{ $errorMessage }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
