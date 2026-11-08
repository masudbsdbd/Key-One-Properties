<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

class ResetPasswordNotification extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        // Token travels in the URL path only once and is single-use; the email is not put in the query string.
        return url(route('password.reset', ['token' => $this->token], false));
    }
}
