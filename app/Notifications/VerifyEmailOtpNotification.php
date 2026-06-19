<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailOtpNotification extends Notification
{
    public function __construct(
        protected string $otp,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your EaseVerifier email')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Use the one-time code below to verify your email address.')
            ->line('Verification code: '.$this->otp)
            ->line('This code expires in 10 minutes.')
            ->action('Open verification page', url('/email/verify'))
            ->line('If you did not create an account, you can ignore this email.');
    }
}
