<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessInvitationNotification extends Notification
{
    public function __construct(
        public Invitation $invitation,
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
        $businessName = $this->invitation->business?->name ?? 'a business';
        $url = route('invitations.accept.show', $this->invitation->token);

        return (new MailMessage)
            ->subject('KOSPAL staff invitation')
            ->line("You have been invited to join {$businessName} on KOSPAL.")
            ->line('Role: '.$this->invitation->role->value)
            ->line('Open the link below to create your password (or sign in if you already have a KOSPAL account), then join the team.')
            ->action('Accept invitation', $url)
            ->line('This invitation expires at '.$this->invitation->expires_at->toDayDateTimeString().'.');
    }
}
