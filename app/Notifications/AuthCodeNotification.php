<?php

namespace App\Notifications;

use App\Enums\AuthCodePurpose;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email carrying a 6-digit code. Sent right away (not queued): the person is waiting
 * on the page for it, and the free hosting has no queue worker.
 */
class AuthCodeNotification extends Notification
{
    public function __construct(
        public readonly AuthCodePurpose $purpose,
        #[\SensitiveParameter] public readonly string $code,
        public readonly int $minutes,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->purpose->subject())
            ->view('mail.auth-code', [
                'name' => $notifiable->name,
                'intro' => $this->purpose->intro(),
                'code' => $this->code,
                'minutes' => $this->minutes,
            ]);
    }
}
