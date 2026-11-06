<?php

namespace App\Listeners;

use App\Events\SendNotificationEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendSocketNotification
{
   public function handle(SendNotificationEvent $event)
    {
        try {
            Http::timeout(3)->post(config('services.socket.url').'/send', [
                'user_id' => $event->userId,
                'event'   => $event->event,
                'data'    => $event->data,
                'room'    => $event->room,
            ]);
        } catch (\Throwable $exception) {
            // Socket hanya untuk pembaruan realtime. Kegagalannya tidak boleh
            // membatalkan transaksi utama yang sudah disimpan ke database.
            Log::warning('Notifikasi socket tidak terkirim.', [
                'event' => $event->event,
                'room' => $event->room,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
