<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductReviewNote;
use App\Services\Nutrition\ProductReviewNotes;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The review notes of one product, under its analysis and on its page. The nutritionist (or
 * admin) sends and withdraws notes; the brand owner marks them fixed. Every action checks the
 * product policy again on the server.
 */
class ReviewNotesPanel extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public int $productId;

    public function mount(int $productId): void
    {
        $this->productId = $productId;
        abort_unless(Gate::allows('view', $this->product), 404);
    }

    #[Computed]
    public function product(): Product
    {
        return Product::findOrFail($this->productId);
    }

    /** @return Collection<int, ProductReviewNote> */
    #[Computed]
    public function notes(): Collection
    {
        return $this->product->reviewNotes()->with(['author', 'resolvedBy'])->get();
    }

    public function canSend(): bool
    {
        return Gate::allows('sendReviewNote', $this->product);
    }

    public function canResolve(): bool
    {
        return Gate::allows('resolveReviewNote', $this->product);
    }

    public function sendNoteAction(): Action
    {
        return Action::make('sendNote')
            ->label('إرسال ملاحظة لصاحب البراند')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('warning')
            ->visible(fn () => $this->canSend())
            ->modalHeading('ملاحظة لصاحب البراند')
            ->modalDescription('تصله كإشعار، ويبقى المنتج "مطلوب تعديل" حتى يعدّله، ولا يُعتمد قبل ذلك.')
            ->modalSubmitActionLabel('إرسال')
            ->schema([
                Textarea::make('body')
                    ->label('الملاحظة')
                    ->placeholder('مثال: كمية الزيت 150 غ تبدو كبيرة، هل المقصود 15 غ؟')
                    ->required()
                    ->rows(4)
                    ->maxLength(ProductReviewNotes::MAX_NOTE),
            ])
            ->action(function (array $data) {
                abort_unless($this->canSend(), 403);
                app(ProductReviewNotes::class)->send($this->product, auth()->user(), $data['body']);
                Notification::make()->title('أُرسلت الملاحظة')->success()->send();
                $this->changed();
            });
    }

    public function resolveNoteAction(): Action
    {
        return Action::make('resolveNote')
            ->label('تم التعديل')
            ->icon('heroicon-o-check')
            ->color('success')
            ->size('sm')
            ->visible(fn () => $this->canResolve())
            ->modalHeading('تم التعديل')
            ->modalDescription('يصل إشعار لكاتب الملاحظة ليراجع التحليل من جديد.')
            ->modalSubmitActionLabel('إرسال')
            ->schema([
                Textarea::make('reply')
                    ->label('ماذا عدّلت؟ (اختياري)')
                    ->placeholder('مثال: صحّحت الزيت إلى 15 غ.')
                    ->rows(3)
                    ->maxLength(ProductReviewNotes::MAX_REPLY),
            ])
            ->action(function (array $data, array $arguments) {
                abort_unless($this->canResolve(), 403);
                app(ProductReviewNotes::class)->resolve($this->openNote($arguments), auth()->user(), $data['reply'] ?? null);
                Notification::make()->title('أُبلغ أخصائي التغذية')->success()->send();
                $this->changed();
            });
    }

    public function withdrawNoteAction(): Action
    {
        return Action::make('withdrawNote')
            ->label('إغلاق بلا تعديل')
            ->icon('heroicon-o-x-mark')
            ->color('gray')
            ->size('sm')
            ->link()
            ->visible(fn () => $this->canSend())
            ->requiresConfirmation()
            ->modalHeading('إغلاق الملاحظة')
            ->modalDescription('تُغلق الملاحظة بلا تعديل، ويصبح الاعتماد ممكناً.')
            ->action(function (array $arguments) {
                abort_unless($this->canSend(), 403);
                app(ProductReviewNotes::class)->withdraw($this->openNote($arguments), auth()->user());
                $this->changed();
            });
    }

    /** Only an open note of THIS product: an id from another product is not found. */
    private function openNote(array $arguments): ProductReviewNote
    {
        return $this->product->reviewNotes()->open()->findOrFail((int) ($arguments['note'] ?? 0));
    }

    private function changed(): void
    {
        unset($this->notes);
        // The analysis page shows or hides its approve button.
        $this->dispatch('review-notes-changed');
    }

    public function render()
    {
        return view('livewire.review-notes-panel');
    }
}
