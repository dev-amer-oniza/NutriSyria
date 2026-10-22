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
        $profile = $request->user->profile;
        $about = array_filter([
            $profile?->age() ? $profile->age().' سنة' : null,
            $profile?->sex?->label(),
            $profile?->goal?->label(),
        ]);

        return new self(
            subject: 'طلب استشارة جديد من '.$request->user->name,
            lines: [
                "{$request->user->name} يطلب استشارة".($about ? ' ('.implode('، ', $about).')' : '').'.',
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
        $name = $request->nutritionist->nutritionistProfile?->display_name ?? $request->nutritionist->name;

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
        $name = $request->nutritionist->nutritionistProfile?->display_name ?? $request->nutritionist->name;

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
