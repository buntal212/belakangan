<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendNotificationEvent
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */

    public $userId;
    public $event;
    public $data;
    public $room;

    public function __construct($userId, $room, $event, $data)
    {
        $this->userId = $userId;
        $this->event = $event;
        $this->data = $data;
        $this->room = $room;
    }

}
