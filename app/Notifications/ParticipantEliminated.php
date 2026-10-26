<?php

namespace App\Notifications;

use App\Models\Challenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ParticipantEliminated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Challenge $challenge, public string $date) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Вы выбыли из челленджа')
            ->line("Вы не подтвердили подъём на Фаджр {$this->date} и выбыли из челленджа «{$this->challenge->group->name}».")
            ->line('Ставка потеряна и будет разделена между участниками, которые дойдут до конца.')
            ->action('Открыть челлендж', route('challenges.show', $this->challenge));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Вы выбыли из челленджа',
            'body' => "Не подтверждён Фаджр {$this->date}. Ставка группы «{$this->challenge->group->name}» потеряна.",
            'url' => route('challenges.show', $this->challenge, absolute: false),
        ];
    }
}
