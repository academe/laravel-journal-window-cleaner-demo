<?php

namespace App\Demos\WindowCleaner\Models;

use Academe\LaravelJournal\Concerns\HasJournal;
use Academe\LaravelJournal\Contracts\NamesJournal;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model implements NamesJournal
{
    use HasJournal;

    protected $table = 'wc_wallets';

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
