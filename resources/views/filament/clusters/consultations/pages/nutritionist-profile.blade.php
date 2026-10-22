<x-filament-panels::page>
    @php $profile = $this->profile(); @endphp

    <x-filament::section>
        @if ($profile->isListed())
            <p class="font-bold text-success-600 dark:text-success-400">ملفك ظاهر في قائمة الأخصائيين في الموقع.</p>
            <a class="text-primary-600 underline" href="{{ route('site.nutritionists.show', $profile) }}" target="_blank">افتح صفحتك في الموقع</a>
        @elseif (! $profile->exists || ! $profile->isComplete())
            <p class="font-bold text-warning-600 dark:text-warning-400">ملفك غير ظاهر بعد. أكمل: {{ implode('، ', $profile->missing()) }}.</p>
        @else
            <p class="font-bold text-gray-600 dark:text-gray-300">ملفك مكتمل، لكنه مخفي لأن "أستقبل مراجعين جدداً" متوقف.</p>
        @endif
    </x-filament::section>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit">حفظ</x-filament::button>
    </form>
</x-filament-panels::page>
