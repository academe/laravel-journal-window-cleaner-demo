<?php

namespace App\Demos\WindowCleaner\Models;

use Academe\LaravelJournal\Concerns\HasJournal;
use Academe\LaravelJournal\Contracts\NamesJournal;
use App\Demos\WindowCleaner\Support\Books;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Money\Money;

/**
 * Level A: the customer's account balance IS their journal.
 * Payments are credits (positive), charges are debits (negative),
 * so a negative balance means the customer owes money.
 */
class Customer extends Model implements NamesJournal
{
    use HasFactory, HasJournal, Notifiable;

    protected $table = 'wc_customers';

    protected $guarded = [];

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    public function servicePlans(): HasMany
    {
        return $this->hasMany(ServicePlan::class);
    }

    public function balance(): Money
    {
        return $this->journal->currentBalance();
    }

    /**
     * What the customer owes, as a positive amount (zero when in credit).
     */
    public function amountOwed(): Money
    {
        $balance = $this->balance();

        return $balance->isNegative() ? $balance->absolute() : Books::money(0);
    }

    /**
     * NamesJournal: the package uses these wherever it names this
     * customer's journal (e.g. PeriodClosed messages) — the account
     * name IS the customer, no duplicate name column needed.
     */
    public function journalDisplayName(): string
    {
        return $this->name;
    }

    public function journalDescription(): ?string
    {
        return $this->address;
    }
}
