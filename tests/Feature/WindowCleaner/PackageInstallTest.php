<?php

use Illuminate\Support\Facades\Schema;

it('has the journal tables migrated', function () {
    foreach (['journals', 'journal_transactions', 'journal_checkpoints', 'journal_ledgers'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing table {$table}");
    }
});
