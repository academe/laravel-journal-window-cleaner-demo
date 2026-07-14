# Window Cleaner Demo Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the "Shiny & Sons" window-cleaner demo app that demonstrates all three levels of `academe/laravel-journal` (running balances, `TransactionGroup` double entry, typed ledgers) plus checkpoints, references, and tags — per the approved spec at `docs/superpowers/specs/2026-07-14-window-cleaner-demo-design.md`.

**Architecture:** One live business. Each `Customer` owns a journal (level A). Two action classes (`ChargeVisit`, `RecordPayment`) do ALL posting as balanced `TransactionGroup`s (level B). Every journal sits in one of four typed ledgers — Debtors/Bank (asset), Sales (income), VAT owed (liability) — so the accounting equation holds live (level C). A day-by-day seeder replays ~6 months of history through those same actions.

**Tech Stack:** Laravel 13 (PHP 8.5), plain Blade (NO npm/Vite), SQLite, Pest 4, `academe/laravel-journal` (dev-main from GitHub), `moneyphp/money`.

## Global Constraints

Every task's requirements implicitly include all of these:

- **NO git commits.** Jason commits manually. Ignore any skill instruction to commit; never run `git commit`.
- **NO npm.** Never run npm/vite. Views use `<link rel="stylesheet" href="/css/demo.css">`, never `@vite`.
- Package installs as `academe/laravel-journal:dev-main` from VCS repo `https://github.com/academe/laravel-ledger` (repo name differs from package name — that is correct).
- All money is **GBP, integer minor units** in DB columns; `Money\Money` everywhere in code. Consumer prices are **VAT-inclusive**; rate is `config('demo.vat_rate_percent')` = 20.
- Demo tables are prefixed `wc_`.
- All journal posting goes through `ChargeVisit` / `RecordPayment` (exceptions: the Playground page and package-API code inside those two actions).
- New app code lives under `app/Demos/WindowCleaner/` (namespace `App\Demos\WindowCleaner\...`) — this structure is pre-approved. Views: `resources/views/demos/window-cleaner/`. Routes: `routes/demos/window-cleaner.php`, all inside `Route::prefix('window-cleaner')->name('wc.')`.
- Tests are Pest feature tests in `tests/Feature/WindowCleaner/`, using `RefreshDatabase` (enabled globally in `tests/Pest.php`). Run with `php artisan test --filter=<name>`.
- Follow the repo's `CLAUDE.md` (Laravel Boost guidelines). Run `vendor/bin/pint --dirty` after each task.
- This is docs-by-example: code the Tour points at (`ChargeVisit`, `RecordPayment`, `CloseMonth`, `EnsureBooksExist`, `Gbp`) carries generous doc-block comments explaining the package concepts in play. Controllers/views stay lean.

## Package API cheat-sheet (verified against package source)

```php
// Owner models:  use Academe\LaravelJournal\Concerns\HasJournal;
$journal = $model->initJournal('GBP');            // throws JournalAlreadyExists on repeat
$journal->assignToLedger($ledger);                // returns $journal
$journal->credit(Money|int, ?string $memo, ?CarbonInterface $postDate, ?string $groupUuid): JournalTransaction;
$journal->debit(...same...);
$journal->currentBalance(): Money;                // credit - debit, excludes future-dated
$journal->balanceOn(CarbonInterface $date): Money;
$journal->checkpoint(CarbonInterface|string $date): JournalCheckpoint;  // freezes through end of $date
$journal->latestCheckpoint(): ?JournalCheckpoint; // ->checkpoint_date is Carbon
$journal->transactions(): HasMany;                // JournalTransaction
$journal->owner: Model (morph);  $journal->currency_code: string;

// Referenced models:  use Academe\LaravelJournal\Concerns\HasJournalTransactions;
$visit->journalTransactions: Collection<JournalTransaction>;   // morphMany via 'reference'

// Double entry:
use Academe\LaravelJournal\TransactionGroup;
$uuid = TransactionGroup::make()
    ->addTransaction($journal, 'credit'|'debit', Money $m, ?string $memo, ?Model $reference, ?CarbonInterface $postDate)
    ->commit();   // string UUID; throws DebitsAndCreditsDoNotEqual, TransactionCouldNotBeProcessed (wraps PeriodClosed etc. as ->getPrevious())

// Transactions:
$t->amount: Money (credit positive, debit negative);  $t->tags = ['k' => 'v']; $t->save();
$t->memo, $t->post_date (Carbon), $t->transaction_group (uuid|null);

// Ledgers:
use Academe\LaravelJournal\Models\Ledger;
use Academe\LaravelJournal\Enums\StandardLedgerType;   // ASSET, LIABILITY, EQUITY, INCOME, EXPENSE
$ledger = Ledger::create(['name' => 'Assets', 'type' => StandardLedgerType::ASSET]);
$ledger->currentBalance('GBP'): Money;   // debit-normal: debit-credit; credit-normal: credit-debit
$ledger->journals(): HasMany<Journal>;

// Exceptions namespace: Academe\LaravelJournal\Exceptions\{PeriodClosed, CurrencyMismatch,
//   TransactionCouldNotBeProcessed, DebitsAndCreditsDoNotEqual, InvalidJournalEntryValue, ...}
// Cached journals.balance recomputes on commit (batched); computed methods are always accurate.
// A posted Money's currency must equal the journal's or CurrencyMismatch is thrown.
```

## File Structure

```text
config/demo.php                                      VAT rate
public/css/demo.css                                  classless-ish stylesheet (only styling in the app)
routes/web.php                                       landing route + require of demo routes
routes/demos/window-cleaner.php                      all /window-cleaner routes
app/Demos/WindowCleaner/
    Models/          Customer, Service, ServicePlan, Visit, Payment, CompanyAccount, SmsMessage, Wallet
    Actions/         EnsureBooksExist, ChargeVisit, RecordPayment, RunDueVisits, CloseMonth, SendBalanceTexts
    Support/         Gbp (format/parse/VAT split), Books (ledger+journal lookups), JournalName, CurrentCustomer, TagsTransactionGroups (trait)
    Notifications/   BalanceReminder, DemoSmsChannel
    Console/         RunVisitsCommand
    Http/Controllers/Admin/     DashboardController, CustomerController, RunVisitsController, BooksController, CloseMonthController, SmsController
    Http/Controllers/Portal/    SwitchController, AccountController, PaymentController
    Http/Controllers/Tour/      TourController, PlaygroundController
resources/views/landing.blade.php
resources/views/demos/window-cleaner/                layout, home, admin/*, portal/*, tour/*
database/migrations/2026_07_14_1000{01,02,03}_*.php  wc_* tables (three migrations)
database/factories/                                  CustomerFactory, ServiceFactory, ServicePlanFactory
database/seeders/WindowCleanerSeeder.php
tests/Feature/WindowCleaner/                         one test file per task
```

Route map (all named `wc.*`, all prefixed `/window-cleaner`):

| Method+URI (under /window-cleaner) | Name | Task |
| --- | --- | --- |
| GET / | wc.home | 2 |
| GET /admin | wc.admin.dashboard | 13 |
| GET /admin/customers, /admin/customers/{customer} | wc.admin.customers.index / .show | 13 |
| POST /admin/customers/{customer}/visits | wc.admin.visits.store | 13 |
| POST /admin/customers/{customer}/payments | wc.admin.payments.store | 13 |
| POST /admin/run-visits | wc.admin.run-visits | 14 |
| GET /admin/books | wc.admin.books | 14 |
| GET+POST /admin/close-month | wc.admin.close-month.show / .store | 14 |
| POST /admin/send-balance-texts | wc.admin.sms.send | 15 |
| GET /admin/sms-outbox | wc.admin.sms.outbox | 15 |
| GET /portal, POST /portal/act-as/{customer} | wc.portal.switch / .switch.store | 16 |
| GET /portal/account | wc.portal.account | 16 |
| GET+POST /portal/pay | wc.portal.pay / .pay.store | 16 |
| GET /portal/paid/{payment} | wc.portal.paid | 16 |
| GET /tour/{page} (level-a, level-b, level-c, checkpoints) | wc.tour.show | 17 |
| GET+POST /tour/playground | wc.tour.playground / .playground.store | 17 |

---

### Task 1: Install and wire the package

**Files:**
- Modify: `composer.json` (via composer commands)
- Modify: `tests/Pest.php`
- Create: `tests/Feature/WindowCleaner/PackageInstallTest.php`
- Created by artisan: `config/journal.php`, `database/migrations/*_create_journal_tables.php` (published)

**Interfaces:**
- Consumes: GitHub repo `academe/laravel-ledger` main branch (must already contain the `illuminate/* ^12.0 || ^13.0` constraint widening — verify before starting).
- Produces: package installed; tables `journals`, `journal_transactions`, `journal_checkpoints`, `journal_ledgers` migrated; `RefreshDatabase` on for all Feature tests.

- [ ] **Step 1: Pre-flight — confirm the pushed package supports Laravel 13**

Run: `git ls-remote https://github.com/academe/laravel-ledger main` (confirm reachable), then:

```bash
composer config repositories.laravel-journal vcs https://github.com/academe/laravel-ledger
composer require academe/laravel-journal:dev-main
```

Expected: installs cleanly. **If composer reports the package requires `illuminate/database ^12.0`:** the constraint widening has not been pushed to GitHub yet. STOP and tell Jason to push the package repo (`c:/Users/jason/Documents/dev/laravel-journal`, already edited locally) before continuing.

- [ ] **Step 2: Publish config + migrations, migrate**

```bash
php artisan vendor:publish --tag=journal-config
php artisan vendor:publish --tag=journal-migrations
php artisan migrate
```

Expected: `config/journal.php` appears (its `base_currency` default is already `GBP` — leave it); journal migrations appear in `database/migrations/` and run.

- [ ] **Step 3: Enable RefreshDatabase for Feature tests**

In `tests/Pest.php`, ensure the Feature extension uses RefreshDatabase (uncomment/add):

```php
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');
```

- [ ] **Step 4: Write the smoke test**

`tests/Feature/WindowCleaner/PackageInstallTest.php`:

```php
<?php

use Illuminate\Support\Facades\Schema;

it('has the journal tables migrated', function () {
    foreach (['journals', 'journal_transactions', 'journal_checkpoints', 'journal_ledgers'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing table {$table}");
    }
});
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --filter=PackageInstallTest`
Expected: PASS (1 test)

---

### Task 2: Demo config, stylesheet, layout, landing page, route skeleton

**Files:**
- Create: `config/demo.php`, `public/css/demo.css`, `resources/views/landing.blade.php`, `resources/views/demos/window-cleaner/layout.blade.php`, `resources/views/demos/window-cleaner/home.blade.php`, `routes/demos/window-cleaner.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/WindowCleaner/PagesTest.php`

**Interfaces:**
- Produces: `config('demo.vat_rate_percent')` = 20; Blade layout `demos.window-cleaner.layout` with sections `title` and `content` and flash display for `session('status')` / `session('error')`; route names `home`, `wc.home`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/PagesTest.php`:

```php
<?php

it('shows the demo repo landing page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('laravel-journal')
        ->assertSee('Window cleaner');
});

it('shows the window cleaner home page', function () {
    $this->get('/window-cleaner')
        ->assertOk()
        ->assertSee('Shiny & Sons');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PagesTest`
Expected: FAIL (route `/window-cleaner` returns 404; `/` shows the default welcome view)

- [ ] **Step 3: Implement**

`config/demo.php`:

```php
<?php

return [
    // UK standard VAT rate, percent. Consumer prices in the demo are
    // VAT-inclusive; Gbp::vatSplit() extracts the VAT portion at this rate.
    'vat_rate_percent' => 20,
];
```

`public/css/demo.css` (the app's ONLY styling — keep it boring):

```css
:root { --ink: #1a1a1a; --soft: #666; --line: #ddd; --accent: #0a6e4f; --bad: #a02020; --wash: #f5f4f0; }
* { box-sizing: border-box; }
body { font: 16px/1.5 system-ui, sans-serif; color: var(--ink); margin: 0; }
header, main, footer { max-width: 60rem; margin: 0 auto; padding: 0.75rem 1rem; }
header { display: flex; gap: 1.5rem; align-items: baseline; border-bottom: 2px solid var(--ink); }
header nav { display: flex; gap: 1rem; }
footer { color: var(--soft); font-size: 0.85rem; border-top: 1px solid var(--line); margin-top: 2rem; }
a { color: var(--accent); }
h1 { font-size: 1.5rem; } h2 { font-size: 1.2rem; margin-top: 2rem; }
table { border-collapse: collapse; width: 100%; margin: 1rem 0; }
th, td { text-align: left; padding: 0.35rem 0.75rem 0.35rem 0; border-bottom: 1px solid var(--line); vertical-align: top; }
td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
form.stack { display: grid; gap: 0.5rem; max-width: 22rem; margin: 1rem 0; }
input, select, button { font: inherit; padding: 0.35rem 0.5rem; }
button { background: var(--accent); color: white; border: 0; cursor: pointer; }
button.plain { background: none; color: var(--accent); text-decoration: underline; padding: 0; }
.flash { background: var(--wash); border-left: 4px solid var(--accent); padding: 0.5rem 0.75rem; }
.flash.error { border-color: var(--bad); }
.owes { color: var(--bad); } .credit { color: var(--accent); }
.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: 1rem; }
.cards > div { border: 1px solid var(--line); padding: 1rem; }
.cards .big { font-size: 1.6rem; font-variant-numeric: tabular-nums; }
pre { background: var(--wash); padding: 1rem; overflow-x: auto; font-size: 0.85rem; }
.sms { max-width: 24rem; }
.sms .msg { background: var(--wash); border-radius: 1rem; padding: 0.6rem 0.9rem; margin: 0.5rem 0; }
.sms .meta { color: var(--soft); font-size: 0.8rem; }
small.tag { background: var(--wash); border: 1px solid var(--line); border-radius: 0.5rem; padding: 0 0.4rem; margin-right: 0.25rem; }
```

`resources/views/demos/window-cleaner/layout.blade.php`:

```blade
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Shiny & Sons') — laravel-journal demo</title>
    <link rel="stylesheet" href="/css/demo.css">
</head>
<body>
<header>
    <strong><a href="/window-cleaner">Shiny &amp; Sons</a></strong>
    <nav>
        <a href="/window-cleaner/admin">Admin</a>
        <a href="/window-cleaner/portal/account">Customer portal</a>
        <a href="/window-cleaner/tour/level-a">Tour</a>
    </nav>
</header>
<main>
    @if (session('status'))<p class="flash">{{ session('status') }}</p>@endif
    @if (session('error'))<p class="flash error">{{ session('error') }}</p>@endif
    @yield('content')
</main>
<footer>
    <p>A demo of <a href="https://github.com/academe/laravel-ledger">academe/laravel-journal</a>.
    No real money, cards, or texts are involved.</p>
</footer>
</body>
</html>
```

`resources/views/demos/window-cleaner/home.blade.php`:

```blade
@extends('demos.window-cleaner.layout')
@section('title', 'Shiny & Sons')
@section('content')
    <h1>Shiny &amp; Sons — window cleaning</h1>
    <p>A VAT-registered window cleaning round. Customers have services at individual
    prices and schedules, hold an account balance, and can pay online. Every number
    on every page comes out of <code>academe/laravel-journal</code>.</p>
    <div class="cards">
        <div><h2><a href="/window-cleaner/admin">Admin</a></h2>
            <p>The window cleaner's side: run the round, take payments, read the books, close the month, text balances.</p></div>
        <div><h2><a href="/window-cleaner/portal/account">Customer portal</a></h2>
            <p>What a customer sees: their balance, their services, their statement, and a pay-online form.</p></div>
        <div><h2><a href="/window-cleaner/tour/level-a">Tour</a></h2>
            <p>The guided tour: which package feature powers which page, with the actual code.</p></div>
    </div>
@endsection
```

`resources/views/landing.blade.php`:

```blade
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>laravel-journal demos</title>
    <link rel="stylesheet" href="/css/demo.css">
</head>
<body>
<main>
    <h1>laravel-journal demos</h1>
    <p>Runnable, readable demos of <a href="https://github.com/academe/laravel-ledger">academe/laravel-journal</a> —
    accounting journals and double-entry bookkeeping for Eloquent models.</p>
    <div class="cards">
        <div>
            <h2><a href="/window-cleaner">Window cleaner</a></h2>
            <p>One business showing all three levels: per-customer running balances,
            balanced transaction groups with VAT, and typed ledgers with the
            accounting equation holding live. Plus checkpoints, tags, references,
            emulated online payment and SMS.</p>
        </div>
    </div>
</main>
</body>
</html>
```

`routes/demos/window-cleaner.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::prefix('window-cleaner')->name('wc.')->group(function () {
    Route::view('/', 'demos.window-cleaner.home')->name('home');
});
```

`routes/web.php` (replace contents):

```php
<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');

require __DIR__.'/demos/window-cleaner.php';
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PagesTest`
Expected: PASS (2 tests). Then run `vendor/bin/pint --dirty`.

---

### Task 3: `Gbp` money helper (format / parse / VAT split)

**Files:**
- Create: `app/Demos/WindowCleaner/Support/Gbp.php`
- Test: `tests/Feature/WindowCleaner/GbpTest.php`

**Interfaces:**
- Produces: `Gbp::format(Money $m): string` ("£15.00", "-£3.00"; non-GBP prefixed with code e.g. "USD 5.00"); `Gbp::parse(string $decimal): Money` ("15.00" → GBP 1500); `Gbp::money(int $minorUnits): Money`; `Gbp::vatSplit(Money $gross): array{net: Money, vat: Money}`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/GbpTest.php`:

```php
<?php

use App\Demos\WindowCleaner\Support\Gbp;
use Money\Money;

it('formats GBP money with a pound sign', function () {
    expect(Gbp::format(Money::GBP(1500)))->toBe('£15.00')
        ->and(Gbp::format(Money::GBP(-300)))->toBe('-£3.00')
        ->and(Gbp::format(Money::GBP(5)))->toBe('£0.05')
        ->and(Gbp::format(Money::USD(500)))->toBe('USD 5.00');
});

it('parses decimal strings into GBP minor units', function () {
    expect(Gbp::parse('15.00')->getAmount())->toBe('1500')
        ->and(Gbp::parse('8.5')->getAmount())->toBe('850')
        ->and(Gbp::parse('0.01')->getAmount())->toBe('1');
});

it('splits VAT-inclusive prices without losing a penny', function (string $gross, string $net, string $vat) {
    $split = Gbp::vatSplit(Gbp::parse($gross));

    expect($split['net']->getAmount())->toBe($net)
        ->and($split['vat']->getAmount())->toBe($vat)
        ->and($split['net']->add($split['vat'])->equals(Gbp::parse($gross)))->toBeTrue();
})->with([
    '£15.00' => ['15.00', '1250', '250'],
    '£14.99 (awkward penny)' => ['14.99', '1250', '249'],
    '£8.50' => ['8.50', '709', '141'],
]);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=GbpTest`
Expected: FAIL — `Class "App\Demos\WindowCleaner\Support\Gbp" not found`

- [ ] **Step 3: Implement**

`app/Demos/WindowCleaner/Support/Gbp.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Support;

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;

/**
 * GBP display, parsing, and VAT arithmetic for the demo.
 *
 * The package stores integer minor units and exposes Money values;
 * this helper is the only place the demo converts to and from the
 * strings that forms and pages use.
 */
final class Gbp
{
    public static function money(int $minorUnits): Money
    {
        return new Money($minorUnits, new Currency('GBP'));
    }

    public static function format(Money $money): string
    {
        $decimal = (new DecimalMoneyFormatter(new ISOCurrencies))->format($money->absolute());
        $sign = $money->isNegative() ? '-' : '';

        return $money->getCurrency()->getCode() === 'GBP'
            ? "{$sign}£{$decimal}"
            : "{$sign}{$money->getCurrency()->getCode()} {$decimal}";
    }

    public static function parse(string $decimal): Money
    {
        return (new DecimalMoneyParser(new ISOCurrencies))->parse($decimal, new Currency('GBP'));
    }

    /**
     * Split a VAT-inclusive gross into net + VAT.
     *
     * Money::allocate() distributes the remainder pennies to the first
     * share, so net + vat always reassembles the exact gross — no
     * penny is ever created or lost (try £14.99).
     *
     * @return array{net: Money, vat: Money}
     */
    public static function vatSplit(Money $gross): array
    {
        [$net, $vat] = $gross->allocate([100, (int) config('demo.vat_rate_percent')]);

        return ['net' => $net, 'vat' => $vat];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=GbpTest`
Expected: PASS (3 tests, 5 datasets total). Then `vendor/bin/pint --dirty`.

---

### Task 4: Books foundation — `CompanyAccount`, ledgers, `Books`, `EnsureBooksExist`

**Files:**
- Create: `database/migrations/2026_07_14_100001_create_wc_company_accounts_table.php`
- Create: `app/Demos/WindowCleaner/Models/CompanyAccount.php`
- Create: `app/Demos/WindowCleaner/Support/Books.php`
- Create: `app/Demos/WindowCleaner/Actions/EnsureBooksExist.php`
- Test: `tests/Feature/WindowCleaner/BooksFoundationTest.php`

**Interfaces:**
- Produces: `EnsureBooksExist::run(): void` (idempotent); `Books::salesJournal()/vatJournal()/bankJournal(): Journal`; `Books::debtorsLedger()/bankLedger()/salesLedger()/vatLedger(): Ledger`; constants `Books::LEDGER_DEBTORS = 'Debtors'`, `LEDGER_BANK = 'Bank'`, `LEDGER_SALES = 'Sales'`, `LEDGER_VAT = 'VAT owed'`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/BooksFoundationTest.php`:

```php
<?php

use Academe\LaravelJournal\Enums\StandardLedgerType;
use Academe\LaravelJournal\Models\Ledger;
use App\Demos\WindowCleaner\Actions\EnsureBooksExist;
use App\Demos\WindowCleaner\Models\CompanyAccount;
use App\Demos\WindowCleaner\Support\Books;

it('creates the four typed ledgers and three company account journals, idempotently', function () {
    app(EnsureBooksExist::class)->run();
    app(EnsureBooksExist::class)->run(); // second run must change nothing

    expect(Ledger::count())->toBe(4)
        ->and(CompanyAccount::count())->toBe(3)
        ->and(Books::debtorsLedger()->type)->toBe(StandardLedgerType::ASSET)
        ->and(Books::bankLedger()->type)->toBe(StandardLedgerType::ASSET)
        ->and(Books::salesLedger()->type)->toBe(StandardLedgerType::INCOME)
        ->and(Books::vatLedger()->type)->toBe(StandardLedgerType::LIABILITY)
        ->and(Books::salesJournal()->currency_code)->toBe('GBP')
        ->and(Books::salesJournal()->ledger->name)->toBe(Books::LEDGER_SALES)
        ->and(Books::vatJournal()->ledger->name)->toBe(Books::LEDGER_VAT)
        ->and(Books::bankJournal()->ledger->name)->toBe(Books::LEDGER_BANK);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=BooksFoundationTest`
Expected: FAIL — class not found

- [ ] **Step 3: Implement**

`database/migrations/2026_07_14_100001_create_wc_company_accounts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stand-in owner models for pure accounting accounts (Sales, VAT,
        // Bank). A journal must be owned by a model; these rows exist to
        // own the business-side journals. See "Why journals are owned by
        // models" in the package README.
        Schema::create('wc_company_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wc_company_accounts');
    }
};
```

`app/Demos/WindowCleaner/Models/CompanyAccount.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Models;

use Academe\LaravelJournal\Concerns\HasJournal;
use Illuminate\Database\Eloquent\Model;

class CompanyAccount extends Model
{
    use HasJournal;

    protected $table = 'wc_company_accounts';

    protected $guarded = [];
}
```

`app/Demos/WindowCleaner/Support/Books.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Support;

use Academe\LaravelJournal\Models\Journal;
use Academe\LaravelJournal\Models\Ledger;
use App\Demos\WindowCleaner\Models\CompanyAccount;

/**
 * Named lookups into the business's books (Level C).
 *
 * Four typed ledgers cover the accounting equation for this business:
 * Debtors + Bank (assets) = VAT owed (liability) + Sales (income).
 * Every customer journal lives in Debtors; the three company account
 * journals live in their own ledgers.
 */
final class Books
{
    public const SALES = 'Sales';

    public const VAT = 'VAT';

    public const BANK = 'Bank';

    public const LEDGER_DEBTORS = 'Debtors';

    public const LEDGER_BANK = 'Bank';

    public const LEDGER_SALES = 'Sales';

    public const LEDGER_VAT = 'VAT owed';

    public static function salesJournal(): Journal
    {
        return self::accountJournal(self::SALES);
    }

    public static function vatJournal(): Journal
    {
        return self::accountJournal(self::VAT);
    }

    public static function bankJournal(): Journal
    {
        return self::accountJournal(self::BANK);
    }

    public static function debtorsLedger(): Ledger
    {
        return self::ledger(self::LEDGER_DEBTORS);
    }

    public static function bankLedger(): Ledger
    {
        return self::ledger(self::LEDGER_BANK);
    }

    public static function salesLedger(): Ledger
    {
        return self::ledger(self::LEDGER_SALES);
    }

    public static function vatLedger(): Ledger
    {
        return self::ledger(self::LEDGER_VAT);
    }

    private static function accountJournal(string $name): Journal
    {
        return CompanyAccount::where('name', $name)->firstOrFail()->journal;
    }

    private static function ledger(string $name): Ledger
    {
        return Ledger::where('name', $name)->firstOrFail();
    }
}
```

`app/Demos/WindowCleaner/Actions/EnsureBooksExist.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Actions;

use Academe\LaravelJournal\Enums\StandardLedgerType;
use Academe\LaravelJournal\Models\Ledger;
use App\Demos\WindowCleaner\Models\CompanyAccount;
use App\Demos\WindowCleaner\Support\Books;

/**
 * Create the ledgers and company-account journals the business posts
 * into (Level C setup). Safe to run repeatedly: existing rows are
 * reused, so the seeder, tests, and factories can all call it blindly.
 */
class EnsureBooksExist
{
    public function run(): void
    {
        Ledger::firstOrCreate(['name' => Books::LEDGER_DEBTORS], ['type' => StandardLedgerType::ASSET]);
        $bank = Ledger::firstOrCreate(['name' => Books::LEDGER_BANK], ['type' => StandardLedgerType::ASSET]);
        $sales = Ledger::firstOrCreate(['name' => Books::LEDGER_SALES], ['type' => StandardLedgerType::INCOME]);
        $vat = Ledger::firstOrCreate(['name' => Books::LEDGER_VAT], ['type' => StandardLedgerType::LIABILITY]);

        foreach ([[Books::SALES, $sales], [Books::VAT, $vat], [Books::BANK, $bank]] as [$name, $ledger]) {
            $account = CompanyAccount::firstOrCreate(['name' => $name]);

            if ($account->journal()->doesntExist()) {
                $account->initJournal('GBP')->assignToLedger($ledger);
            }
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=BooksFoundationTest`
Expected: PASS. Then `vendor/bin/pint --dirty`.

---

### Task 5: `Customer`, `Service`, `ServicePlan` models + factories

**Files:**
- Create: `database/migrations/2026_07_14_100002_create_wc_customer_tables.php`
- Create: `app/Demos/WindowCleaner/Models/Customer.php`, `.../Service.php`, `.../ServicePlan.php`
- Create: `database/factories/CustomerFactory.php`, `ServiceFactory.php`, `ServicePlanFactory.php`
- Test: `tests/Feature/WindowCleaner/CustomerModelsTest.php`

**Interfaces:**
- Consumes: `EnsureBooksExist`, `Books::debtorsLedger()` (Task 4).
- Produces: `Customer` (uses `HasJournal`, `Notifiable`; `balance(): Money`; `amountOwed(): Money` — positive money owed, zero when in credit; `servicePlans(): HasMany`); `Service` (`name`); `ServicePlan` (`customer()`, `service()`, `priceAsMoney(): Money`, `isDueOn(CarbonInterface): bool`, `rollForward(CarbonInterface $from): void`, int `price` minor units, int `interval_weeks`, Carbon `next_due_on`, bool `active`). `Customer::factory()` initialises a GBP journal in the Debtors ledger (via afterCreating).

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/CustomerModelsTest.php`:

```php
<?php

use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\ServicePlan;
use App\Demos\WindowCleaner\Support\Books;
use Illuminate\Support\Carbon;

it('gives a factory customer a GBP journal in the Debtors ledger', function () {
    $customer = Customer::factory()->create();

    expect($customer->journal)->not->toBeNull()
        ->and($customer->journal->currency_code)->toBe('GBP')
        ->and($customer->journal->ledger->name)->toBe(Books::LEDGER_DEBTORS)
        ->and($customer->balance()->isZero())->toBeTrue()
        ->and($customer->amountOwed()->isZero())->toBeTrue();
});

it('rolls a plan forward past the given date, preserving the weekday', function () {
    $plan = ServicePlan::factory()->create([
        'interval_weeks' => 2,
        'next_due_on' => '2026-07-06', // a Monday
    ]);

    $plan->rollForward(Carbon::parse('2026-07-06'));
    expect($plan->fresh()->next_due_on->toDateString())->toBe('2026-07-20');

    $plan->rollForward(Carbon::parse('2026-08-25')); // long gap: must land beyond it
    expect($plan->fresh()->next_due_on->toDateString())->toBe('2026-08-31')
        ->and($plan->fresh()->next_due_on->isMonday())->toBeTrue();
});

it('knows when it is due', function () {
    $plan = ServicePlan::factory()->create(['next_due_on' => '2026-07-14', 'active' => true]);

    expect($plan->isDueOn(Carbon::parse('2026-07-14')))->toBeTrue()
        ->and($plan->isDueOn(Carbon::parse('2026-07-13')))->toBeFalse();

    $plan->update(['active' => false]);
    expect($plan->fresh()->isDueOn(Carbon::parse('2026-07-14')))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CustomerModelsTest`
Expected: FAIL — class not found

- [ ] **Step 3: Implement**

`database/migrations/2026_07_14_100002_create_wc_customer_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wc_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address');
            $table->string('phone');
            $table->timestamps();
        });

        Schema::create('wc_services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // A customer's subscription to one service: their own price
        // (VAT-inclusive minor units), their own cadence, their own day.
        Schema::create('wc_service_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wc_customers');
            $table->foreignId('service_id')->constrained('wc_services');
            $table->unsignedInteger('price');
            $table->unsignedTinyInteger('interval_weeks');
            $table->date('next_due_on');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wc_service_plans');
        Schema::dropIfExists('wc_services');
        Schema::dropIfExists('wc_customers');
    }
};
```

`app/Demos/WindowCleaner/Models/Customer.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Models;

use Academe\LaravelJournal\Concerns\HasJournal;
use App\Demos\WindowCleaner\Support\Gbp;
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
class Customer extends Model
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

        return $balance->isNegative() ? $balance->absolute() : Gbp::money(0);
    }
}
```

`app/Demos/WindowCleaner/Models/Service.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $table = 'wc_services';

    protected $guarded = [];

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }
}
```

`app/Demos/WindowCleaner/Models/ServicePlan.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Models;

use App\Demos\WindowCleaner\Support\Gbp;
use Carbon\CarbonInterface;
use Database\Factories\ServicePlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Money\Money;

class ServicePlan extends Model
{
    use HasFactory;

    protected $table = 'wc_service_plans';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'next_due_on' => 'date',
            'active' => 'boolean',
        ];
    }

    protected static function newFactory(): ServicePlanFactory
    {
        return ServicePlanFactory::new();
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

    public function isDueOn(CarbonInterface $date): bool
    {
        return $this->active && $this->next_due_on->lte($date);
    }

    /**
     * Advance next_due_on in interval_weeks steps until it is beyond
     * $from. Stepping in whole weeks keeps the visit on its weekday.
     */
    public function rollForward(CarbonInterface $from): void
    {
        do {
            $this->next_due_on = $this->next_due_on->addWeeks($this->interval_weeks);
        } while ($this->next_due_on->lte($from));

        $this->save();
    }
}
```

`database/factories/CustomerFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Demos\WindowCleaner\Actions\EnsureBooksExist;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Support\Books;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'address' => fake()->streetAddress(),
            'phone' => '07700 900'.fake()->unique()->numberBetween(100, 999),
        ];
    }

    public function configure(): static
    {
        // Every customer needs a journal in the Debtors ledger; the books
        // must exist first. EnsureBooksExist is idempotent, so tests can
        // just use the factory with no extra setup.
        return $this->afterCreating(function (Customer $customer) {
            app(EnsureBooksExist::class)->run();
            $customer->initJournal('GBP')->assignToLedger(Books::debtorsLedger());
        });
    }
}
```

`database/factories/ServiceFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Demos\WindowCleaner\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
```

`database/factories/ServicePlanFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\ServicePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServicePlanFactory extends Factory
{
    protected $model = ServicePlan::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'service_id' => Service::factory(),
            'price' => 1500,
            'interval_weeks' => 2,
            'next_due_on' => today(),
            'active' => true,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CustomerModelsTest`
Expected: PASS (3 tests). Then `vendor/bin/pint --dirty`.

---

### Task 6: Activity models — `Visit`, `Payment`, `SmsMessage`, `Wallet`

**Files:**
- Create: `database/migrations/2026_07_14_100003_create_wc_activity_tables.php`
- Create: `app/Demos/WindowCleaner/Models/Visit.php`, `.../Payment.php`, `.../SmsMessage.php`, `.../Wallet.php`
- Test: `tests/Feature/WindowCleaner/ActivityModelsTest.php`

**Interfaces:**
- Produces: `Visit` (uses `HasJournalTransactions`; `customer()`, `service()`, int `price`, Carbon `visited_on`, nullable `service_plan_id`, `priceAsMoney(): Money`); `Payment` (uses `HasJournalTransactions`; `customer()`, int `amount`, string `method`, Carbon `paid_at`, `amountAsMoney(): Money`); `SmsMessage` (`customer_id`, `phone`, `body`, Carbon `sent_at`, `customer()`); `Wallet` (uses `HasJournal`; unique `name`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/ActivityModelsTest.php`:

```php
<?php

use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\Visit;
use App\Demos\WindowCleaner\Models\Wallet;

it('reaches journal entries from a referenced visit', function () {
    $customer = Customer::factory()->create();
    $service = Service::factory()->create();

    $visit = Visit::create([
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'price' => 1500,
        'visited_on' => today(),
    ]);

    expect($visit->journalTransactions)->toHaveCount(0);

    $transaction = $customer->journal->debit(1500, 'test entry');
    $transaction->reference()->associate($visit)->save();

    expect($visit->fresh()->journalTransactions)->toHaveCount(1)
        ->and($visit->priceAsMoney()->getAmount())->toBe('1500');
});

it('gives a wallet its own journal', function () {
    $wallet = Wallet::create(['name' => 'playground']);
    $wallet->initJournal('GBP');

    $wallet->journal->credit(500, 'first credit');

    expect($wallet->journal->currentBalance()->getAmount())->toBe('500');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ActivityModelsTest`
Expected: FAIL — class/table not found

- [ ] **Step 3: Implement**

`database/migrations/2026_07_14_100003_create_wc_activity_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One performed service. Journal entries that charged it point
        // back here via the package's `reference` morph.
        Schema::create('wc_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wc_customers');
            $table->foreignId('service_id')->constrained('wc_services');
            $table->foreignId('service_plan_id')->nullable()->constrained('wc_service_plans');
            $table->unsignedInteger('price');
            $table->date('visited_on');
            $table->timestamps();
        });

        Schema::create('wc_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wc_customers');
            $table->unsignedInteger('amount');
            $table->string('method', 20);
            $table->dateTime('paid_at');
            $table->timestamps();
        });

        // The fake SMS outbox (a stand-in for a real SMS provider).
        Schema::create('wc_sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wc_customers');
            $table->string('phone');
            $table->text('body');
            $table->dateTime('sent_at');
            $table->timestamps();
        });

        // Scratch owner model for the Tour's Playground page: Level A in
        // isolation, deliberately outside the business's books.
        Schema::create('wc_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wc_wallets');
        Schema::dropIfExists('wc_sms_messages');
        Schema::dropIfExists('wc_payments');
        Schema::dropIfExists('wc_visits');
    }
};
```

`app/Demos/WindowCleaner/Models/Visit.php`:

```php
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
```

`app/Demos/WindowCleaner/Models/Payment.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Models;

use Academe\LaravelJournal\Concerns\HasJournalTransactions;
use App\Demos\WindowCleaner\Support\Gbp;
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
        return Gbp::money($this->amount);
    }
}
```

`app/Demos/WindowCleaner/Models/SmsMessage.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    protected $table = 'wc_sms_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
```

`app/Demos/WindowCleaner/Models/Wallet.php`:

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ActivityModelsTest`
Expected: PASS (2 tests). Then `vendor/bin/pint --dirty`.

---

### Task 7: `ChargeVisit` action (Level B posting, VAT split, references, tags)

**Files:**
- Create: `app/Demos/WindowCleaner/Actions/ChargeVisit.php`
- Create: `app/Demos/WindowCleaner/Support/TagsTransactionGroups.php`
- Test: `tests/Feature/WindowCleaner/ChargeVisitTest.php`

**Interfaces:**
- Consumes: `Gbp::vatSplit()`, `Books::salesJournal()/vatJournal()`, models from Tasks 5-6.
- Produces: `ChargeVisit::run(Customer $customer, Service $service, Money $grossPrice, ?ServicePlan $plan = null, ?CarbonInterface $date = null): Visit`; trait `TagsTransactionGroups` with `protected function tagGroup(string $groupUuid, array $tags): void`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/ChargeVisitTest.php`:

```php
<?php

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Support\Books;
use Money\Money;

beforeEach(function () {
    $this->customer = Customer::factory()->create();
    $this->service = Service::factory()->create(['name' => 'Full house']);
});

it('charges a visit as one balanced three-leg group with the VAT split out', function () {
    $visit = app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500));

    // Level A: the customer's running balance shows the debt.
    expect($this->customer->journal->currentBalance()->equals(Money::GBP(-1500)))->toBeTrue();

    // Level B: three legs, one shared group UUID, reachable from the visit.
    $legs = $visit->journalTransactions;
    expect($legs)->toHaveCount(3)
        ->and($legs->pluck('transaction_group')->unique())->toHaveCount(1)
        ->and($legs->first()->tags)->toBe(['kind' => 'visit', 'service' => 'full-house']);

    // The business side: net to Sales, VAT to VAT owed.
    expect(Books::salesJournal()->currentBalance()->equals(Money::GBP(1250)))->toBeTrue()
        ->and(Books::vatJournal()->currentBalance()->equals(Money::GBP(250)))->toBeTrue();
});

it('keeps the accounting equation balanced, even on awkward pennies', function () {
    app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1499));

    $assets = Books::debtorsLedger()->currentBalance('GBP')
        ->add(Books::bankLedger()->currentBalance('GBP'));
    $liabilitiesPlusIncome = Books::vatLedger()->currentBalance('GBP')
        ->add(Books::salesLedger()->currentBalance('GBP'));

    expect($assets->equals($liabilitiesPlusIncome))->toBeTrue()
        ->and(Books::debtorsLedger()->currentBalance('GBP')->equals(Money::GBP(1499)))->toBeTrue()
        ->and(Books::salesLedger()->currentBalance('GBP')->equals(Money::GBP(1250)))->toBeTrue()
        ->and(Books::vatLedger()->currentBalance('GBP')->equals(Money::GBP(249)))->toBeTrue();
});

it('records the visit with a historical post date when given one', function () {
    $date = now()->subMonths(2)->startOfDay();

    $visit = app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500), null, $date);

    expect($visit->visited_on->toDateString())->toBe($date->toDateString())
        ->and($visit->journalTransactions->first()->post_date->toDateString())->toBe($date->toDateString())
        ->and($this->customer->journal->balanceOn($date)->equals(Money::GBP(-1500)))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChargeVisitTest`
Expected: FAIL — `ChargeVisit` not found

- [ ] **Step 3: Implement**

`app/Demos/WindowCleaner/Support/TagsTransactionGroups.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Support;

use Academe\LaravelJournal\Models\JournalTransaction;

/**
 * Tag every entry in a committed transaction group.
 *
 * TransactionGroup::commit() returns the shared group UUID; tags are a
 * per-entry attribute, so they're applied to the entries afterwards.
 */
trait TagsTransactionGroups
{
    /**
     * @param  array<string, bool|int|float|string>  $tags
     */
    protected function tagGroup(string $groupUuid, array $tags): void
    {
        JournalTransaction::where('transaction_group', $groupUuid)
            ->get()
            ->each(function (JournalTransaction $transaction) use ($tags) {
                $transaction->tags = $tags;
                $transaction->save();
            });
    }
}
```

`app/Demos/WindowCleaner/Actions/ChargeVisit.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Actions;

use Academe\LaravelJournal\TransactionGroup;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\ServicePlan;
use App\Demos\WindowCleaner\Models\Visit;
use App\Demos\WindowCleaner\Support\Books;
use App\Demos\WindowCleaner\Support\Gbp;
use App\Demos\WindowCleaner\Support\TagsTransactionGroups;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Money\Money;

/**
 * Charge a customer for one visit — the demo's Level B centrepiece.
 *
 * The VAT-inclusive price is split into net + VAT (Money::allocate, so
 * no penny is lost), then posted as ONE balanced TransactionGroup:
 *
 *   debit  customer journal   £15.00   (Debtors: the customer owes more)
 *   credit Sales journal      £12.50   (income earned)
 *   credit VAT journal         £2.50   (owed to HMRC)
 *
 * Debits equal credits, so commit() writes all three atomically. Every
 * leg references the Visit row (the package's `reference` morph) and is
 * tagged for filtering in statements.
 *
 * Scheduling is NOT this class's job: ad-hoc visits call it directly;
 * scheduled runs go through RunDueVisits, which also rolls the plan.
 */
class ChargeVisit
{
    use TagsTransactionGroups;

    public function run(
        Customer $customer,
        Service $service,
        Money $grossPrice,
        ?ServicePlan $plan = null,
        ?CarbonInterface $date = null,
    ): Visit {
        $date ??= now();

        ['net' => $net, 'vat' => $vat] = Gbp::vatSplit($grossPrice);

        $visit = Visit::create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'service_plan_id' => $plan?->id,
            'price' => (int) $grossPrice->getAmount(),
            'visited_on' => $date->toDateString(),
        ]);

        $memo = "{$service->name} on {$date->toDateString()}";

        $groupUuid = TransactionGroup::make()
            ->addTransaction($customer->journal, 'debit', $grossPrice, $memo, $visit, $date)
            ->addTransaction(Books::salesJournal(), 'credit', $net, $memo, $visit, $date)
            ->addTransaction(Books::vatJournal(), 'credit', $vat, "VAT on {$memo}", $visit, $date)
            ->commit();

        $this->tagGroup($groupUuid, [
            'kind' => 'visit',
            'service' => Str::slug($service->name),
        ]);

        return $visit;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ChargeVisitTest`
Expected: PASS (3 tests). Then `vendor/bin/pint --dirty`.

---

### Task 8: `RecordPayment` action

**Files:**
- Create: `app/Demos/WindowCleaner/Actions/RecordPayment.php`
- Test: `tests/Feature/WindowCleaner/RecordPaymentTest.php`

**Interfaces:**
- Consumes: `Books::bankJournal()`, `TagsTransactionGroups`, `Payment` model.
- Produces: `RecordPayment::run(Customer $customer, Money $amount, ?CarbonInterface $date = null, string $method = 'online'): Payment`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/RecordPaymentTest.php`:

```php
<?php

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Actions\RecordPayment;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Support\Books;
use Money\Money;

beforeEach(function () {
    $this->customer = Customer::factory()->create();
});

it('credits the customer and debits the bank in one group', function () {
    $payment = app(RecordPayment::class)->run($this->customer, Money::GBP(1000));

    expect($this->customer->journal->currentBalance()->equals(Money::GBP(1000)))->toBeTrue()
        ->and(Books::bankJournal()->currentBalance()->equals(Money::GBP(1000)))->toBeTrue()
        ->and($payment->journalTransactions)->toHaveCount(2)
        ->and($payment->journalTransactions->first()->tags)
            ->toBe(['kind' => 'payment', 'channel' => 'online'])
        ->and($payment->method)->toBe('online');
});

it('lets a customer overpay straight through zero', function () {
    $service = Service::factory()->create();
    app(ChargeVisit::class)->run($this->customer, $service, Money::GBP(1500));

    app(RecordPayment::class)->run($this->customer, Money::GBP(2000));

    // Owed £15, paid £20: now £5 in credit. No special handling anywhere.
    expect($this->customer->journal->currentBalance()->equals(Money::GBP(500)))->toBeTrue()
        ->and($this->customer->amountOwed()->isZero())->toBeTrue();
});

it('records underpayment just as happily', function () {
    $service = Service::factory()->create();
    app(ChargeVisit::class)->run($this->customer, $service, Money::GBP(1500));

    app(RecordPayment::class)->run($this->customer, Money::GBP(500), null, 'manual');

    expect($this->customer->journal->currentBalance()->equals(Money::GBP(-1000)))->toBeTrue()
        ->and($this->customer->amountOwed()->equals(Money::GBP(1000)))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RecordPaymentTest`
Expected: FAIL — `RecordPayment` not found

- [ ] **Step 3: Implement**

`app/Demos/WindowCleaner/Actions/RecordPayment.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Actions;

use Academe\LaravelJournal\TransactionGroup;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Payment;
use App\Demos\WindowCleaner\Support\Books;
use App\Demos\WindowCleaner\Support\TagsTransactionGroups;
use Carbon\CarbonInterface;
use Money\Money;

/**
 * Record a customer payment: credit their journal, debit the Bank
 * journal — one balanced TransactionGroup (Level B).
 *
 * Over- and underpayment need no special handling anywhere in the
 * demo: the balance simply moves, through zero if that's where it
 * goes. Both legs reference the Payment row.
 */
class RecordPayment
{
    use TagsTransactionGroups;

    public function run(
        Customer $customer,
        Money $amount,
        ?CarbonInterface $date = null,
        string $method = 'online',
    ): Payment {
        $date ??= now();

        $payment = Payment::create([
            'customer_id' => $customer->id,
            'amount' => (int) $amount->getAmount(),
            'method' => $method,
            'paid_at' => $date,
        ]);

        $memo = "Payment received ({$method})";

        $groupUuid = TransactionGroup::make()
            ->addTransaction($customer->journal, 'credit', $amount, $memo, $payment, $date)
            ->addTransaction(Books::bankJournal(), 'debit', $amount, $memo, $payment, $date)
            ->commit();

        $this->tagGroup($groupUuid, [
            'kind' => 'payment',
            'channel' => $method,
        ]);

        return $payment;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RecordPaymentTest`
Expected: PASS (3 tests). Then `vendor/bin/pint --dirty`.

---

### Task 9: `RunDueVisits` action + `demo:run-visits` command

**Files:**
- Create: `app/Demos/WindowCleaner/Actions/RunDueVisits.php`
- Create: `app/Demos/WindowCleaner/Console/RunVisitsCommand.php`
- Modify: `bootstrap/app.php` (register the command)
- Test: `tests/Feature/WindowCleaner/RunDueVisitsTest.php`

**Interfaces:**
- Consumes: `ChargeVisit::run()`, `ServicePlan::isDueOn()/rollForward()/priceAsMoney()`.
- Produces: `RunDueVisits::run(?CarbonInterface $today = null): int` (number of visits charged; charges each due plan ONCE at its `next_due_on` post date, then rolls the plan beyond `$today`); artisan `demo:run-visits`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/RunDueVisitsTest.php`:

```php
<?php

use App\Demos\WindowCleaner\Actions\RunDueVisits;
use App\Demos\WindowCleaner\Models\ServicePlan;
use App\Demos\WindowCleaner\Models\Visit;
use Illuminate\Support\Carbon;

it('charges exactly the due plans and rolls their dates beyond today', function () {
    $today = Carbon::parse('2026-07-14'); // a Tuesday

    $dueToday = ServicePlan::factory()->create(['next_due_on' => '2026-07-14', 'interval_weeks' => 2, 'price' => 1500]);
    $overdue = ServicePlan::factory()->create(['next_due_on' => '2026-07-10', 'interval_weeks' => 1, 'price' => 850]);
    $notDue = ServicePlan::factory()->create(['next_due_on' => '2026-07-15', 'interval_weeks' => 2]);
    $inactive = ServicePlan::factory()->create(['next_due_on' => '2026-07-14', 'active' => false]);

    $count = app(RunDueVisits::class)->run($today);

    expect($count)->toBe(2)
        ->and(Visit::count())->toBe(2);

    // The overdue plan is charged at its scheduled date, not today.
    $overdueVisit = Visit::where('service_plan_id', $overdue->id)->sole();
    expect($overdueVisit->visited_on->toDateString())->toBe('2026-07-10');

    // Both charged plans roll beyond today; the others are untouched.
    expect($dueToday->fresh()->next_due_on->toDateString())->toBe('2026-07-28')
        ->and($overdue->fresh()->next_due_on->toDateString())->toBe('2026-07-17')
        ->and($notDue->fresh()->next_due_on->toDateString())->toBe('2026-07-15')
        ->and($inactive->fresh()->next_due_on->toDateString())->toBe('2026-07-14');

    // Charged customers now owe their plan price.
    expect($dueToday->customer->journal->currentBalance()->getAmount())->toBe('-1500');
});

it('is exposed as an artisan command', function () {
    ServicePlan::factory()->create(['next_due_on' => today()->subDay()]);

    $this->artisan('demo:run-visits')
        ->expectsOutputToContain('Charged 1 due visit(s).')
        ->assertSuccessful();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RunDueVisitsTest`
Expected: FAIL — `RunDueVisits` not found

- [ ] **Step 3: Implement**

`app/Demos/WindowCleaner/Actions/RunDueVisits.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Actions;

use App\Demos\WindowCleaner\Models\ServicePlan;
use Carbon\CarbonInterface;

/**
 * "Do the round": charge every active plan whose next_due_on has
 * arrived, then roll the plan to its next occurrence.
 *
 * Each due plan is charged ONCE, posted at its scheduled next_due_on
 * (so a plan run late is still recorded on the day the schedule says
 * the visit happened), then rolled beyond $today. A plan overdue by
 * several intervals yields one visit, not several — the missed weeks
 * were simply not worked.
 *
 * The seeder replays history by calling this day by day with historical
 * dates; the admin button and demo:run-visits call it with today.
 */
class RunDueVisits
{
    public function __construct(protected ChargeVisit $chargeVisit) {}

    public function run(?CarbonInterface $today = null): int
    {
        $today ??= today();

        $due = ServicePlan::query()
            ->where('active', true)
            ->whereDate('next_due_on', '<=', $today)
            ->with(['customer', 'service'])
            ->orderBy('next_due_on')
            ->get();

        foreach ($due as $plan) {
            $this->chargeVisit->run(
                $plan->customer,
                $plan->service,
                $plan->priceAsMoney(),
                $plan,
                $plan->next_due_on,
            );

            $plan->rollForward($today);
        }

        return $due->count();
    }
}
```

`app/Demos/WindowCleaner/Console/RunVisitsCommand.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Console;

use App\Demos\WindowCleaner\Actions\RunDueVisits;
use Illuminate\Console\Command;

class RunVisitsCommand extends Command
{
    protected $signature = 'demo:run-visits';

    protected $description = 'Charge every window-cleaning plan due on or before today';

    public function handle(RunDueVisits $runDueVisits): int
    {
        $count = $runDueVisits->run();

        $this->info("Charged {$count} due visit(s).");

        return self::SUCCESS;
    }
}
```

`bootstrap/app.php` — add the command registration to the existing builder chain (keep everything already there):

```php
->withCommands([
    App\Demos\WindowCleaner\Console\RunVisitsCommand::class,
])
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RunDueVisitsTest`
Expected: PASS (2 tests). Then `vendor/bin/pint --dirty`.

---

### Task 10: `CloseMonth` action (checkpoints)

**Files:**
- Create: `app/Demos/WindowCleaner/Actions/CloseMonth.php`
- Test: `tests/Feature/WindowCleaner/CloseMonthTest.php`

**Interfaces:**
- Consumes: `Journal::checkpoint()/latestCheckpoint()` (package).
- Produces: `CloseMonth::run(?CarbonInterface $asOf = null): array{date: CarbonInterface, closed: int, skipped: int}` — checkpoints every journal through the end of the month before `$asOf` (default now), skipping journals already checkpointed at or beyond that date.

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/CloseMonthTest.php`:

```php
<?php

use Academe\LaravelJournal\Exceptions\PeriodClosed;
use Academe\LaravelJournal\Exceptions\TransactionCouldNotBeProcessed;
use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Actions\CloseMonth;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use Illuminate\Support\Carbon;
use Money\Money;

beforeEach(function () {
    Carbon::setTestNow('2026-07-14 10:00:00');
    $this->customer = Customer::factory()->create();
    $this->service = Service::factory()->create();
});

it('checkpoints every journal through last month end', function () {
    app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500), null, Carbon::parse('2026-06-10'));

    $result = app(CloseMonth::class)->run();

    // 4 journals exist: the customer's plus Sales, VAT, Bank.
    expect($result['closed'])->toBe(4)
        ->and($result['skipped'])->toBe(0)
        ->and($result['date']->toDateString())->toBe('2026-06-30')
        ->and($this->customer->journal->fresh()->latestCheckpoint()->checkpoint_date->toDateString())->toBe('2026-06-30');

    // Balances still read correctly across the checkpoint boundary.
    expect($this->customer->journal->currentBalance()->equals(Money::GBP(-1500)))->toBeTrue();
});

it('is safe to run twice', function () {
    app(CloseMonth::class)->run();
    $second = app(CloseMonth::class)->run();

    expect($second['closed'])->toBe(0)
        ->and($second['skipped'])->toBe(4);
});

it('blocks back-dated postings into the closed period', function () {
    app(CloseMonth::class)->run(); // closes through 2026-06-30

    // TransactionGroup::commit() wraps the PeriodClosed in
    // TransactionCouldNotBeProcessed; the original is chained.
    try {
        app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500), null, Carbon::parse('2026-06-20'));
        $this->fail('Expected TransactionCouldNotBeProcessed');
    } catch (TransactionCouldNotBeProcessed $e) {
        expect($e->getPrevious())->toBeInstanceOf(PeriodClosed::class);
    }

    // Nothing was written: the whole group rolled back.
    expect($this->customer->journal->currentBalance()->isZero())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CloseMonthTest`
Expected: FAIL — `CloseMonth` not found

- [ ] **Step 3: Implement**

`app/Demos/WindowCleaner/Actions/CloseMonth.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Actions;

use Academe\LaravelJournal\Models\Journal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Close the previous month: checkpoint every journal through its last
 * day. A checkpoint freezes the period behind it (posting, editing, or
 * deleting entries dated on or before it throws PeriodClosed) and
 * stores cumulative totals so balance queries scan only newer entries.
 *
 * Journals already checkpointed at or beyond the target date are
 * skipped, so clicking "Close month" twice is harmless.
 */
class CloseMonth
{
    /**
     * @return array{date: CarbonInterface, closed: int, skipped: int}
     */
    public function run(?CarbonInterface $asOf = null): array
    {
        $date = Carbon::instance($asOf ?? now())
            ->subMonthNoOverflow()
            ->endOfMonth()
            ->startOfDay();

        $closed = 0;
        $skipped = 0;

        foreach (Journal::query()->get() as $journal) {
            $latest = $journal->latestCheckpoint();

            if ($latest !== null && $latest->checkpoint_date->gte($date)) {
                $skipped++;

                continue;
            }

            $journal->checkpoint($date);
            $closed++;
        }

        return ['date' => $date, 'closed' => $closed, 'skipped' => $skipped];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CloseMonthTest`
Expected: PASS (3 tests). Then `vendor/bin/pint --dirty`.

---

### Task 11: SMS — `DemoSmsChannel`, `BalanceReminder`, `SendBalanceTexts`

**Files:**
- Create: `app/Demos/WindowCleaner/Notifications/DemoSmsChannel.php`
- Create: `app/Demos/WindowCleaner/Notifications/BalanceReminder.php`
- Create: `app/Demos/WindowCleaner/Actions/SendBalanceTexts.php`
- Test: `tests/Feature/WindowCleaner/BalanceTextsTest.php`

**Interfaces:**
- Consumes: `Customer` (Notifiable, `amountOwed()`), `SmsMessage`, `Gbp::format()`.
- Produces: `SendBalanceTexts::run(): int` (texts every customer whose balance is negative; returns count); `BalanceReminder` notification with `public Money $owed` and `toDemoSms(Customer): string`; `DemoSmsChannel::send()` writes `wc_sms_messages` rows.

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/BalanceTextsTest.php`:

```php
<?php

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Actions\SendBalanceTexts;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\SmsMessage;
use Money\Money;

it('texts exactly the customers who owe money', function () {
    $service = Service::factory()->create();

    $owing = Customer::factory()->create(['name' => 'Derek Hound', 'phone' => '07700 900123']);
    app(ChargeVisit::class)->run($owing, $service, Money::GBP(2350));

    $inCredit = Customer::factory()->create();

    $sent = app(SendBalanceTexts::class)->run();

    expect($sent)->toBe(1)
        ->and(SmsMessage::count())->toBe(1);

    $message = SmsMessage::sole();
    expect($message->customer_id)->toBe($owing->id)
        ->and($message->phone)->toBe('07700 900123')
        ->and($message->body)->toContain('Derek Hound')
        ->and($message->body)->toContain('£23.50')
        ->and($message->sent_at)->not->toBeNull()
        ->and(SmsMessage::where('customer_id', $inCredit->id)->count())->toBe(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=BalanceTextsTest`
Expected: FAIL — `SendBalanceTexts` not found

- [ ] **Step 3: Implement**

`app/Demos/WindowCleaner/Notifications/DemoSmsChannel.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Notifications;

use App\Demos\WindowCleaner\Models\SmsMessage;
use Illuminate\Notifications\Notification;

/**
 * A stand-in for a real SMS provider channel (Twilio, Vonage, ...).
 *
 * This is the realistic integration shape: a custom notification
 * channel. In production you would swap this class for one that calls
 * a provider's API; here, messages land in wc_sms_messages and show up
 * on the SMS outbox page. Nothing leaves the machine.
 */
class DemoSmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        SmsMessage::create([
            'customer_id' => $notifiable->getKey(),
            'phone' => $notifiable->phone,
            'body' => $notification->toDemoSms($notifiable),
            'sent_at' => now(),
        ]);
    }
}
```

`app/Demos/WindowCleaner/Notifications/BalanceReminder.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Notifications;

use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Support\Gbp;
use Illuminate\Notifications\Notification;
use Money\Money;

class BalanceReminder extends Notification
{
    public function __construct(public Money $owed) {}

    public function via(object $notifiable): array
    {
        return [DemoSmsChannel::class];
    }

    public function toDemoSms(Customer $customer): string
    {
        $amount = Gbp::format($this->owed);

        return "Hi {$customer->name}, your Shiny & Sons window cleaning balance is "
            ."{$amount} outstanding. Pay online: ".url('/window-cleaner/portal/pay');
    }
}
```

`app/Demos/WindowCleaner/Actions/SendBalanceTexts.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Actions;

use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Notifications\BalanceReminder;

/**
 * Text a balance reminder to every customer who owes money (Level A
 * read: one currentBalance() per customer decides who gets a text).
 */
class SendBalanceTexts
{
    public function run(): int
    {
        $sent = 0;

        foreach (Customer::query()->get() as $customer) {
            $owed = $customer->amountOwed();

            if ($owed->isZero()) {
                continue;
            }

            $customer->notify(new BalanceReminder($owed));
            $sent++;
        }

        return $sent;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=BalanceTextsTest`
Expected: PASS (1 test). Then `vendor/bin/pint --dirty`.

---

### Task 12: Seeder — six months of deterministic history

**Files:**
- Create: `database/seeders/WindowCleanerSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/WindowCleaner/SeederTest.php`

**Interfaces:**
- Consumes: every action from Tasks 4-11.
- Produces: `php artisan migrate:fresh --seed` builds the full demo world: 4 services, 10 hardcoded customers with plans and personas, ~6 months of replayed history, the first history month checkpointed, one round of balance texts in the outbox. Seeding an already-seeded DB is refused with a warning (checkpoints make re-seeding on top invalid).

**Personas** (rule-based, no randomness, so seeding is fully deterministic):
- `prompt` — pays the full balance every Friday it owes anything.
- `overpayer` — standing order: pays £20 on the 1st of every month, owing or not.
- `slow` — pays half the balance (min £1) on the first Friday of each month.
- `delinquent` — never pays.

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/SeederTest.php`:

```php
<?php

use Academe\LaravelJournal\Models\JournalCheckpoint;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\SmsMessage;
use App\Demos\WindowCleaner\Models\Visit;
use App\Demos\WindowCleaner\Support\Books;
use Database\Seeders\WindowCleanerSeeder;

it('seeds six months of balanced history', function () {
    $this->seed(WindowCleanerSeeder::class);

    expect(Service::count())->toBe(4)
        ->and(Customer::count())->toBe(10)
        ->and(Visit::count())->toBeGreaterThan(50);

    // Level C: the books balance by construction.
    $assets = Books::debtorsLedger()->currentBalance('GBP')
        ->add(Books::bankLedger()->currentBalance('GBP'));
    $liabilitiesPlusIncome = Books::vatLedger()->currentBalance('GBP')
        ->add(Books::salesLedger()->currentBalance('GBP'));
    expect($assets->equals($liabilitiesPlusIncome))->toBeTrue();

    // Personas put balances on both sides of zero.
    $balances = Customer::all()->map(fn (Customer $c) => $c->balance());
    expect($balances->contains(fn ($b) => $b->isNegative()))->toBeTrue()
        ->and($balances->contains(fn ($b) => $b->isPositive()))->toBeTrue();

    // One historical month is closed, and the outbox has messages.
    expect(JournalCheckpoint::count())->toBeGreaterThan(0)
        ->and(SmsMessage::count())->toBeGreaterThan(0);
});

it('refuses to seed twice', function () {
    $this->seed(WindowCleanerSeeder::class);
    $this->seed(WindowCleanerSeeder::class); // must not throw or duplicate

    expect(Customer::count())->toBe(10);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SeederTest`
Expected: FAIL — `WindowCleanerSeeder` not found

- [ ] **Step 3: Implement**

`database/seeders/WindowCleanerSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Demos\WindowCleaner\Actions\CloseMonth;
use App\Demos\WindowCleaner\Actions\EnsureBooksExist;
use App\Demos\WindowCleaner\Actions\RecordPayment;
use App\Demos\WindowCleaner\Actions\RunDueVisits;
use App\Demos\WindowCleaner\Actions\SendBalanceTexts;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\ServicePlan;
use App\Demos\WindowCleaner\Support\Books;
use App\Demos\WindowCleaner\Support\Gbp;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Builds the demo world by REPLAYING history through the same action
 * classes the live app uses: day by day for ~6 months, RunDueVisits
 * charges whatever is due and each customer's payment persona decides
 * whether to pay. The books therefore balance by construction — the
 * seeder contains no posting logic of its own.
 *
 * Everything is hardcoded (no randomness), so seeding is deterministic.
 * Run with: php artisan migrate:fresh --seed
 */
class WindowCleanerSeeder extends Seeder
{
    private const HISTORY_MONTHS = 6;

    public function run(): void
    {
        if (Customer::query()->exists()) {
            $this->command?->warn('Window cleaner demo already seeded; use php artisan migrate:fresh --seed to rebuild.');

            return;
        }

        app(EnsureBooksExist::class)->run();

        $services = $this->createServices();
        $start = today()->subMonths(self::HISTORY_MONTHS)->startOfWeek(); // a Monday
        $customers = $this->createCustomers($services, $start);

        $this->replayHistory($customers, $start);

        // Leave the outbox populated so the SMS pages have content.
        app(SendBalanceTexts::class)->run();
    }

    /**
     * @return Collection<string, Service>
     */
    private function createServices(): Collection
    {
        return collect(['Front only', 'Full house', 'Conservatory', 'Gutter clean'])
            ->mapWithKeys(fn (string $name) => [$name => Service::create(['name' => $name])]);
    }

    /**
     * Each row: [name, address, phone, persona, plans], where each plan
     * is [service, price £ (VAT-inc), interval weeks, weekday offset from
     * the Monday the history starts on].
     *
     * @param  Collection<string, Service>  $services
     * @return Collection<int, Customer>
     */
    private function createCustomers(Collection $services, CarbonInterface $start): Collection
    {
        $spec = [
            ['Margaret Whitfield', '1 Acacia Avenue', '07700 900001', 'prompt', [['Full house', '15.00', 2, 0]]],
            ['Raj Patel', '2 Acacia Avenue', '07700 900002', 'prompt', [['Front only', '8.50', 2, 0], ['Gutter clean', '25.00', 8, 2]]],
            ['Sofia Andersson', '14 Mill Lane', '07700 900003', 'overpayer', [['Full house', '14.99', 4, 1]]],
            ['Derek Hound', '15 Mill Lane', '07700 900004', 'slow', [['Full house', '16.00', 4, 1]]],
            ['Chen Wei', '3 High Street', '07700 900005', 'prompt', [['Front only', '9.00', 1, 3]]],
            ['Amara Okafor', '4 High Street', '07700 900006', 'slow', [['Full house', '15.50', 2, 3], ['Conservatory', '12.00', 4, 3]]],
            ['Bill Sykes', '5 Canal Walk', '07700 900007', 'delinquent', [['Front only', '8.00', 2, 4]]],
            ['Freya Nilsen', '6 Canal Walk', '07700 900008', 'prompt', [['Full house', '18.00', 2, 4]]],
            ['George Trent', '7 Orchard Close', '07700 900009', 'prompt', [['Conservatory', '11.00', 4, 0], ['Gutter clean', '30.00', 8, 0]]],
            ['Priya Sharma', '8 Orchard Close', '07700 900010', 'delinquent', [['Full house', '13.50', 4, 2]]],
        ];

        return collect($spec)->map(function (array $row) use ($services, $start) {
            [$name, $address, $phone, $persona, $plans] = $row;

            $customer = Customer::create(compact('name', 'address', 'phone'));
            $customer->initJournal('GBP')->assignToLedger(Books::debtorsLedger());

            // Transient property, read only by maybePay() during replay.
            $customer->persona = $persona;

            foreach ($plans as [$serviceName, $price, $intervalWeeks, $dayOffset]) {
                ServicePlan::create([
                    'customer_id' => $customer->id,
                    'service_id' => $services[$serviceName]->id,
                    'price' => (int) Gbp::parse($price)->getAmount(),
                    'interval_weeks' => $intervalWeeks,
                    'next_due_on' => $start->copy()->addDays($dayOffset),
                ]);
            }

            return $customer;
        });
    }

    /**
     * @param  Collection<int, Customer>  $customers
     */
    private function replayHistory(Collection $customers, CarbonInterface $start): void
    {
        $runDueVisits = app(RunDueVisits::class);
        $recordPayment = app(RecordPayment::class);

        // Close the first month of history once it has fully passed, so
        // the demo starts with a real checkpoint in place (Tour page).
        $closeOn = $start->copy()->endOfMonth()->addDay()->startOfDay();

        for ($day = $start->copy()->startOfDay(); $day->lte(today()); $day = $day->copy()->addDay()) {
            $runDueVisits->run($day);

            foreach ($customers as $customer) {
                $this->maybePay($recordPayment, $customer, $day);
            }

            if ($day->isSameDay($closeOn)) {
                app(CloseMonth::class)->run($day);
            }
        }
    }

    /**
     * Apply the customer's payment persona for one day of the replay.
     * Rule-based (no randomness) so the seed is deterministic.
     */
    private function maybePay(RecordPayment $recordPayment, Customer $customer, CarbonInterface $day): void
    {
        $balance = $customer->journal->balanceOn($day);
        $owed = $balance->isNegative() ? $balance->absolute() : Gbp::money(0);

        if ($customer->persona === 'prompt' && $day->isFriday() && $owed->isPositive()) {
            $recordPayment->run($customer, $owed, $day);
        }

        if ($customer->persona === 'overpayer' && $day->day === 1) {
            $recordPayment->run($customer, Gbp::parse('20.00'), $day);
        }

        if ($customer->persona === 'slow' && $day->isFriday() && $day->day <= 7
            && $owed->greaterThanOrEqual(Gbp::parse('1.00'))) {
            $recordPayment->run($customer, $this->half($owed), $day);
        }

        // 'delinquent': never pays.
    }

    private function half(\Money\Money $amount): \Money\Money
    {
        return Gbp::money(intdiv((int) $amount->getAmount(), 2));
    }
}
```

`database/seeders/DatabaseSeeder.php` (replace contents):

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(WindowCleanerSeeder::class);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SeederTest`
Expected: PASS (2 tests). This test replays ~180 days and may take 30-90s.

- [ ] **Step 5: Seed the real database and eyeball it**

```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="use App\Demos\WindowCleaner\Models\Customer; Customer::all()->each(fn ($c) => print($c->name.': '.$c->journal->currentBalance()->getAmount().PHP_EOL));"
```

Expected: 10 customers with a mix of negative (owing), zero, and positive (in credit) minor-unit balances. Then `vendor/bin/pint --dirty`.

---

### Task 13: Admin — dashboard and customer pages

**Files:**
- Create: `app/Demos/WindowCleaner/Http/Controllers/Admin/DashboardController.php`, `.../Admin/CustomerController.php`
- Create: `app/Demos/WindowCleaner/Support/Statement.php`
- Create: `resources/views/demos/window-cleaner/admin/dashboard.blade.php`, `.../admin/customers/index.blade.php`, `.../admin/customers/show.blade.php`
- Modify: `routes/demos/window-cleaner.php`
- Test: `tests/Feature/WindowCleaner/AdminPagesTest.php`

**Interfaces:**
- Consumes: actions and models from earlier tasks.
- Produces: routes `wc.admin.dashboard`, `wc.admin.customers.index`, `wc.admin.customers.show`, `wc.admin.visits.store`, `wc.admin.payments.store`; `Statement::for(Journal $journal): Collection` — rows `{transaction: JournalTransaction, running: Money}` newest first, running balance computed oldest-to-newest.

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/AdminPagesTest.php`:

```php
<?php

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\ServicePlan;
use Money\Money;

beforeEach(function () {
    $this->customer = Customer::factory()->create(['name' => 'Margaret Whitfield']);
    $this->service = Service::factory()->create(['name' => 'Full house']);
});

it('shows the dashboard with owed total, bank balance, and due visits', function () {
    ServicePlan::factory()->create([
        'customer_id' => $this->customer->id,
        'service_id' => $this->service->id,
        'next_due_on' => today()->subDay(),
    ]);
    app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500));

    $this->get('/window-cleaner/admin')
        ->assertOk()
        ->assertSee('Margaret Whitfield')
        ->assertSee('£15.00'); // total owed
});

it('lists customers with their balances', function () {
    app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500));

    $this->get('/window-cleaner/admin/customers')
        ->assertOk()
        ->assertSee('Margaret Whitfield')
        ->assertSee('£15.00');
});

it('shows a customer statement with running balance and tags', function () {
    app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500));

    $this->get('/window-cleaner/admin/customers/'.$this->customer->id)
        ->assertOk()
        ->assertSee('Full house')
        ->assertSee('-£15.00')   // running balance after the charge
        ->assertSee('kind=visit');
});

it('records an ad-hoc visit from the admin form', function () {
    $this->post('/window-cleaner/admin/customers/'.$this->customer->id.'/visits', [
        'service_id' => $this->service->id,
        'price' => '12.50',
    ])->assertRedirect();

    expect($this->customer->journal->currentBalance()->equals(Money::GBP(-1250)))->toBeTrue();
});

it('records a manual payment from the admin form', function () {
    $this->post('/window-cleaner/admin/customers/'.$this->customer->id.'/payments', [
        'amount' => '10.00',
    ])->assertRedirect();

    expect($this->customer->journal->currentBalance()->equals(Money::GBP(1000)))->toBeTrue();
});

it('rejects a zero or negative payment', function () {
    $this->from('/window-cleaner/admin/customers/'.$this->customer->id)
        ->post('/window-cleaner/admin/customers/'.$this->customer->id.'/payments', ['amount' => '0'])
        ->assertSessionHasErrors('amount');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AdminPagesTest`
Expected: FAIL — 404s (routes missing)

- [ ] **Step 3: Implement**

`app/Demos/WindowCleaner/Support/Statement.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Support;

use Academe\LaravelJournal\Models\Journal;
use Academe\LaravelJournal\Models\JournalTransaction;
use Illuminate\Support\Collection;
use Money\Money;

/**
 * A journal's entries with a running balance, newest first.
 *
 * JournalTransaction::$amount is the signed view of an entry (credit
 * positive, debit negative), so the running balance is a simple sum.
 */
final class Statement
{
    /**
     * @return Collection<int, array{transaction: JournalTransaction, running: Money}>
     */
    public static function for(Journal $journal): Collection
    {
        $running = new Money(0, $journal->currency);

        return $journal->transactions()
            ->orderBy('post_date')
            ->orderBy('created_at')
            ->get()
            ->map(function (JournalTransaction $transaction) use (&$running) {
                $running = $running->add($transaction->amount);

                return ['transaction' => $transaction, 'running' => $running];
            })
            ->reverse()
            ->values();
    }
}
```

`app/Demos/WindowCleaner/Http/Controllers/Admin/DashboardController.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Admin;

use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\ServicePlan;
use App\Demos\WindowCleaner\Support\Books;
use App\Demos\WindowCleaner\Support\Gbp;
use Illuminate\Contracts\View\View;
use Money\Money;

class DashboardController
{
    public function show(): View
    {
        $duePlans = ServicePlan::query()
            ->where('active', true)
            ->whereDate('next_due_on', '<=', today())
            ->with(['customer', 'service'])
            ->orderBy('next_due_on')
            ->get();

        $totalOwed = Customer::query()->get()
            ->reduce(
                fn (Money $sum, Customer $customer) => $sum->add($customer->amountOwed()),
                Gbp::money(0),
            );

        return view('demos.window-cleaner.admin.dashboard', [
            'duePlans' => $duePlans,
            'totalOwed' => $totalOwed,
            'bankBalance' => Books::bankJournal()->currentBalance(),
        ]);
    }
}
```

`app/Demos/WindowCleaner/Http/Controllers/Admin/CustomerController.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Admin;

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Actions\RecordPayment;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Support\Gbp;
use App\Demos\WindowCleaner\Support\Statement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerController
{
    public function index(): View
    {
        return view('demos.window-cleaner.admin.customers.index', [
            'customers' => Customer::query()->orderBy('name')->get(),
        ]);
    }

    public function show(Customer $customer): View
    {
        return view('demos.window-cleaner.admin.customers.show', [
            'customer' => $customer,
            'plans' => $customer->servicePlans()->with('service')->get(),
            'statement' => Statement::for($customer->journal),
            'services' => Service::query()->orderBy('name')->get(),
        ]);
    }

    public function storeVisit(Request $request, Customer $customer, ChargeVisit $chargeVisit): RedirectResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'exists:wc_services,id'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000'],
        ]);

        $service = Service::findOrFail($validated['service_id']);
        $chargeVisit->run($customer, $service, Gbp::parse((string) $validated['price']));

        return redirect()
            ->route('wc.admin.customers.show', $customer)
            ->with('status', "Visit recorded: {$service->name}.");
    }

    public function storePayment(Request $request, Customer $customer, RecordPayment $recordPayment): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000'],
        ]);

        $payment = $recordPayment->run($customer, Gbp::parse((string) $validated['amount']), null, 'manual');

        return redirect()
            ->route('wc.admin.customers.show', $customer)
            ->with('status', 'Payment recorded: '.Gbp::format($payment->amountAsMoney()).'.');
    }
}
```

Add to `routes/demos/window-cleaner.php`, inside the existing group (imports at top of file):

```php
use App\Demos\WindowCleaner\Http\Controllers\Admin\CustomerController;
use App\Demos\WindowCleaner\Http\Controllers\Admin\DashboardController;

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [DashboardController::class, 'show'])->name('dashboard');
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::post('customers/{customer}/visits', [CustomerController::class, 'storeVisit'])->name('visits.store');
        Route::post('customers/{customer}/payments', [CustomerController::class, 'storePayment'])->name('payments.store');
    });
```

`resources/views/demos/window-cleaner/admin/dashboard.blade.php`:

```blade
@extends('demos.window-cleaner.layout')
@section('title', 'Admin dashboard')
@section('content')
    @php use App\Demos\WindowCleaner\Support\Gbp; @endphp
    <h1>Dashboard</h1>
    <div class="cards">
        <div><h2>Owed by customers</h2><p class="big owes">{{ Gbp::format($totalOwed) }}</p>
            <p><a href="{{ route('wc.admin.customers.index') }}">Customers</a></p></div>
        <div><h2>Bank balance</h2><p class="big">{{ Gbp::format($bankBalance) }}</p>
            <p><a href="{{ route('wc.admin.books') }}">The books</a></p></div>
        <div><h2>Visits due</h2><p class="big">{{ $duePlans->count() }}</p>
            <form method="post" action="{{ route('wc.admin.run-visits') }}">@csrf<button>Run due visits</button></form></div>
    </div>

    <h2>Due today or overdue</h2>
    <table>
        <tr><th>Due</th><th>Customer</th><th>Service</th><th class="num">Price</th><th class="num">Every</th></tr>
        @forelse ($duePlans as $plan)
            <tr>
                <td>{{ $plan->next_due_on->toFormattedDateString() }}</td>
                <td><a href="{{ route('wc.admin.customers.show', $plan->customer) }}">{{ $plan->customer->name }}</a></td>
                <td>{{ $plan->service->name }}</td>
                <td class="num">{{ Gbp::format($plan->priceAsMoney()) }}</td>
                <td class="num">{{ $plan->interval_weeks }}w</td>
            </tr>
        @empty
            <tr><td colspan="5">Nothing due — the round is up to date.</td></tr>
        @endforelse
    </table>

    <p>
        <a href="{{ route('wc.admin.close-month.show') }}">Close month</a> ·
        <a href="{{ route('wc.admin.sms.outbox') }}">SMS outbox</a>
    </p>
@endsection
```

Note: this view references `wc.admin.run-visits`, `wc.admin.books`, `wc.admin.close-month.show`, and `wc.admin.sms.outbox`, which arrive in Tasks 14-15. To keep THIS task's tests green, register placeholder routes now in `routes/demos/window-cleaner.php` (Tasks 14-15 replace them with real controllers):

```php
        // Placeholders replaced in Tasks 14-15.
        Route::post('run-visits', fn () => redirect()->route('wc.admin.dashboard'))->name('run-visits');
        Route::get('books', fn () => abort(404))->name('books');
        Route::get('close-month', fn () => abort(404))->name('close-month.show');
        Route::get('sms-outbox', fn () => abort(404))->name('sms.outbox');
```

`resources/views/demos/window-cleaner/admin/customers/index.blade.php`:

```blade
@extends('demos.window-cleaner.layout')
@section('title', 'Customers')
@section('content')
    @php use App\Demos\WindowCleaner\Support\Gbp; @endphp
    <h1>Customers</h1>
    <table>
        <tr><th>Name</th><th>Address</th><th class="num">Balance</th></tr>
        @foreach ($customers as $customer)
            @php $balance = $customer->balance(); @endphp
            <tr>
                <td><a href="{{ route('wc.admin.customers.show', $customer) }}">{{ $customer->name }}</a></td>
                <td>{{ $customer->address }}</td>
                <td class="num {{ $balance->isNegative() ? 'owes' : 'credit' }}">
                    {{ $balance->isNegative() ? Gbp::format($balance->absolute()).' owed' : Gbp::format($balance).' in credit' }}
                </td>
            </tr>
        @endforeach
    </table>
@endsection
```

`resources/views/demos/window-cleaner/admin/customers/show.blade.php`:

```blade
@extends('demos.window-cleaner.layout')
@section('title', $customer->name)
@section('content')
    @php use App\Demos\WindowCleaner\Support\Gbp; @endphp
    <h1>{{ $customer->name }}</h1>
    <p>{{ $customer->address }} · {{ $customer->phone }}</p>
    @php $balance = $customer->balance(); @endphp
    <p class="big {{ $balance->isNegative() ? 'owes' : 'credit' }}">
        {{ $balance->isNegative() ? 'Owes '.Gbp::format($balance->absolute()) : 'In credit '.Gbp::format($balance) }}
    </p>

    <h2>Services</h2>
    <table>
        <tr><th>Service</th><th class="num">Price</th><th class="num">Every</th><th>Next due</th><th>Active</th></tr>
        @foreach ($plans as $plan)
            <tr>
                <td>{{ $plan->service->name }}</td>
                <td class="num">{{ Gbp::format($plan->priceAsMoney()) }}</td>
                <td class="num">{{ $plan->interval_weeks }}w</td>
                <td>{{ $plan->next_due_on->toFormattedDateString() }}</td>
                <td>{{ $plan->active ? 'yes' : 'no' }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Record an ad-hoc visit</h2>
    <form class="stack" method="post" action="{{ route('wc.admin.visits.store', $customer) }}">
        @csrf
        <select name="service_id" required>
            @foreach ($services as $service)
                <option value="{{ $service->id }}">{{ $service->name }}</option>
            @endforeach
        </select>
        <input name="price" inputmode="decimal" placeholder="Price inc. VAT, e.g. 15.00" required>
        @error('price')<small class="owes">{{ $message }}</small>@enderror
        <button>Charge visit</button>
    </form>

    <h2>Record a manual payment</h2>
    <form class="stack" method="post" action="{{ route('wc.admin.payments.store', $customer) }}">
        @csrf
        <input name="amount" inputmode="decimal" placeholder="Amount, e.g. 10.00" required>
        @error('amount')<small class="owes">{{ $message }}</small>@enderror
        <button>Record payment</button>
    </form>

    <h2>Statement</h2>
    <table>
        <tr><th>Date</th><th>Memo</th><th>Tags</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr>
        @foreach ($statement as $row)
            <tr>
                <td>{{ $row['transaction']->post_date->toFormattedDateString() }}</td>
                <td>{{ $row['transaction']->memo }}</td>
                <td>@foreach ($row['transaction']->tags as $key => $value)<small class="tag">{{ $key }}={{ $value }}</small>@endforeach</td>
                <td class="num">{{ $row['transaction']->debit ? Gbp::format($row['transaction']->debit) : '' }}</td>
                <td class="num">{{ $row['transaction']->credit ? Gbp::format($row['transaction']->credit) : '' }}</td>
                <td class="num">{{ Gbp::format($row['running']) }}</td>
            </tr>
        @endforeach
    </table>
@endsection
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AdminPagesTest`
Expected: PASS (6 tests). Then `vendor/bin/pint --dirty`.

---

### Task 14: Admin — run visits, the Books page, close month

**Files:**
- Create: `app/Demos/WindowCleaner/Http/Controllers/Admin/RunVisitsController.php`, `.../Admin/BooksController.php`, `.../Admin/CloseMonthController.php`
- Create: `app/Demos/WindowCleaner/Support/JournalName.php`
- Create: `resources/views/demos/window-cleaner/admin/books.blade.php`, `.../admin/close-month.blade.php`
- Modify: `routes/demos/window-cleaner.php` (replace Task 13's placeholders for `run-visits`, `books`, `close-month.show`; add `close-month.store`)
- Test: `tests/Feature/WindowCleaner/AdminOperationsTest.php`

**Interfaces:**
- Consumes: `RunDueVisits`, `CloseMonth`, `Books`, `JournalTransaction`.
- Produces: routes `wc.admin.run-visits` (POST), `wc.admin.books` (GET), `wc.admin.close-month.show` (GET) / `wc.admin.close-month.store` (POST); `JournalName::of(Journal): string` (owner's `name` attribute, else `ClassBasename #id`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/AdminOperationsTest.php`:

```php
<?php

use Academe\LaravelJournal\Models\JournalCheckpoint;
use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\ServicePlan;
use App\Demos\WindowCleaner\Models\Visit;
use Money\Money;

it('runs due visits from the dashboard button', function () {
    ServicePlan::factory()->create(['next_due_on' => today()->subDay()]);

    $this->post('/window-cleaner/admin/run-visits')
        ->assertRedirect('/window-cleaner/admin');

    expect(Visit::count())->toBe(1);
});

it('shows the books with ledger balances and a balanced equation', function () {
    $customer = Customer::factory()->create();
    $service = Service::factory()->create();
    app(ChargeVisit::class)->run($customer, $service, Money::GBP(1500));

    $this->get('/window-cleaner/admin/books')
        ->assertOk()
        ->assertSee('Debtors')
        ->assertSee('VAT owed')
        ->assertSee('£12.50')  // Sales
        ->assertSee('£2.50')   // VAT
        ->assertSee('balances'); // the equation verdict line
});

it('closes the month and reports it', function () {
    Customer::factory()->create(); // ensures books + one customer journal exist

    $this->post('/window-cleaner/admin/close-month')
        ->assertRedirect('/window-cleaner/admin/close-month');

    expect(JournalCheckpoint::count())->toBe(4);

    $this->get('/window-cleaner/admin/close-month')
        ->assertOk()
        ->assertSee(now()->subMonthNoOverflow()->endOfMonth()->toFormattedDateString());
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AdminOperationsTest`
Expected: FAIL — placeholder routes 404 / no checkpoints created

- [ ] **Step 3: Implement**

`app/Demos/WindowCleaner/Support/JournalName.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Support;

use Academe\LaravelJournal\Models\Journal;

/**
 * A journal has no name of its own — its identity is its owner model.
 * Every owner in this demo (Customer, CompanyAccount, Wallet) has a
 * name attribute, so display resolves through the owner morph.
 */
final class JournalName
{
    public static function of(Journal $journal): string
    {
        $owner = $journal->owner;

        return $owner->name ?? class_basename($owner).' #'.$owner->getKey();
    }
}
```

`app/Demos/WindowCleaner/Http/Controllers/Admin/RunVisitsController.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Admin;

use App\Demos\WindowCleaner\Actions\RunDueVisits;
use Illuminate\Http\RedirectResponse;

class RunVisitsController
{
    public function store(RunDueVisits $runDueVisits): RedirectResponse
    {
        $count = $runDueVisits->run();

        return redirect()
            ->route('wc.admin.dashboard')
            ->with('status', "Charged {$count} due visit(s).");
    }
}
```

`app/Demos/WindowCleaner/Http/Controllers/Admin/BooksController.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Admin;

use Academe\LaravelJournal\Models\JournalTransaction;
use App\Demos\WindowCleaner\Support\Books;
use Illuminate\Contracts\View\View;

class BooksController
{
    public function show(): View
    {
        $debtors = Books::debtorsLedger()->currentBalance('GBP');
        $bank = Books::bankLedger()->currentBalance('GBP');
        $sales = Books::salesLedger()->currentBalance('GBP');
        $vat = Books::vatLedger()->currentBalance('GBP');

        // Recent groups, newest first: fetch a window of grouped entries
        // and bucket them by their shared group UUID.
        $recentGroups = JournalTransaction::query()
            ->whereNotNull('transaction_group')
            ->with('journal.owner')
            ->orderByDesc('post_date')
            ->orderByDesc('created_at')
            ->limit(60)
            ->get()
            ->groupBy('transaction_group')
            ->take(10);

        return view('demos.window-cleaner.admin.books', [
            'debtors' => $debtors,
            'bank' => $bank,
            'sales' => $sales,
            'vat' => $vat,
            'assets' => $debtors->add($bank),
            'liabilitiesPlusIncome' => $vat->add($sales),
            'recentGroups' => $recentGroups,
        ]);
    }
}
```

`app/Demos/WindowCleaner/Http/Controllers/Admin/CloseMonthController.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Admin;

use Academe\LaravelJournal\Models\JournalCheckpoint;
use App\Demos\WindowCleaner\Actions\CloseMonth;
use App\Demos\WindowCleaner\Support\Gbp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CloseMonthController
{
    public function show(): View
    {
        return view('demos.window-cleaner.admin.close-month', [
            'checkpoints' => JournalCheckpoint::query()
                ->with('journal.owner')
                ->orderByDesc('checkpoint_date')
                ->get()
                ->groupBy(fn (JournalCheckpoint $checkpoint) => $checkpoint->checkpoint_date->toDateString()),
            'target' => now()->subMonthNoOverflow()->endOfMonth(),
        ]);
    }

    public function store(CloseMonth $closeMonth): RedirectResponse
    {
        $result = $closeMonth->run();

        return redirect()
            ->route('wc.admin.close-month.show')
            ->with('status', sprintf(
                'Closed through %s: %d journal(s) checkpointed, %d already closed.',
                $result['date']->toFormattedDateString(),
                $result['closed'],
                $result['skipped'],
            ));
    }
}
```

In `routes/demos/window-cleaner.php`, replace the Task 13 placeholders for `run-visits`, `books`, and `close-month.show` (keep the `sms-outbox` placeholder until Task 15):

```php
use App\Demos\WindowCleaner\Http\Controllers\Admin\BooksController;
use App\Demos\WindowCleaner\Http\Controllers\Admin\CloseMonthController;
use App\Demos\WindowCleaner\Http\Controllers\Admin\RunVisitsController;

        Route::post('run-visits', [RunVisitsController::class, 'store'])->name('run-visits');
        Route::get('books', [BooksController::class, 'show'])->name('books');
        Route::get('close-month', [CloseMonthController::class, 'show'])->name('close-month.show');
        Route::post('close-month', [CloseMonthController::class, 'store'])->name('close-month.store');
```

`resources/views/demos/window-cleaner/admin/books.blade.php`:

```blade
@extends('demos.window-cleaner.layout')
@section('title', 'The books')
@section('content')
    @php use App\Demos\WindowCleaner\Support\Gbp; use App\Demos\WindowCleaner\Support\JournalName; @endphp
    <h1>The books (Level C)</h1>
    <p>Every journal belongs to a typed ledger, so the whole business reads as an
    accounting equation. Each balance below is one <code>Ledger::currentBalance('GBP')</code> call.</p>

    <table>
        <tr><th>Ledger</th><th>Type</th><th class="num">Balance</th></tr>
        <tr><td>Debtors (all customer journals)</td><td>asset</td><td class="num">{{ Gbp::format($debtors) }}</td></tr>
        <tr><td>Bank</td><td>asset</td><td class="num">{{ Gbp::format($bank) }}</td></tr>
        <tr><td>Sales</td><td>income</td><td class="num">{{ Gbp::format($sales) }}</td></tr>
        <tr><td>VAT owed</td><td>liability</td><td class="num">{{ Gbp::format($vat) }}</td></tr>
    </table>

    <p class="flash {{ $assets->equals($liabilitiesPlusIncome) ? '' : 'error' }}">
        Assets {{ Gbp::format($assets) }} = liabilities + income {{ Gbp::format($liabilitiesPlusIncome) }}
        — the equation {{ $assets->equals($liabilitiesPlusIncome) ? 'balances' : 'DOES NOT balance' }}.
    </p>

    <h2>Recent journal entries (grouped)</h2>
    @foreach ($recentGroups as $uuid => $entries)
        <table>
            <tr><th colspan="3">{{ $entries->first()->post_date->toFormattedDateString() }} — {{ $entries->first()->memo }}</th></tr>
            @foreach ($entries as $entry)
                <tr>
                    <td>{{ JournalName::of($entry->journal) }}</td>
                    <td class="num">{{ $entry->debit ? 'Dr '.Gbp::format($entry->debit) : '' }}</td>
                    <td class="num">{{ $entry->credit ? 'Cr '.Gbp::format($entry->credit) : '' }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach
@endsection
```

`resources/views/demos/window-cleaner/admin/close-month.blade.php`:

```blade
@extends('demos.window-cleaner.layout')
@section('title', 'Close month')
@section('content')
    <h1>Close month (checkpoints)</h1>
    <p>Closing a month checkpoints every journal through {{ $target->toFormattedDateString() }}:
    cumulative totals are stored (so balance queries scan only newer entries) and the
    period behind the checkpoint is locked — back-dated postings throw
    <code>PeriodClosed</code>. See the <a href="/window-cleaner/tour/checkpoints">Checkpoints tour page</a>.</p>

    <form method="post" action="{{ route('wc.admin.close-month.store') }}">
        @csrf
        <button>Close through {{ $target->toFormattedDateString() }}</button>
    </form>

    <h2>Existing checkpoints</h2>
    @forelse ($checkpoints as $date => $group)
        <p><strong>{{ $date }}</strong> — {{ $group->count() }} journal(s) checkpointed.</p>
    @empty
        <p>No checkpoints yet.</p>
    @endforelse
@endsection
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AdminOperationsTest`
Expected: PASS (3 tests). Also re-run Task 13's tests (`--filter=AdminPagesTest`) to confirm the dashboard links still resolve. Then `vendor/bin/pint --dirty`.

---

### Task 15: SMS pages — send balance texts + outbox

**Files:**
- Create: `app/Demos/WindowCleaner/Http/Controllers/Admin/SmsController.php`
- Create: `resources/views/demos/window-cleaner/admin/sms-outbox.blade.php`
- Modify: `routes/demos/window-cleaner.php` (replace the `sms-outbox` placeholder; add `sms.send`)
- Test: `tests/Feature/WindowCleaner/SmsPagesTest.php`

**Interfaces:**
- Consumes: `SendBalanceTexts`, `SmsMessage`.
- Produces: routes `wc.admin.sms.send` (POST), `wc.admin.sms.outbox` (GET).

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/SmsPagesTest.php`:

```php
<?php

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\SmsMessage;
use Money\Money;

it('sends balance texts from the admin button and shows them in the outbox', function () {
    $customer = Customer::factory()->create(['name' => 'Bill Sykes']);
    $service = Service::factory()->create();
    app(ChargeVisit::class)->run($customer, $service, Money::GBP(800));

    $this->post('/window-cleaner/admin/send-balance-texts')
        ->assertRedirect('/window-cleaner/admin/sms-outbox');

    expect(SmsMessage::count())->toBe(1);

    $this->get('/window-cleaner/admin/sms-outbox')
        ->assertOk()
        ->assertSee('Bill Sykes')
        ->assertSee('£8.00');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SmsPagesTest`
Expected: FAIL — POST route missing / outbox placeholder 404s

- [ ] **Step 3: Implement**

`app/Demos/WindowCleaner/Http/Controllers/Admin/SmsController.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Admin;

use App\Demos\WindowCleaner\Actions\SendBalanceTexts;
use App\Demos\WindowCleaner\Models\SmsMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SmsController
{
    public function send(SendBalanceTexts $sendBalanceTexts): RedirectResponse
    {
        $count = $sendBalanceTexts->run();

        return redirect()
            ->route('wc.admin.sms.outbox')
            ->with('status', "Sent {$count} balance text(s).");
    }

    public function outbox(): View
    {
        return view('demos.window-cleaner.admin.sms-outbox', [
            'messages' => SmsMessage::query()
                ->with('customer')
                ->orderByDesc('sent_at')
                ->limit(50)
                ->get(),
        ]);
    }
}
```

In `routes/demos/window-cleaner.php` (admin group), replace the outbox placeholder:

```php
use App\Demos\WindowCleaner\Http\Controllers\Admin\SmsController;

        Route::post('send-balance-texts', [SmsController::class, 'send'])->name('sms.send');
        Route::get('sms-outbox', [SmsController::class, 'outbox'])->name('sms.outbox');
```

`resources/views/demos/window-cleaner/admin/sms-outbox.blade.php`:

```blade
@extends('demos.window-cleaner.layout')
@section('title', 'SMS outbox')
@section('content')
    <h1>SMS outbox</h1>
    <p>Messages "sent" through the demo SMS channel. In production the channel class
    would call a real provider (Twilio, Vonage, ...); here they land in a table.</p>

    <form method="post" action="{{ route('wc.admin.sms.send') }}">
        @csrf
        <button>Text balances to everyone who owes</button>
    </form>

    <div class="sms">
        @forelse ($messages as $message)
            <div class="msg">
                <div class="meta">{{ $message->customer->name }} · {{ $message->phone }} · {{ $message->sent_at->diffForHumans() }}</div>
                {{ $message->body }}
            </div>
        @empty
            <p>No messages yet.</p>
        @endforelse
    </div>
@endsection
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SmsPagesTest`
Expected: PASS (1 test). Then `vendor/bin/pint --dirty`.

---

### Task 16: Customer portal — switcher, account, pay online

**Files:**
- Create: `app/Demos/WindowCleaner/Support/CurrentCustomer.php`
- Create: `app/Demos/WindowCleaner/Http/Controllers/Portal/SwitchController.php`, `.../Portal/AccountController.php`, `.../Portal/PaymentController.php`
- Create: `resources/views/demos/window-cleaner/portal/switch.blade.php`, `.../portal/account.blade.php`, `.../portal/pay.blade.php`, `.../portal/paid.blade.php`
- Modify: `routes/demos/window-cleaner.php`
- Test: `tests/Feature/WindowCleaner/PortalTest.php`

**Interfaces:**
- Consumes: `RecordPayment`, `Statement`, `Gbp`, `Customer`.
- Produces: routes `wc.portal.switch` (GET), `wc.portal.switch.store` (POST act-as/{customer}), `wc.portal.account`, `wc.portal.pay` (GET+POST), `wc.portal.paid` ({payment}); `CurrentCustomer::get(): ?Customer` / `CurrentCustomer::set(Customer): void` (session key `wc_customer_id`). Portal pages redirect to `wc.portal.switch` when no customer is selected.

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/PortalTest.php`:

```php
<?php

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Payment;
use App\Demos\WindowCleaner\Models\Service;
use Money\Money;

beforeEach(function () {
    $this->customer = Customer::factory()->create(['name' => 'Margaret Whitfield']);
});

it('asks you to pick a customer when none is selected', function () {
    $this->get('/window-cleaner/portal/account')
        ->assertRedirect('/window-cleaner/portal');

    $this->get('/window-cleaner/portal')
        ->assertOk()
        ->assertSee('Margaret Whitfield');
});

it('acts as the chosen customer', function () {
    $this->post('/window-cleaner/portal/act-as/'.$this->customer->id)
        ->assertRedirect('/window-cleaner/portal/account');

    $this->get('/window-cleaner/portal/account')
        ->assertOk()
        ->assertSee('Margaret Whitfield');
});

it('shows the balance owed on the account page', function () {
    $service = Service::factory()->create();
    app(ChargeVisit::class)->run($this->customer, $service, Money::GBP(2300));

    $this->post('/window-cleaner/portal/act-as/'.$this->customer->id);

    $this->get('/window-cleaner/portal/account')
        ->assertOk()
        ->assertSee('You owe')
        ->assertSee('£23.00');
});

it('takes an online payment, prefilled but editable (overpay)', function () {
    $service = Service::factory()->create();
    app(ChargeVisit::class)->run($this->customer, $service, Money::GBP(1500));
    $this->post('/window-cleaner/portal/act-as/'.$this->customer->id);

    $this->get('/window-cleaner/portal/pay')
        ->assertOk()
        ->assertSee('value="15.00"', false); // prefilled with amount owed

    $this->post('/window-cleaner/portal/pay', ['amount' => '20.00'])
        ->assertRedirect(); // to wc.portal.paid for the new payment

    expect($this->customer->journal->currentBalance()->equals(Money::GBP(500)))->toBeTrue();

    $payment = Payment::latest('id')->first();
    $this->get('/window-cleaner/portal/paid/'.$payment->id)
        ->assertOk()
        ->assertSee('£20.00')
        ->assertSee('in credit');
});

it('rejects invalid amounts', function () {
    $this->post('/window-cleaner/portal/act-as/'.$this->customer->id);

    $this->from('/window-cleaner/portal/pay')
        ->post('/window-cleaner/portal/pay', ['amount' => '0'])
        ->assertSessionHasErrors('amount');

    $this->from('/window-cleaner/portal/pay')
        ->post('/window-cleaner/portal/pay', ['amount' => '-5'])
        ->assertSessionHasErrors('amount');

    $this->from('/window-cleaner/portal/pay')
        ->post('/window-cleaner/portal/pay', ['amount' => '1001'])
        ->assertSessionHasErrors('amount');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PortalTest`
Expected: FAIL — routes missing

- [ ] **Step 3: Implement**

`app/Demos/WindowCleaner/Support/CurrentCustomer.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Support;

use App\Demos\WindowCleaner\Models\Customer;

/**
 * The emulated "login": which customer the portal is acting as, held
 * in the session. Real authentication would be pure noise in a demo
 * about journals — this is the entire mechanism.
 */
final class CurrentCustomer
{
    private const KEY = 'wc_customer_id';

    public static function get(): ?Customer
    {
        $id = session(self::KEY);

        return $id === null ? null : Customer::find($id);
    }

    public static function set(Customer $customer): void
    {
        session([self::KEY => $customer->id]);
    }
}
```

`app/Demos/WindowCleaner/Http/Controllers/Portal/SwitchController.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Portal;

use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Support\CurrentCustomer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SwitchController
{
    public function index(): View
    {
        return view('demos.window-cleaner.portal.switch', [
            'customers' => Customer::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Customer $customer): RedirectResponse
    {
        CurrentCustomer::set($customer);

        return redirect()->route('wc.portal.account');
    }
}
```

`app/Demos/WindowCleaner/Http/Controllers/Portal/AccountController.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Portal;

use App\Demos\WindowCleaner\Support\CurrentCustomer;
use App\Demos\WindowCleaner\Support\Statement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AccountController
{
    public function show(): View|RedirectResponse
    {
        $customer = CurrentCustomer::get();

        if ($customer === null) {
            return redirect()->route('wc.portal.switch');
        }

        return view('demos.window-cleaner.portal.account', [
            'customer' => $customer,
            'plans' => $customer->servicePlans()->where('active', true)->with('service')->get(),
            'statement' => Statement::for($customer->journal),
        ]);
    }
}
```

`app/Demos/WindowCleaner/Http/Controllers/Portal/PaymentController.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Portal;

use App\Demos\WindowCleaner\Actions\RecordPayment;
use App\Demos\WindowCleaner\Models\Payment;
use App\Demos\WindowCleaner\Support\CurrentCustomer;
use App\Demos\WindowCleaner\Support\Gbp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\DecimalMoneyFormatter;

class PaymentController
{
    public function create(): View|RedirectResponse
    {
        $customer = CurrentCustomer::get();

        if ($customer === null) {
            return redirect()->route('wc.portal.switch');
        }

        $owed = $customer->amountOwed();

        return view('demos.window-cleaner.portal.pay', [
            'customer' => $customer,
            'owed' => $owed,
            // Prefill as a plain decimal ("15.00") for the input value.
            'suggested' => (new DecimalMoneyFormatter(new ISOCurrencies))->format($owed),
        ]);
    }

    public function store(Request $request, RecordPayment $recordPayment): RedirectResponse
    {
        $customer = CurrentCustomer::get();

        if ($customer === null) {
            return redirect()->route('wc.portal.switch');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000'],
        ]);

        $payment = $recordPayment->run($customer, Gbp::parse((string) $validated['amount']));

        return redirect()->route('wc.portal.paid', $payment);
    }

    public function show(Payment $payment): View
    {
        return view('demos.window-cleaner.portal.paid', [
            'payment' => $payment,
            'customer' => $payment->customer,
        ]);
    }
}
```

Add to `routes/demos/window-cleaner.php`, inside the `window-cleaner` group:

```php
use App\Demos\WindowCleaner\Http\Controllers\Portal\AccountController;
use App\Demos\WindowCleaner\Http\Controllers\Portal\PaymentController;
use App\Demos\WindowCleaner\Http\Controllers\Portal\SwitchController;

    Route::prefix('portal')->name('portal.')->group(function () {
        Route::get('/', [SwitchController::class, 'index'])->name('switch');
        Route::post('act-as/{customer}', [SwitchController::class, 'store'])->name('switch.store');
        Route::get('account', [AccountController::class, 'show'])->name('account');
        Route::get('pay', [PaymentController::class, 'create'])->name('pay');
        Route::post('pay', [PaymentController::class, 'store'])->name('pay.store');
        Route::get('paid/{payment}', [PaymentController::class, 'show'])->name('paid');
    });
```

`resources/views/demos/window-cleaner/portal/switch.blade.php`:

```blade
@extends('demos.window-cleaner.layout')
@section('title', 'Choose a customer')
@section('content')
    <h1>Customer portal — emulated login</h1>
    <p>There's no real authentication in this demo. Pick a customer to see their
    portal exactly as they would after logging in.</p>
    <table>
        @foreach ($customers as $customer)
            <tr>
                <td>{{ $customer->name }}</td>
                <td>{{ $customer->address }}</td>
                <td><form method="post" action="{{ route('wc.portal.switch.store', $customer) }}">@csrf<button class="plain">Act as</button></form></td>
            </tr>
        @endforeach
    </table>
@endsection
```

`resources/views/demos/window-cleaner/portal/account.blade.php`:

```blade
@extends('demos.window-cleaner.layout')
@section('title', 'My account')
@section('content')
    @php use App\Demos\WindowCleaner\Support\Gbp; @endphp
    <h1>My account</h1>
    <p>Acting as <strong>{{ $customer->name }}</strong> — <a href="{{ route('wc.portal.switch') }}">switch</a></p>

    @php $balance = $customer->balance(); @endphp
    @if ($balance->isNegative())
        <p class="big owes">You owe {{ Gbp::format($balance->absolute()) }}</p>
        <p><a href="{{ route('wc.portal.pay') }}"><strong>Pay online</strong></a></p>
    @elseif ($balance->isPositive())
        <p class="big credit">You are {{ Gbp::format($balance) }} in credit</p>
    @else
        <p class="big">Your balance is settled — nothing to pay.</p>
    @endif

    <h2>Your services</h2>
    <table>
        <tr><th>Service</th><th class="num">Price</th><th>Every</th><th>Next visit</th></tr>
        @foreach ($plans as $plan)
            <tr>
                <td>{{ $plan->service->name }}</td>
                <td class="num">{{ Gbp::format($plan->priceAsMoney()) }}</td>
                <td>{{ $plan->interval_weeks }} week(s)</td>
                <td>{{ $plan->next_due_on->toFormattedDateString() }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Statement</h2>
    <table>
        <tr><th>Date</th><th>Detail</th><th class="num">Amount</th><th class="num">Balance</th></tr>
        @foreach ($statement as $row)
            <tr>
                <td>{{ $row['transaction']->post_date->toFormattedDateString() }}</td>
                <td>{{ $row['transaction']->memo }}</td>
                <td class="num">{{ Gbp::format($row['transaction']->amount) }}</td>
                <td class="num">{{ Gbp::format($row['running']) }}</td>
            </tr>
        @endforeach
    </table>
@endsection
```

`resources/views/demos/window-cleaner/portal/pay.blade.php`:

```blade
@extends('demos.window-cleaner.layout')
@section('title', 'Pay online')
@section('content')
    @php use App\Demos\WindowCleaner\Support\Gbp; @endphp
    <h1>Pay online</h1>
    <p>Acting as <strong>{{ $customer->name }}</strong>.
    @if ($owed->isPositive()) You currently owe {{ Gbp::format($owed) }}. @endif
    Pay any amount — more than you owe puts your account in credit; less
    reduces what you owe. (No card details: this is an emulation.)</p>

    <form class="stack" method="post" action="{{ route('wc.portal.pay.store') }}">
        @csrf
        <label for="amount">Amount (£)</label>
        <input id="amount" name="amount" inputmode="decimal" value="{{ old('amount', $suggested) }}" required>
        @error('amount')<small class="owes">{{ $message }}</small>@enderror
        <button>Pay now</button>
    </form>
@endsection
```

`resources/views/demos/window-cleaner/portal/paid.blade.php`:

```blade
@extends('demos.window-cleaner.layout')
@section('title', 'Payment received')
@section('content')
    @php use App\Demos\WindowCleaner\Support\Gbp; @endphp
    <h1>Thank you!</h1>
    <p>We received your payment of <strong>{{ Gbp::format($payment->amountAsMoney()) }}</strong>
    on {{ $payment->paid_at->toFormattedDateString() }}.</p>

    @php $balance = $customer->balance(); @endphp
    @if ($balance->isNegative())
        <p>Your remaining balance is {{ Gbp::format($balance->absolute()) }} owed.</p>
    @elseif ($balance->isPositive())
        <p>Your account is now {{ Gbp::format($balance) }} in credit.</p>
    @else
        <p>Your account is fully settled.</p>
    @endif

    <p><a href="{{ route('wc.portal.account') }}">Back to my account</a></p>
@endsection
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PortalTest`
Expected: PASS (5 tests). Then `vendor/bin/pint --dirty`.

---

### Task 17: Tour pages + Playground

**Files:**
- Create: `app/Demos/WindowCleaner/Http/Controllers/Tour/TourController.php`, `.../Tour/PlaygroundController.php`
- Create: `resources/views/demos/window-cleaner/tour/show.blade.php`, `.../tour/playground.blade.php`
- Modify: `routes/demos/window-cleaner.php`
- Test: `tests/Feature/WindowCleaner/TourTest.php`

**Interfaces:**
- Consumes: `Wallet`, `Gbp`, package exceptions.
- Produces: routes `wc.tour.show` ({page} in level-a|level-b|level-c|checkpoints), `wc.tour.playground` (GET), `wc.tour.playground.store` (POST).

- [ ] **Step 1: Write the failing test**

`tests/Feature/WindowCleaner/TourTest.php`:

```php
<?php

use App\Demos\WindowCleaner\Models\Wallet;

it('serves the four tour pages and 404s unknown ones', function () {
    foreach (['level-a', 'level-b', 'level-c', 'checkpoints'] as $page) {
        $this->get("/window-cleaner/tour/{$page}")->assertOk();
    }

    $this->get('/window-cleaner/tour/level-z')->assertNotFound();
});

it('posts raw credits and debits on the playground wallet', function () {
    $this->post('/window-cleaner/tour/playground', [
        'direction' => 'credit', 'amount' => '10.00', 'currency' => 'GBP', 'memo' => 'top up',
    ])->assertRedirect('/window-cleaner/tour/playground');

    $this->post('/window-cleaner/tour/playground', [
        'direction' => 'debit', 'amount' => '2.50', 'currency' => 'GBP',
    ]);

    $wallet = Wallet::where('name', 'playground')->sole();
    expect($wallet->journal->currentBalance()->getAmount())->toBe('750');

    $this->get('/window-cleaner/tour/playground')
        ->assertOk()
        ->assertSee('£7.50')
        ->assertSee('top up');
});

it('demonstrates CurrencyMismatch when posting USD to the GBP wallet', function () {
    $this->post('/window-cleaner/tour/playground', [
        'direction' => 'credit', 'amount' => '10.00', 'currency' => 'GBP',
    ]);

    $this->post('/window-cleaner/tour/playground', [
        'direction' => 'credit', 'amount' => '5.00', 'currency' => 'USD',
    ])->assertRedirect('/window-cleaner/tour/playground')
        ->assertSessionHas('error');

    // Balance unchanged: the mismatched post never lands.
    $wallet = Wallet::where('name', 'playground')->sole();
    expect($wallet->journal->currentBalance()->getAmount())->toBe('1000');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TourTest`
Expected: FAIL — routes missing

- [ ] **Step 3: Implement**

`app/Demos/WindowCleaner/Http/Controllers/Tour/TourController.php` — one generic view fed from an array; the `code` strings are the actual demo code, kept short:

```php
<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Tour;

use Illuminate\Contracts\View\View;

/**
 * The guided tour: one page per package concept, each pointing at the
 * real file that implements it and the live page where it is running.
 */
class TourController
{
    public function show(string $page): View
    {
        $pages = $this->pages();

        abort_unless(array_key_exists($page, $pages), 404);

        return view('demos.window-cleaner.tour.show', [
            'current' => $page,
            'pages' => $pages,
        ] + $pages[$page]);
    }

    /**
     * @return array<string, array{title: string, intro: string, code: string, file: string, liveUrl: string, liveLabel: string}>
     */
    private function pages(): array
    {
        return [
            'level-a' => [
                'title' => 'Level A — a running balance per model',
                'intro' => 'Add the HasJournal trait to any Eloquent model and it owns a journal: '
                    .'post credits and debits, read balances. In this demo every Customer works '
                    .'this way — their account balance IS their journal. Payments are credits '
                    .'(positive), charges are debits (negative), so a negative balance means the '
                    .'customer owes money. Try the Playground to post raw entries on a scratch wallet.',
                'code' => <<<'PHP'
                    class Customer extends Model
                    {
                        use HasJournal;
                    }

                    $customer->initJournal('GBP');
                    $customer->journal->credit(Money::GBP(2000), 'Payment');
                    $customer->journal->debit(Money::GBP(1500), 'Full house clean');

                    $customer->journal->currentBalance();  // Money::GBP(500)
                    PHP,
                'file' => 'app/Demos/WindowCleaner/Models/Customer.php',
                'liveUrl' => '/window-cleaner/tour/playground',
                'liveLabel' => 'Try it on the Playground',
            ],
            'level-b' => [
                'title' => 'Level B — double entry with TransactionGroup',
                'intro' => 'Every charge and payment in this demo is a balanced TransactionGroup: '
                    .'debits must equal credits or nothing is written. Charging a £15.00 visit '
                    .'debits the customer the gross and credits Sales the net and VAT the tax — '
                    .'three legs, one atomic commit, every leg referencing the Visit row and '
                    .'tagged. The VAT-inclusive price is split with Money::allocate() so no '
                    .'penny is created or lost (try £14.99).',
                'code' => <<<'PHP'
                    ['net' => $net, 'vat' => $vat] = Gbp::vatSplit($grossPrice);

                    TransactionGroup::make()
                        ->addTransaction($customer->journal, 'debit', $grossPrice, $memo, $visit, $date)
                        ->addTransaction(Books::salesJournal(), 'credit', $net, $memo, $visit, $date)
                        ->addTransaction(Books::vatJournal(), 'credit', $vat, "VAT on {$memo}", $visit, $date)
                        ->commit();
                    PHP,
                'file' => 'app/Demos/WindowCleaner/Actions/ChargeVisit.php',
                'liveUrl' => '/window-cleaner/admin/customers',
                'liveLabel' => 'Charge a visit from any customer page',
            ],
            'level-c' => [
                'title' => 'Level C — typed ledgers and the accounting equation',
                'intro' => 'Journals are grouped under typed ledgers: every customer journal sits '
                    .'in Debtors (asset), and the company journals in Bank (asset), Sales (income) '
                    .'and VAT owed (liability). Because every posting is a balanced group, '
                    .'Debtors + Bank always equals VAT owed + Sales — the Books page computes each '
                    .'side with one Ledger::currentBalance() call per ledger and shows the '
                    .'equation holding live.',
                'code' => <<<'PHP'
                    $debtors = Ledger::firstOrCreate(['name' => 'Debtors'], ['type' => StandardLedgerType::ASSET]);

                    $customer->initJournal('GBP')->assignToLedger($debtors);

                    Books::debtorsLedger()->currentBalance('GBP');  // one SQL aggregate
                    PHP,
                'file' => 'app/Demos/WindowCleaner/Actions/EnsureBooksExist.php',
                'liveUrl' => '/window-cleaner/admin/books',
                'liveLabel' => 'See the books balance live',
            ],
            'checkpoints' => [
                'title' => 'Checkpoints — fast balances and closed periods',
                'intro' => 'A checkpoint stores a journal\'s cumulative totals through a date and '
                    .'locks the period behind it: balance queries start from the checkpoint '
                    .'instead of scanning all history, and back-dated writes throw PeriodClosed. '
                    .'The admin Close month button checkpoints every journal through the last '
                    .'month end; the seeder closed the first month of history already, so this '
                    .'demo has been running on a checkpoint since you seeded it.',
                'code' => <<<'PHP'
                    $journal->checkpoint('2026-06-30');    // freeze through end of June

                    $journal->credit(Money::GBP(1000), 'too late', Carbon::parse('2026-06-20'));
                    // => throws PeriodClosed (inside a TransactionGroup it surfaces
                    //    as TransactionCouldNotBeProcessed with PeriodClosed chained)
                    PHP,
                'file' => 'app/Demos/WindowCleaner/Actions/CloseMonth.php',
                'liveUrl' => '/window-cleaner/admin/close-month',
                'liveLabel' => 'Close a month yourself',
            ],
        ];
    }
}
```

`app/Demos/WindowCleaner/Http/Controllers/Tour/PlaygroundController.php`:

```php
<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Tour;

use Academe\LaravelJournal\Exceptions\CurrencyMismatch;
use App\Demos\WindowCleaner\Models\Wallet;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Parser\DecimalMoneyParser;

/**
 * Level A in isolation: a scratch GBP wallet you can post raw credits
 * and debits to. Posting USD provokes the package's CurrencyMismatch —
 * the demo's one deliberately-broken button.
 */
class PlaygroundController
{
    public function show(): View
    {
        $wallet = $this->wallet();

        return view('demos.window-cleaner.tour.playground', [
            'balance' => $wallet->journal->currentBalance(),
            'transactions' => $wallet->journal->transactions()
                ->orderByDesc('post_date')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:10000'],
            'currency' => ['required', 'in:GBP,USD'],
            'memo' => ['nullable', 'string', 'max:100'],
        ]);

        $money = (new DecimalMoneyParser(new ISOCurrencies))
            ->parse((string) $validated['amount'], new Currency($validated['currency']));

        try {
            $this->wallet()->journal->{$validated['direction']}($money, $validated['memo'] ?? null);
        } catch (CurrencyMismatch $e) {
            return redirect()->route('wc.tour.playground')
                ->with('error', 'CurrencyMismatch: '.$e->getMessage());
        }

        return redirect()->route('wc.tour.playground')
            ->with('status', ucfirst($validated['direction']).' posted.');
    }

    private function wallet(): Wallet
    {
        $wallet = Wallet::firstOrCreate(['name' => 'playground']);

        if ($wallet->journal()->doesntExist()) {
            $wallet->initJournal('GBP');
        }

        return $wallet->load('journal');
    }
}
```

Add to `routes/demos/window-cleaner.php`, inside the `window-cleaner` group:

```php
use App\Demos\WindowCleaner\Http\Controllers\Tour\PlaygroundController;
use App\Demos\WindowCleaner\Http\Controllers\Tour\TourController;

    Route::prefix('tour')->name('tour.')->group(function () {
        Route::get('playground', [PlaygroundController::class, 'show'])->name('playground');
        Route::post('playground', [PlaygroundController::class, 'store'])->name('playground.store');
        Route::get('{page}', [TourController::class, 'show'])->name('show');
    });
```

(Order matters: `playground` must be registered before the `{page}` wildcard.)

`resources/views/demos/window-cleaner/tour/show.blade.php`:

```blade
@extends('demos.window-cleaner.layout')
@section('title', $title)
@section('content')
    <nav>
        @foreach ($pages as $slug => $page)
            @if ($slug === $current)<strong>{{ $page['title'] }}</strong>@else<a href="{{ route('wc.tour.show', $slug) }}">{{ $page['title'] }}</a>@endif
            @if (! $loop->last) · @endif
        @endforeach
        · <a href="{{ route('wc.tour.playground') }}">Playground</a>
    </nav>

    <h1>{{ $title }}</h1>
    <p>{{ $intro }}</p>
    <pre><code>{{ $code }}</code></pre>
    <p>The real thing: <code>{{ $file }}</code> — <a href="{{ $liveUrl }}">{{ $liveLabel }}</a></p>
@endsection
```

`resources/views/demos/window-cleaner/tour/playground.blade.php`:

```blade
@extends('demos.window-cleaner.layout')
@section('title', 'Playground')
@section('content')
    @php use App\Demos\WindowCleaner\Support\Gbp; @endphp
    <h1>Playground — Level A in isolation</h1>
    <p>A scratch GBP wallet, outside the business's books. Raw
    <code>credit()</code> / <code>debit()</code> calls, no groups, no ledgers.
    Post in USD to see <code>CurrencyMismatch</code> protect the journal.</p>

    <p class="big">Balance: {{ Gbp::format($balance) }}</p>

    <form class="stack" method="post" action="{{ route('wc.tour.playground.store') }}">
        @csrf
        <select name="direction"><option value="credit">Credit (add)</option><option value="debit">Debit (remove)</option></select>
        <input name="amount" inputmode="decimal" placeholder="Amount, e.g. 10.00" required>
        <select name="currency"><option value="GBP">GBP</option><option value="USD">USD (will fail!)</option></select>
        <input name="memo" placeholder="Memo (optional)">
        @error('amount')<small class="owes">{{ $message }}</small>@enderror
        <button>Post entry</button>
    </form>

    <h2>Entries</h2>
    <table>
        <tr><th>Date</th><th>Memo</th><th class="num">Amount</th></tr>
        @foreach ($transactions as $transaction)
            <tr>
                <td>{{ $transaction->post_date->toFormattedDateString() }}</td>
                <td>{{ $transaction->memo }}</td>
                <td class="num">{{ Gbp::format($transaction->amount) }}</td>
            </tr>
        @endforeach
    </table>
@endsection
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TourTest`
Expected: PASS (3 tests). Then `vendor/bin/pint --dirty`.

---

### Task 18: README + full verification

**Files:**
- Modify: `README.md` (replace the Laravel boilerplate)

**Interfaces:**
- Consumes: everything.
- Produces: a README that gets a stranger from clone to browsing in three commands.

- [ ] **Step 1: Write the README**

Replace `README.md` with (adjust anything that shipped differently):

```markdown
# laravel-journal demos

Runnable, readable demos of [academe/laravel-journal](https://github.com/academe/laravel-ledger)
— accounting journals and double-entry bookkeeping for Eloquent models.

## Run it

    composer install
    php artisan migrate:fresh --seed
    php artisan serve      # or serve via Laravel Herd

Open the site and start at the landing page. No npm, no build step, SQLite only.

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
| B | Every charge/payment is a balanced `TransactionGroup` (with VAT split via `Money::allocate`) | `app/Demos/WindowCleaner/Actions/ChargeVisit.php`, `RecordPayment.php` |
| C | Typed ledgers: Debtors + Bank = VAT owed + Sales, live | Admin → Books, `Actions/EnsureBooksExist.php` |

Plus: checkpoints (Admin → Close month), transaction references
(`Visit`/`Payment` ⟷ journal entries), and tags (statement pages).

Start at **/window-cleaner** and follow the Tour.

## Structure

Each demo lives in its own namespace so more can join:

    app/Demos/WindowCleaner/{Models,Actions,Support,Notifications,Http}
    routes/demos/window-cleaner.php
    resources/views/demos/window-cleaner/

## Tests

    php artisan test

The feature tests double as executable documentation — start with
`tests/Feature/WindowCleaner/ChargeVisitTest.php`.
```

- [ ] **Step 2: Full suite**

Run: `php artisan test`
Expected: ALL PASS (≈30 tests). Fix anything red before proceeding.

- [ ] **Step 3: Lint**

Run: `vendor/bin/pint`
Expected: clean (or fixes applied; re-run tests if it changed anything).

- [ ] **Step 4: Manual browse checklist**

With the DB seeded (`php artisan migrate:fresh --seed`), open `http://laravel-journal-window-cleaner-demo.test/` and verify by hand:

1. Landing → Window cleaner → three area cards render.
2. Admin dashboard: owed total, bank balance, due visits; "Run due visits" charges and flashes a count.
3. A customer page: statement shows running balance and tags; ad-hoc visit and manual payment forms work.
4. Books: equation line says "balances"; recent groups show Dr/Cr legs.
5. Close month: closes, then re-running reports "already closed".
6. SMS: send balance texts → outbox shows phone-style bubbles.
7. Portal: pick a customer who owes → account shows "You owe" → Pay online prefilled → overpay → confirmation shows "in credit".
8. Tour: all four pages + Playground; posting USD flashes CurrencyMismatch.

Report anything broken; do not commit (Jason commits manually).

---

## Plan self-review notes (already applied)

- Spec coverage: config/VAT ✔ (T2/T3), models ✔ (T4-T6), actions ✔ (T7-T11), seeder/personas/checkpoint ✔ (T12), admin ✔ (T13-T15), portal ✔ (T16), tour/playground ✔ (T17), README ✔ (T18). Herd serves the site, so no deploy task.
- The dashboard view (T13) references routes owned by T14/T15; placeholder routes are registered in T13 and replaced later — this is deliberate, keep the names identical.
- `Customer::factory()` runs `EnsureBooksExist` per creation; it is idempotent and cheap on SQLite. The seeder does NOT use the factory (hardcoded data) and initialises journals itself.
- Money assertions use `->equals()` (moneyphp) rather than object comparison.
- The seeder test replays ~180 days × ~14 plans and is the slowest test in the suite; if it exceeds ~2 minutes, reduce HISTORY_MONTHS to 4 — update the SeederTest thresholds accordingly.
