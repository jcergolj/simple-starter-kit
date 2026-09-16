<?php

namespace App\Features\Settings\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangedNotification extends Notification
{
    public function __construct(
        public readonly string $newEmail,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your account email address changed'))
            ->line(__('Your account email address was changed to :email.', ['email' => $this->newEmail]))
            ->line(__('If you did not make this change, contact support immediately.'));
    }
}
