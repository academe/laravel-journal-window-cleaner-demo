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
            $status .= ' Skipped — closed period: '.implode('; ', $result['skippedPlans']).'.';
        }

        return redirect()
            ->route('wc.admin.dashboard')
            ->with('status', $status);
    }
}
