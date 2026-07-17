<?php

namespace App\Demos\WindowCleaner\Notifications;

use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Support\Gbp;
use Illuminate\Notifications\Notification;
use Money\Money;

class BalanceReminder extends Notification
{
    public function __construct(public Money $owed) {}

    public function via(object $notifiable): array
    {
        return [DemoSmsChannel::class];
    }

    public function toDemoSms(Customer $customer): string
    {
        $amount = Gbp::format($this->owed);

        return "Hi {$customer->name}, your Shiny & Sons window cleaning balance is "
            ."{$amount} outstanding. Pay online: ".url('/window-cleaner/portal/pay');
    }
}
