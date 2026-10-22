<?php

namespace App\Filament\Clusters\Consultations\Resources\ConsultationRequests\Tables;

use App\Enums\ConsultationStatus;
use App\Filament\Clusters\Consultations\Resources\Clients\Pages\ViewClient;
use App\Exceptions\ConsultationRefused;
use App\Models\ConsultationRequest;
use App\Services\Consultations\ConsultationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConsultationRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Waiting ones first, then the newest.
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->orderByRaw('case when status = ? then 0 when status = ? then 1 else 2 end', [
                    ConsultationStatus::Pending->value, ConsultationStatus::Accepted->value,
                ])
                ->orderByDesc('id'))
            ->columns([
                TextColumn::make('user.name')
                    ->label('المراجع')
                    ->weight('bold')
                    ->description(fn (ConsultationRequest $record) => $record->clientSummary()),
                TextColumn::make('reason')
                    ->label('السبب')
                    ->wrap()
                    ->lineClamp(3),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->description(fn (ConsultationRequest $record) => $record->isPending()
                        ? 'يبقى '.$record->daysLeft().' أيام'
                        : ($record->status === ConsultationStatus::Declined ? $record->decline_reason : null)),
                TextColumn::make('created_at')
                    ->label('وصل')
                    ->since()
                    ->dateTimeTooltip('Y-m-d H:i'),
                TextColumn::make('user.phone')
                    ->label('واتساب')
                    // The contact is shared only once the request is accepted.
                    ->state(fn (ConsultationRequest $record) => $record->isAccepted() ? $record->user->phone : null)
                    ->url(fn (ConsultationRequest $record) => $record->isAccepted() ? ConsultationRequest::whatsappUrl($record->user->phone) : null, shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedChatBubbleOvalLeft)
                    ->extraAttributes(['dir' => 'ltr'])
                    ->placeholder('بعد القبول'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(ConsultationStatus::class),
            ])
            ->recordActions([
                self::acceptAction(),
                self::declineAction(),
                Action::make('file')
                    ->label('ملف المراجع')
                    ->icon(Heroicon::OutlinedUser)
                    ->color('gray')
                    ->visible(fn (ConsultationRequest $record) => $record->isAccepted())
                    ->url(fn (ConsultationRequest $record) => ViewClient::getUrl(['record' => $record])),
            ])
            ->emptyStateHeading('لا طلبات بعد')
            ->emptyStateDescription('تصلك الطلبات حين يكون ملفك ظاهراً في الموقع و"أستقبل مراجعين" مفعّلاً.');
    }

    private static function acceptAction(): Action
    {
        return Action::make('accept')
            ->label('قبول')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->visible(fn (ConsultationRequest $record) => $record->isPending())
            ->authorize('respond')
            ->requiresConfirmation()
            ->modalHeading(fn (ConsultationRequest $record) => 'قبول طلب '.$record->user->name)
            ->modalDescription('يصله إشعار بالقبول، ويظهر لكما رقم واتساب الآخر. الاستشارة الأولى مجانية.')
            ->action(fn (ConsultationRequest $record) => self::run(
                fn () => app(ConsultationService::class)->accept(auth()->user(), $record),
                'قُبل الطلب. تواصل معه على واتساب.',
            ));
    }

    private static function declineAction(): Action
    {
        return Action::make('decline')
            ->label('اعتذار')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->visible(fn (ConsultationRequest $record) => $record->isPending())
            ->authorize('respond')
            ->modalHeading(fn (ConsultationRequest $record) => 'الاعتذار عن طلب '.$record->user->name)
            ->modalSubmitActionLabel('إرسال الاعتذار')
            ->schema([
                Textarea::make('reason')
                    ->label('السبب (اختياري)')
                    ->helperText('يصله في الإشعار. مثلاً: "جدولي ممتلئ هذا الشهر".')
                    ->rows(3)
                    ->maxLength(300),
            ])
            ->action(fn (ConsultationRequest $record, array $data) => self::run(
                fn () => app(ConsultationService::class)->decline(auth()->user(), $record, $data['reason'] ?? null),
                'أُرسل الاعتذار.',
            ));
    }

    private static function run(callable $step, string $done): void
    {
        try {
            $step();
        } catch (ConsultationRefused $e) {
            Notification::make()->title($e->userMessage())->warning()->send();

            return;
        }

        Notification::make()->title($done)->success()->send();
    }
}
