<?php

namespace App\Listeners;

use App\Events\SendNotificationEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

class SendSocketNotification
{
   public function handle(SendNotificationEvent $event)
    {
        Http::post(config('services.socket.url').'/send', [
            'user_id' => $event->userId,
            'event'   => $event->event,
            'data'    => $event->data,
            'room'    => $event->room
        ]);
    }
}
