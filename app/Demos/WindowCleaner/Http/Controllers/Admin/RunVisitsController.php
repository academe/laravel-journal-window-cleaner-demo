<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Admin;

use App\Demos\WindowCleaner\Actions\RunDueVisits;
use Illuminate\Http\RedirectResponse;

class RunVisitsController
{
    public function store(RunDueVisits $runDueVisits): RedirectResponse
    {
        $result = $runDueVisits->run();

        $status = "Charged {$result['charged']} visit(s).";

        if ($result['skipped'] > 0) {
            $status = "Charged {$result['charged']} visit(s); skipped {$result['skipped']} plan(s) whose due date falls in a closed period.";
        }

        return redirect()
            ->route('wc.admin.dashboard')
            ->with('status', $status);
    }
}
