<?php

namespace App\Notifications;

use App\Models\ConsultationRequest;
use App\Services\Nutrition\LiverUpdate;
use Illuminate\Notifications\Notification;

/**
 * A notice in the site's bell (the owner's decision: everything except sign-in and codes
 * reaches people here, not by email). Stored in `notifications` with format "site", so the
 * panel's Filament bell (format "filament") never shows it, and the other way round.
 */
class SiteNotification extends Notification
{
    public const FORMAT = 'site';

    private function __construct(
        /** success | info | warning: the colour and icon in the bell */
        public readonly string $kind,
        public readonly string $title,
        public readonly ?string $body,
        /** A path inside the site ("/account/criteria"), so it works on any domain. */
        public readonly string $url,
    ) {}

    public static function accepted(ConsultationRequest $request): self
    {
        return new self('success', 'قبل '.self::nutritionistName($request).' طلبك',
            'تواصلا على واتساب. الاستشارة الأولى مجانية.', route('site.my-nutritionist', absolute: false));
    }

    public static function declined(ConsultationRequest $request): self
    {
        return new self('warning', 'اعتذر '.self::nutritionistName($request).' عن طلبك',
            $request->decline_reason ?: 'يمكنك اختيار أخصائي آخر.', route('site.nutritionists', absolute: false));
    }

    /** The nutritionist changed the template, criteria or profile (one notice per editing session). */
    public static function updatedByNutritionist(ConsultationRequest $request): self
    {
        return new self('info', 'حدّث '.self::nutritionistName($request).' ملفك',
            'عدّل قالبك أو معاييرك أو بياناتك. تجد كل تغيير وملاحظته في "معاييري".', route('site.criteria', absolute: false));
    }

    public static function endedByNutritionist(ConsultationRequest $request): self
    {
        return new self('warning', 'أنهى '.self::nutritionistName($request).' الارتباط',
            $request->end_reason ?: 'عادت معاييرك إلى قيمك أنت، ويمكنك تعديل بياناتك من جديد.', route('site.my-nutritionist', absolute: false));
    }

    /**
     * Four weeks on with a passing condition: does it still hold? (App\Services\Nutrition\RecoveryReminder)
     *
     * @param  list<string>  $conditions  their labels
     */
    public static function recoveryCheck(array $conditions, bool $managed): self
    {
        $names = implode(' و', array_map(fn (string $c) => '«'.$c.'»', $conditions));

        return new self('info', 'هل ما زال عندك '.$names.'؟',
            $managed
                ? 'إن تحسّنت، أخبر أخصائيّك ليحدّث ملفك، فنقيّم الوجبات على وضعك الجديد.'
                : 'إن تحسّنت، أزلها من بياناتك الصحية، فنقيّم الوجبات على وضعك الجديد.',
            route('site.criteria', absolute: false));
    }

    /** The old "الكبد الوبائي أو التليّف" is three conditions now (LiverUpdate, step 3). */
    public static function liverUpdate(bool $managed): self
    {
        return new self('info', LiverUpdate::TITLE,
            $managed
                ? 'اخترت سابقاً «الكبد الوبائي أو التليّف»، وصارت الآن ثلاث حالات. سيحدّثها أخصائيّك.'
                : 'اخترت سابقاً «الكبد الوبائي أو التليّف»، وصارت الآن ثلاث حالات. اختر ما يناسبك حتى نقيّم وجباتك على الحالة الصحيحة.',
            route($managed ? 'site.criteria' : 'site.liver', absolute: false));
    }

    private static function nutritionistName(ConsultationRequest $request): string
    {
        return $request->nutritionist->nutritionistProfile?->display_name ?? $request->nutritionist->name;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string|null> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'format' => self::FORMAT,
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
