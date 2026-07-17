<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Admin;

use Academe\LaravelJournal\Models\JournalCheckpoint;
use App\Demos\WindowCleaner\Actions\CloseMonth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CloseMonthController
{
    public function show(): View
    {
        return view('demos.window-cleaner.admin.close-month', [
            'checkpoints' => JournalCheckpoint::query()
                ->orderByDesc('checkpoint_date')
                ->get()
                ->groupBy(fn (JournalCheckpoint $checkpoint) => $checkpoint->checkpoint_date->toDateString()),
            'target' => now()->subMonthNoOverflow()->endOfMonth(),
        ]);
    }

    public function store(CloseMonth $closeMonth): RedirectResponse
    {
        $result = $closeMonth->run();

        return redirect()
            ->route('wc.admin.close-month.show')
            ->with('status', sprintf(
                'Closed through %s: %d journal(s) checkpointed, %d already closed.',
                $result['date']->toFormattedDateString(),
                $result['closed'],
                $result['skipped'],
            ));
    }
}
