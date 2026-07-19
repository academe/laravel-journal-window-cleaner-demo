<?php

namespace App\Demos\WindowCleaner\Models;

use App\Demos\WindowCleaner\Support\Books;
use Carbon\CarbonInterface;
use Database\Factories\ServicePlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Money\Money;

class ServicePlan extends Model
{
    use HasFactory;

    protected $table = 'wc_service_plans';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'next_due_on' => 'date',
            'active' => 'boolean',
        ];
    }

    protected static function newFactory(): ServicePlanFactory
    {
        return ServicePlanFactory::new();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function priceAsMoney(): Money
    {
        return Books::money($this->price);
    }

    public function isDueOn(CarbonInterface $date): bool
    {
        return $this->active && $this->next_due_on->lte($date);
    }

    /**
     * Advance next_due_on in interval_weeks steps until it is beyond
     * $from. Stepping in whole weeks keeps the visit on its weekday.
     */
    public function rollForward(CarbonInterface $from): void
    {
        do {
            $this->next_due_on = $this->next_due_on->addWeeks($this->interval_weeks);
        } while ($this->next_due_on->lte($from));

        $this->save();
    }
}
