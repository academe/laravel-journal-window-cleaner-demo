<?php

namespace Database\Seeders;

use App\Demos\WindowCleaner\Actions\CloseMonth;
use App\Demos\WindowCleaner\Actions\EnsureBooksExist;
use App\Demos\WindowCleaner\Actions\RecordPayment;
use App\Demos\WindowCleaner\Actions\RunDueVisits;
use App\Demos\WindowCleaner\Actions\SendBalanceTexts;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\ServicePlan;
use App\Demos\WindowCleaner\Support\Books;
use App\Demos\WindowCleaner\Support\Gbp;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Money\Money;

/**
 * Builds the demo world by REPLAYING history through the same action
 * classes the live app uses: day by day for ~6 months, RunDueVisits
 * charges whatever is due and each customer's payment persona decides
 * whether to pay. The books therefore balance by construction — the
 * seeder contains no posting logic of its own.
 *
 * Everything is hardcoded (no randomness), so seeding is deterministic.
 * Run with: php artisan migrate:fresh --seed
 */
class WindowCleanerSeeder extends Seeder
{
    private const HISTORY_MONTHS = 6;

    public function run(): void
    {
        if (Customer::query()->exists()) {
            $this->command?->warn('Window cleaner demo already seeded; use php artisan migrate:fresh --seed to rebuild.');

            return;
        }

        app(EnsureBooksExist::class)->run();

        $services = $this->createServices();
        $start = today()->subMonths(self::HISTORY_MONTHS)->startOfWeek(); // a Monday
        $customers = $this->createCustomers($services, $start);

        $this->replayHistory($customers, $start);

        // Leave the outbox populated so the SMS pages have content.
        app(SendBalanceTexts::class)->run();
    }

    /**
     * @return Collection<string, Service>
     */
    private function createServices(): Collection
    {
        return collect(['Front only', 'Full house', 'Conservatory', 'Gutter clean'])
            ->mapWithKeys(fn (string $name) => [$name => Service::create(['name' => $name])]);
    }

    /**
     * Each row: [name, address, phone, persona, plans], where each plan
     * is [service, price £ (VAT-inc), interval weeks, weekday offset from
     * the Monday the history starts on].
     *
     * @param  Collection<string, Service>  $services
     * @return Collection<int, Customer>
     */
    private function createCustomers(Collection $services, CarbonInterface $start): Collection
    {
        $spec = [
            ['Margaret Whitfield', '1 Acacia Avenue', '07700 900001', 'prompt', [['Full house', '15.00', 2, 0]]],
            ['Raj Patel', '2 Acacia Avenue', '07700 900002', 'prompt', [['Front only', '8.50', 2, 0], ['Gutter clean', '25.00', 8, 2]]],
            ['Sofia Andersson', '14 Mill Lane', '07700 900003', 'overpayer', [['Full house', '14.99', 4, 1]]],
            ['Derek Hound', '15 Mill Lane', '07700 900004', 'slow', [['Full house', '16.00', 4, 1]]],
            ['Chen Wei', '3 High Street', '07700 900005', 'prompt', [['Front only', '9.00', 1, 3]]],
            ['Amara Okafor', '4 High Street', '07700 900006', 'slow', [['Full house', '15.50', 2, 3], ['Conservatory', '12.00', 4, 3]]],
            ['Bill Sykes', '5 Canal Walk', '07700 900007', 'delinquent', [['Front only', '8.00', 2, 4]]],
            ['Freya Nilsen', '6 Canal Walk', '07700 900008', 'prompt', [['Full house', '18.00', 2, 4]]],
            ['George Trent', '7 Orchard Close', '07700 900009', 'prompt', [['Conservatory', '11.00', 4, 0], ['Gutter clean', '30.00', 8, 0]]],
            ['Priya Sharma', '8 Orchard Close', '07700 900010', 'delinquent', [['Full house', '13.50', 4, 2]]],
        ];

        return collect($spec)->map(function (array $row) use ($services, $start) {
            [$name, $address, $phone, $persona, $plans] = $row;

            $customer = Customer::create(compact('name', 'address', 'phone'));
            $customer->initJournal('GBP')->assignToLedger(Books::debtorsLedger());

            // Transient property, read only by maybePay() during replay.
            $customer->persona = $persona;

            foreach ($plans as [$serviceName, $price, $intervalWeeks, $dayOffset]) {
                ServicePlan::create([
                    'customer_id' => $customer->id,
                    'service_id' => $services[$serviceName]->id,
                    'price' => (int) Gbp::parse($price)->getAmount(),
                    'interval_weeks' => $intervalWeeks,
                    'next_due_on' => $start->copy()->addDays($dayOffset),
                ]);
            }

            return $customer;
        });
    }

    /**
     * @param  Collection<int, Customer>  $customers
     */
    private function replayHistory(Collection $customers, CarbonInterface $start): void
    {
        $runDueVisits = app(RunDueVisits::class);
        $recordPayment = app(RecordPayment::class);

        // Close the first month of history once it has fully passed, so
        // the demo starts with a real checkpoint in place (Tour page).
        $closeOn = $start->copy()->endOfMonth()->addDay()->startOfDay();

        for ($day = $start->copy()->startOfDay(); $day->lte(today()); $day = $day->copy()->addDay()) {
            $runDueVisits->run($day);

            foreach ($customers as $customer) {
                $this->maybePay($recordPayment, $customer, $day);
            }

            if ($day->isSameDay($closeOn)) {
                app(CloseMonth::class)->run($day);
            }
        }
    }

    /**
     * Apply the customer's payment persona for one day of the replay.
     * Rule-based (no randomness) so the seed is deterministic.
     */
    private function maybePay(RecordPayment $recordPayment, Customer $customer, CarbonInterface $day): void
    {
        $balance = $customer->journal->balanceOn($day);
        $owed = $balance->isNegative() ? $balance->absolute() : Gbp::money(0);

        if ($customer->persona === 'prompt' && $day->isFriday() && $owed->isPositive()) {
            $recordPayment->run($customer, $owed, $day);
        }

        if ($customer->persona === 'overpayer' && $day->day === 1) {
            $recordPayment->run($customer, Gbp::parse('20.00'), $day);
        }

        if ($customer->persona === 'slow' && $day->isFriday() && $day->day <= 7
            && $owed->greaterThanOrEqual(Gbp::parse('1.00'))) {
            $recordPayment->run($customer, $this->half($owed), $day);
        }

        // 'delinquent': never pays.
    }

    private function half(Money $amount): Money
    {
        return Gbp::money(intdiv((int) $amount->getAmount(), 2));
    }
}
