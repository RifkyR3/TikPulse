<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $locale = $notifiable->preferred_locale ?? config('app.locale', 'en');

        return (new MailMessage)
            ->subject(__('verification.mail_subject', ['code' => $this->code], $locale))
            ->greeting(__('verification.mail_greeting', ['name' => $notifiable->name], $locale))
            ->line(__('verification.mail_intro', [], $locale))
            ->line("# **{$this->code}**")
            ->line(__('verification.mail_expires', [], $locale))
            ->line(__('verification.mail_action_intro', [], $locale))
            ->action(__('verification.mail_action', [], $locale), $verificationUrl)
            ->line(__('verification.mail_outro', [], $locale));
    }

    protected function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes((int) Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
