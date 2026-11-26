<?php

namespace App\Notifications;

use App\Auth\Access;
use App\Models\ConsultationRequest;
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
        /** What it is about, when something checks for it later ("weight": the weekly reminder). */
        public readonly ?string $topic = null,
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

    /** A dish the person asked for is on the platform now ("يومي", FoodRequests). */
    public static function foodAdded(string $asked, string $meal, string $url): self
    {
        return new self('success', "صارت «{$meal}» موجودة",
            "طلبت «{$asked}»، وأضفناها. من المرة الجاية بتنحسب من يومك.", $url);
    }

    public static function foodDismissed(string $asked, ?string $note): self
    {
        return new self('info', "ما قدرنا نضيف «{$asked}» هلق",
            $note ?: 'شكراً إنك بعتلنا ياها. منضل نشتغل على أكلات أكتر.', route('site.daily', absolute: false));
    }

    /** A week since the last weighing (Weighing::remindIfDue, once a week at most). */
    public static function weighReminder(): self
    {
        return new self('info', 'صار وقت توزن',
            'سجّل وزنك بـ«يومي»، لنحدّث احتياجك على وزنك الجديد.', route('site.daily', ['sheet' => 'weight'], absolute: false), 'weight');
    }

    /** The admin granted the role asked for at sign-up: the panel opens to them now. */
    public static function roleApproved(string $role): self
    {
        $owner = $role === Access::BRAND_OWNER;

        return new self('success', $owner ? 'انقبل حسابك كصاحب مطعم' : 'انقبل حسابك كأخصائي تغذية',
            $owner ? 'صار فيك تدخل للوحة التحكم. لما نربطك بمطعمك بيوصلك إشعار، وبتبلّش تضيف منتجاتك.'
                : 'صار فيك تدخل للوحة التحكم وتكمّل ملفك العام لتظهر للناس.',
            (string) parse_url(filament()->getPanel('admin')->getUrl(), PHP_URL_PATH) ?: '/admin');
    }

    public static function roleDeclined(string $role): self
    {
        return new self('info', $role === Access::BRAND_OWNER ? 'ما انقبل طلب حساب صاحب المطعم' : 'ما انقبل طلب حساب الأخصائي',
            'حسابك بيضل حساب عادي بالموقع. لأي سؤال تواصل معنا.', route('home', absolute: false));
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
        ] + ($this->topic ? ['topic' => $this->topic] : []);
    }
}
