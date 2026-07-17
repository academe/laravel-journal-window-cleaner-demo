<?php

namespace App\Demos\WindowCleaner\Models;

use Academe\LaravelJournal\Concerns\HasJournal;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasJournal;

    protected $table = 'wc_wallets';

    protected $guarded = [];
}
