<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralInviteNotification extends Notification
{
    /**
     * @param  array{code: string, url: string, expires_at: string, inviter_name: string}  $invite
     */
    public function __construct(
        public array $invite,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->invite['inviter_name'];
        $code = $this->invite['code'];

        return (new MailMessage)
            ->subject("{$name} invited you to KOSPAL")
            ->line("{$name} wants you to try KOSPAL. New shops get a 60-day trial.")
            ->line('If you register with this invite, you both get 10% off your first payment after the trial.')
            ->line("Invite code: {$code}")
            ->action('Create your shop', $this->invite['url'])
            ->line('This invite expires on '.$this->invite['expires_at'].'.');
    }
}
