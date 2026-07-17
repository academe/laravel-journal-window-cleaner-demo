<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Admin;

use App\Demos\WindowCleaner\Actions\SendBalanceTexts;
use App\Demos\WindowCleaner\Models\SmsMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SmsController
{
    public function send(SendBalanceTexts $sendBalanceTexts): RedirectResponse
    {
        $count = $sendBalanceTexts->run();

        return redirect()
            ->route('wc.admin.sms.outbox')
            ->with('status', "Sent {$count} balance text(s).");
    }

    public function outbox(): View
    {
        return view('demos.window-cleaner.admin.sms-outbox', [
            'messages' => SmsMessage::query()
                ->with('customer')
                ->orderByDesc('sent_at')
                ->limit(50)
                ->get(),
        ]);
    }
}
