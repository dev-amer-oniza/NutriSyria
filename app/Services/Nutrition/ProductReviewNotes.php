<?php

namespace App\Services\Nutrition;

use App\Filament\Clusters\Brands\Pages\RecipeAnalysis;
use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\ProductReviewNote;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The note loop between the nutritionist and the brand owner:
 *  - send(): the nutritionist (or admin) asks for a change; the brand's managers get a panel
 *    notification and the product shows "مطلوب تعديل";
 *  - resolve(): the owner says it is fixed, with an optional reply; the note's author is told;
 *  - withdraw(): the author closes a note without a change.
 * Approving official values waits until no note is open. Callers check the permissions
 * (ProductPolicy::sendReviewNote / resolveReviewNote).
 */
final class ProductReviewNotes
{
    public const MAX_NOTE = 2000;
    public const MAX_REPLY = 1000;

    public function send(Product $product, User $author, string $body): ProductReviewNote
    {
        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('اكتب الملاحظة.');
        }

        $note = new ProductReviewNote();
        $note->forceFill([
            'product_id' => $product->id,
            'author_id' => $author->id,
            'body' => mb_substr($body, 0, self::MAX_NOTE),
        ])->save();

        Notification::make()
            ->title("ملاحظة على: {$product->name}")
            ->body("{$author->name}: ".mb_strimwidth($note->body, 0, 140, '…'))
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->warning()
            ->actions([
                Action::make('open')
                    ->label('فتح المنتج')
                    ->url(ProductResource::getUrl('edit', ['record' => $product]))
                    ->markAsRead(),
            ])
            ->sendToDatabase($this->managersOf($product));

        return $note;
    }

    public function resolve(ProductReviewNote $note, User $by, ?string $reply = null): void
    {
        $this->close($note, $by, trim((string) $reply) ?: null);

        $author = $note->author;
        if ($author && $author->is_active !== false && ! $author->is($by)) {
            $product = $note->product;
            Notification::make()
                ->title("عُدّلت: {$product->name}")
                ->body($note->resolution ? "{$by->name}: {$note->resolution}" : "{$by->name} عدّل الوصفة حسب ملاحظتك.")
                ->icon('heroicon-o-check-circle')
                ->success()
                ->actions([
                    Action::make('open')
                        ->label('مراجعة التحليل')
                        ->url(RecipeAnalysis::getUrl().'?product='.$product->id)
                        ->markAsRead(),
                ])
                ->sendToDatabase($author);
        }
    }

    public function withdraw(ProductReviewNote $note, User $by): void
    {
        $this->close($note, $by, 'أُغلقت بلا تعديل.');
    }

    private function close(ProductReviewNote $note, User $by, ?string $resolution): void
    {
        if (! $note->isOpen()) {
            return;
        }

        $note->forceFill([
            'resolved_at' => now(),
            'resolved_by' => $by->id,
            'resolution' => $resolution !== null ? mb_substr($resolution, 0, self::MAX_REPLY) : null,
        ])->save();
    }

    /** Active users who manage the product's brand. */
    private function managersOf(Product $product)
    {
        $ids = DB::table('brand_user')->where('brand_id', $product->brand_id)->pluck('user_id');

        return User::query()->whereKey($ids)->where('is_active', true)->get();
    }
}
