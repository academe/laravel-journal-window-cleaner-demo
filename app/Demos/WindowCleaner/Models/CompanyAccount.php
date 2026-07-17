<?php

namespace App\Demos\WindowCleaner\Models;

use Academe\LaravelJournal\Concerns\HasJournal;
use Academe\LaravelJournal\Contracts\NamesJournal;
use Illuminate\Database\Eloquent\Model;

/**
 * Stand-in owner for the business-side journals (Sales, VAT, Bank,
 * Expenses). Every journal in the package must belong to an Eloquent
 * model, so these rows exist purely to own the company's accounting
 * journals.
 */
class CompanyAccount extends Model implements NamesJournal
{
    use HasJournal;

    protected $table = 'wc_company_accounts';

    protected $guarded = [];

    public function journalDisplayName(): string
    {
        return $this->name;
    }

    public function journalDescription(): ?string
    {
        return null;
    }
}
