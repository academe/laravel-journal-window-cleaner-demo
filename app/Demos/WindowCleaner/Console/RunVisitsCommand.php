<?php

namespace App\Demos\WindowCleaner\Console;

use App\Demos\WindowCleaner\Actions\RunDueVisits;
use Illuminate\Console\Command;

class RunVisitsCommand extends Command
{
    protected $signature = 'demo:run-visits';

    protected $description = 'Charge every window-cleaning plan due on or before today';

    public function handle(RunDueVisits $runDueVisits): int
    {
        $result = $runDueVisits->run();

        $this->info("Charged {$result['charged']} due visit(s), skipped {$result['skipped']} plan(s) in a closed period.");

        return self::SUCCESS;
    }
}
