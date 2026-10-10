<?php

namespace App\Notifications;

use App\Models\SalonUser;
use Filament\Facades\Filament;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SalonPasswordSetup extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(SalonUser $notifiable): string
    {
        return Filament::getPanel('salon')->getResetPasswordUrl($this->token, $notifiable);
    }

    public function toMail(SalonUser $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Salon paneli hesabınız hazır')
            ->greeting("Merhaba {$notifiable->name},")
            ->line("{$notifiable->salon->name} için salon paneli hesabınız açıldı.")
            ->line('Giriş yapmadan önce aşağıdaki bağlantıdan şifrenizi belirleyin.')
            ->action('Şifremi belirle', $this->url($notifiable))
            ->line('Bağlantı 24 saat geçerlidir. Süresi dolarsa giriş sayfasındaki "Şifremi unuttum" bağlantısını kullanın.')
            ->salutation('Zarf');
    }
}
