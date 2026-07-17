# laravel-journal demos

Runnable, readable demos of [academe/laravel-journal](https://github.com/academe/laravel-ledger)
— accounting journals and double-entry bookkeeping for Eloquent models.

## Run it

1. `composer install`
2. `cp .env.example .env && php artisan key:generate && touch database/database.sqlite`
3. `php artisan migrate:fresh --seed`

With [Laravel Herd](https://herd.laravel.com) the site is already served at
`https://laravel-journal-window-cleaner-demo.test/window-cleaner`; otherwise run
`php artisan serve` and browse to `/window-cleaner`.

No npm, no build step, SQLite only.

## The window cleaner demo

"Shiny & Sons" is a VAT-registered window cleaning round: customers subscribe
to services at their own prices and schedules, hold an account balance, pay
online (emulated), and get balance texts (emulated). Six months of history are
seeded by replaying the business day by day through the same code the buttons
use.

One scenario demonstrates the package's three levels **on the same journals**:

| Level | What | Where to look |
| --- | --- | --- |
| A | Each customer's balance IS their journal | `app/Demos/WindowCleaner/Models/Customer.php`, Tour → Playground |
| B | Every charge/payment is a balanced `TransactionGroup` (with VAT split via `Gbp::vatSplit`) | `app/Demos/WindowCleaner/Actions/ChargeVisit.php`, `RecordPayment.php` |
| C | Typed ledgers: Debtors + Bank = VAT owed + Sales, live | Admin → Books, `Actions/EnsureBooksExist.php` |

Plus: checkpoints (Admin → Close month), transaction references
(`Visit`/`Payment` ⟷ journal entries), and tags (statement pages).

Start at **/window-cleaner** and follow the Tour.

## Structure

Each demo lives in its own namespace so more can join:

    app/Demos/WindowCleaner/{Models,Actions,Console,Support,Notifications,Http}
    routes/demos/window-cleaner.php
    resources/views/demos/window-cleaner/

## Tests

    php artisan test

The feature tests double as executable documentation — start with
`tests/Feature/WindowCleaner/ChargeVisitTest.php`.
