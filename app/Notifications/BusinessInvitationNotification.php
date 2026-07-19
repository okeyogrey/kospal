<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->action('Accept invitation', $url)
            ->line('This invitation expires at '.$this->invitation->expires_at->toDayDateTimeString().'.');
    }
}
