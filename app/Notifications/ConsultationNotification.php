<?php

namespace App\Notifications;

use App\Filament\Clusters\Consultations\Resources\ConsultationRequests\ConsultationRequestResource;
use App\Models\ConsultationRequest;
use App\Services\Consultations\ConsultationService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The consultation emails: a new request to the nutritionist, and the answer to the person.
 * Sent right away (no queue on the free hosting); ConsultationService never lets a failed
 * email undo the step.
 */
class ConsultationNotification extends Notification
{
    /** @param  list<string>  $lines */
    private function __construct(
        public readonly string $subject,
        public readonly array $lines,
        public readonly string $actionLabel,
        public readonly string $actionUrl,
        public readonly ?string $footnote = null,
    ) {}

    public static function requested(ConsultationRequest $request): self
    {
        $about = $request->clientSummary();

        return new self(
            subject: 'طلب استشارة جديد من '.$request->user->name,
            lines: [
                "{$request->user->name} يطلب استشارة".($about ? " ($about)" : '').'.',
                'السبب: '.$request->reason,
                'يبقى الطلب مفتوحاً '.ConsultationService::EXPIRES_AFTER_DAYS.' أيام. اقبله أو اعتذر من لوحتك.',
            ],
            actionLabel: 'عرض الطلب',
            actionUrl: ConsultationRequestResource::getUrl(),
            footnote: 'يظهر لك سجله الصحي ورقم واتساب بعد القبول.',
        );
    }

    public static function accepted(ConsultationRequest $request): self
    {
        $name = self::nutritionistName($request);

        return new self(
            subject: "قبل {$name} طلب استشارتك",
            lines: [
                "قبل {$name} طلبك. سيتواصل معك على واتساب، ويمكنك أن تبدأ أنت أيضاً.",
                'الاستشارة الأولى مجانية.',
            ],
            actionLabel: 'أخصائيّي',
            actionUrl: route('site.my-nutritionist'),
            footnote: 'يمكنك إنهاء الارتباط متى شئت من صفحة "أخصائيّي".',
        );
    }

    public static function declined(ConsultationRequest $request): self
    {
        $name = self::nutritionistName($request);

        return new self(
            subject: "اعتذر {$name} عن طلبك",
            lines: array_values(array_filter([
                "اعتذر {$name} عن استقبال طلبك الآن.",
                $request->decline_reason ? 'السبب: '.$request->decline_reason : null,
                'يمكنك اختيار أخصائي آخر.',
            ])),
            actionLabel: 'الأخصائيون',
            actionUrl: route('site.nutritionists'),
        );
    }

    /** The nutritionist changed the template or criteria (at most one email per editing session). */
    public static function criteriaUpdated(ConsultationRequest $request): self
    {
        $name = self::nutritionistName($request);

        return new self(
            subject: "حدّث {$name} معاييرك",
            lines: [
                "عدّل {$name} قالبك الغذائي أو بعض معاييرك، فصارت الوجبات تُقيَّم على أساسها.",
                'تجد كل تغيير وملاحظته في صفحة "معاييري".',
            ],
            actionLabel: 'معاييري',
            actionUrl: route('site.criteria'),
            footnote: 'ما يحدده أخصائيّك يبقى ثابتاً حتى يغيّره هو، أو تنهي الارتباط.',
        );
    }

    public static function endedByNutritionist(ConsultationRequest $request): self
    {
        $name = self::nutritionistName($request);

        return new self(
            subject: "أنهى {$name} الارتباط",
            lines: array_values(array_filter([
                "أنهى {$name} الارتباط بينكما.",
                $request->end_reason ? 'السبب: '.$request->end_reason : null,
                'عادت معاييرك إلى قيمك أنت (أو المحسوبة تلقائياً)، ولم يعد يرى سجلك الصحي. يمكنك اختيار أخصائي آخر متى شئت.',
            ])),
            actionLabel: 'الأخصائيون',
            actionUrl: route('site.nutritionists'),
        );
    }

    private static function nutritionistName(ConsultationRequest $request): string
    {
        return $request->nutritionist->nutritionistProfile?->display_name ?? $request->nutritionist->name;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->view('mail.message', [
                'name' => $notifiable->name,
                'lines' => $this->lines,
                'actionLabel' => $this->actionLabel,
                'actionUrl' => $this->actionUrl,
                'footnote' => $this->footnote,
            ]);
    }
}
