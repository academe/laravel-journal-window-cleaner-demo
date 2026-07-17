<?php

namespace App\Demos\WindowCleaner\Models;

use Academe\LaravelJournal\Concerns\HasJournal;
use Illuminate\Database\Eloquent\Model;

/**
 * Stand-in owner for the business-side journals (Sales, VAT, Bank).
 * Every journal in the package must belong to an Eloquent model, so
 * these rows exist purely to own the company's accounting journals.
 */
class CompanyAccount extends Model
{
    use HasJournal;

    protected $table = 'wc_company_accounts';

    protected $guarded = [];
}
