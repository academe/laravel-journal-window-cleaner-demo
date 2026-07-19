<?php

namespace App\Demos\WindowCleaner\Models;

use Academe\LaravelJournal\Concerns\HasJournalTransactions;
use App\Demos\WindowCleaner\Support\Books;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Money\Money;

class Payment extends Model
{
    use HasJournalTransactions;

    protected $table = 'wc_payments';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function amountAsMoney(): Money
    {
        return Books::money($this->amount);
    }
}
