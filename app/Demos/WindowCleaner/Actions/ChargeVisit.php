<?php

namespace App\Demos\WindowCleaner\Actions;

use Academe\LaravelJournal\Enums\EntryType;
use Academe\LaravelJournal\TransactionGroup;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\ServicePlan;
use App\Demos\WindowCleaner\Models\Visit;
use App\Demos\WindowCleaner\Support\Books;
use App\Demos\WindowCleaner\Support\TagsTransactionGroups;
use App\Demos\WindowCleaner\Support\Vat;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Money\Money;

/**
 * Charge a customer for one visit — the demo's Level B centrepiece.
 *
 * The VAT-inclusive price is split into net + VAT (VAT computed from the
 * gross and rounded down, net takes the remainder, so no penny is lost),
 * then posted as ONE balanced TransactionGroup:
 *
 *   debit  customer journal   £15.00   (Debtors: the customer owes more)
 *   credit Sales journal      £12.50   (income earned)
 *   credit VAT journal         £2.50   (owed to HMRC)
 *
 * Debits equal credits, so commit() writes all three atomically. Every
 * leg references the Visit row (the package's `reference` morph) and is
 * tagged for filtering in statements. The whole run is wrapped in an
 * outer DB::transaction so the Visit row and its journal legs are atomic
 * too — if commit() throws, no orphaned Visit is left behind.
 *
 * Scheduling is NOT this class's job: ad-hoc visits call it directly;
 * scheduled runs go through RunDueVisits, which also rolls the plan.
 */
class ChargeVisit
{
    use TagsTransactionGroups;

    public function run(
        Customer $customer,
        Service $service,
        Money $grossPrice,
        ?ServicePlan $plan = null,
        ?CarbonInterface $date = null,
    ): Visit {
        return DB::transaction(function () use ($customer, $service, $grossPrice, $plan, $date) {
            $date ??= now();

            ['net' => $net, 'vat' => $vat] = Vat::split($grossPrice);

            $visit = Visit::create([
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'service_plan_id' => $plan?->id,
                'price' => (int) $grossPrice->getAmount(),
                'visited_on' => $date->toDateString(),
            ]);

            $memo = "{$service->name} on {$date->toDateString()}";

            $groupUuid = TransactionGroup::make()
                ->addTransaction($customer->journal, EntryType::Debit, $grossPrice, $memo, $visit, $date)
                ->addTransaction(Books::salesJournal(), EntryType::Credit, $net, $memo, $visit, $date)
                ->addTransaction(Books::vatJournal(), EntryType::Credit, $vat, "VAT on {$memo}", $visit, $date)
                ->commit();

            $this->tagGroup($groupUuid, [
                'kind' => 'visit',
                'service' => Str::slug($service->name),
            ]);

            return $visit;
        });
    }
}
