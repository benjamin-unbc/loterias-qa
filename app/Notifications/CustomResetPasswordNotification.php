<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;

class CustomResetPasswordNotification extends ResetPasswordNotification
{
    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject(Lang::get('Bienvenido a ' . config('app.name') . '!'))
            ->greeting('¡Hola!')
            ->line(Lang::get('Ha recibido este mensaje porque hemos creado su cuenta en nuestra plataforma. Por favor, cree su contraseña dando click en el siguiente botón.'))
            ->action(Lang::get('Crear una contraseña'), $url)
            ->line(Lang::get('Este enlace de creación de contraseña expirará en 60 minutos.'))
            ->line(Lang::get('Si no ha solicitado la creación de su cuenta, omita este mensaje de correo electrónico.'))
            ->salutation('Saludos, ' . config('app.name'));        
    }
}
