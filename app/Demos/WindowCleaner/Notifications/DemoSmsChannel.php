<?php

namespace App\Demos\WindowCleaner\Notifications;

use App\Demos\WindowCleaner\Models\SmsMessage;
use Illuminate\Notifications\Notification;

/**
 * A stand-in for a real SMS provider channel (Twilio, Vonage, ...).
 *
 * This is the realistic integration shape: a custom notification
 * channel. In production you would swap this class for one that calls
 * a provider's API; here, messages land in wc_sms_messages and show up
 * on the SMS outbox page. Nothing leaves the machine.
 */
class DemoSmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        SmsMessage::create([
            'customer_id' => $notifiable->getKey(),
            'phone' => $notifiable->phone,
            'body' => $notification->toDemoSms($notifiable),
            'sent_at' => now(),
        ]);
    }
}
