<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\WebPush;
use Illuminate\Notifications\Notification;

class WebPushChannel
{
    public function __construct(private WebPush $push) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if ($notifiable instanceof User && method_exists($notification, 'toWebPush')) {
            $this->push->send($notifiable, $notification->toWebPush($notifiable));
        }
    }
}
