<?php

namespace App\Demos\WindowCleaner\Models;

use Academe\LaravelJournal\Concerns\HasJournalTransactions;
use App\Demos\WindowCleaner\Support\Gbp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Money\Money;

class Visit extends Model
{
    use HasJournalTransactions;

    protected $table = 'wc_visits';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['visited_on' => 'date'];
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
        return Gbp::money($this->price);
    }
}
