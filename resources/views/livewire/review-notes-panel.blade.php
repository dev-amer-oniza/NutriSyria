<div class="grid gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            @if ($this->notes->isEmpty())
                لا توجد ملاحظات على هذا المنتج.
            @else
                ملاحظات أخصائي التغذية على التحليل، الأحدث أولاً.
            @endif
        </p>
        {{-- Filament draws an action even when visible() says no, so each button checks itself. --}}
        @if ($this->sendNoteAction->isVisible())
            {{ $this->sendNoteAction }}
        @endif
    </div>

    @foreach ($this->notes as $note)
        <article @class([
            'grid gap-2 rounded-xl border p-4',
            'border-danger-300 bg-danger-50 dark:border-danger-500/40 dark:bg-danger-500/10' => $note->isOpen(),
            'border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5' => ! $note->isOpen(),
        ])>
            <header class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span class="font-semibold text-gray-950 dark:text-white">
                    {{ $note->author?->name ?? 'مستخدم محذوف' }}
                    <span class="font-normal text-gray-500 dark:text-gray-400" dir="ltr">{{ $note->created_at->format('Y-m-d H:i') }}</span>
                </span>
                @if ($note->isOpen())
                    <x-filament::badge color="danger" icon="heroicon-o-chat-bubble-left-ellipsis">مطلوب تعديل</x-filament::badge>
                @else
                    <x-filament::badge color="success" icon="heroicon-o-check-circle">مغلقة</x-filament::badge>
                @endif
            </header>

            <p class="whitespace-pre-line text-gray-950 dark:text-white">{{ $note->body }}</p>

            @unless ($note->isOpen())
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    ↩ {{ $note->resolvedBy?->name ?? 'مستخدم محذوف' }}، <span dir="ltr">{{ $note->resolved_at->format('Y-m-d H:i') }}</span>@if ($note->resolution): {{ $note->resolution }}@endif
                </p>
            @endunless

            @if ($note->isOpen())
                <div class="flex flex-wrap items-center gap-3">
                    @foreach ([$this->resolveNoteAction, $this->withdrawNoteAction] as $action)
                        @php($action = $action(['note' => $note->id]))
                        @if ($action->isVisible())
                            {{ $action }}
                        @endif
                    @endforeach
                </div>
            @endif
        </article>
    @endforeach

    <x-filament-actions::modals />
</div>
