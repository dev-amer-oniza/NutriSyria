<x-filament-panels::page>
    @include('filament.clusters.import-review.components.import-steps', ['current' => 1])

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->getWays() as $way)
            <x-filament::section>
                <div class="flex h-full flex-col gap-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400">
                            <x-filament::icon :icon="$way['icon']" class="h-6 w-6" />
                        </span>
                        <h2 class="text-lg font-bold text-gray-950 dark:text-white">{{ $way['title'] }}</h2>
                    </div>

                    <p class="text-sm text-gray-700 dark:text-gray-200">
                        <span class="font-semibold">متى:</span> {{ $way['when'] }}
                    </p>

                    <ol class="grid list-inside list-decimal gap-1 text-sm text-gray-600 dark:text-gray-300">
                        @foreach ($way['how'] as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>

                    <div class="mt-auto pt-2">
                        <x-filament::button tag="a" :href="$way['url']" :icon="$way['icon']">
                            {{ $way['action'] }}
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    <p class="text-sm text-gray-600 dark:text-gray-300">
        بعد أي طريقة تجد الدفعة في
        <x-filament::link :href="$this->getBatchesUrl()">دفعات الاستيراد</x-filament::link>،
        وفيها: اعتمد كل السليم بضغطة، وافتح ما عليه تنبيه واحداً واحداً.
    </p>
</x-filament-panels::page>
