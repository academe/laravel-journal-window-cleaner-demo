<?php

namespace App\Demos\WindowCleaner\Models;

use Academe\LaravelJournal\Concerns\HasJournalTransactions;
use App\Demos\WindowCleaner\Support\Gbp;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Money\Money;

/**
 * A purchase of supplies or equipment, paid immediately from the bank.
 * The price is gross (VAT-inclusive) minor units; RecordPurchase splits
 * out the input VAT when posting.
 */
class Purchase extends Model
{
    use HasFactory, HasJournalTransactions;

    protected $table = 'wc_purchases';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['purchased_on' => 'date'];
    }

    protected static function newFactory(): PurchaseFactory
    {
        return PurchaseFactory::new();
    }

    public function priceAsMoney(): Money
    {
        return Gbp::money($this->price);
    }
}
