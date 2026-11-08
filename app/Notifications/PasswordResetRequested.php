<?php

namespace App\Notifications;

use App\Models\PasswordResetRequest;
use Illuminate\Notifications\Notification;

class PasswordResetRequested extends Notification
{
    public function __construct(private PasswordResetRequest $request) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Password reset requested by '.$this->request->user->name,
            'url' => route('password-requests.index'),
        ];
    }
}
