# Window Cleaner Demo — Design

**Date:** 2026-07-14
**Repo:** `laravel-journal-window-cleaner-demo` (this repo) — a fresh Laravel 13 app, no starter kit, served by Herd at `http://laravel-journal-window-cleaner-demo.test/`
**Package under demonstration:** [`academe/laravel-journal`](https://github.com/academe/laravel-journal) (local working copy at `c:/Users/jason/Documents/dev/laravel-journal`)

## Purpose

A docs-by-example demo for developers evaluating `academe/laravel-journal`.
They clone it, run `composer install && php artisan migrate --seed && php artisan serve`
(or just open the Herd site), click around, and read the code. Readable,
well-commented code that maps directly to the package README's concepts is the
priority; the UI is functional, not polished.

One business scenario — a window cleaning round — demonstrates all three of
the package's levels *stacked on the same journals*, making the README's
"scales with your rigour" claim literal:

- **Level A** — simple running balance: each customer's account balance is
  their journal.
- **Level B** — double entry with `TransactionGroup`: every charge and
  payment is a balanced multi-leg group.
- **Level C** — ledger-enforced double entry: the same journals are grouped
  under typed ledgers so the accounting equation is visible, live.

Also exercised: checkpoints (period closing), transaction references
(`HasJournalTransactions`), tags, `Money::allocate()` for VAT splitting.

## Prerequisite: package Laravel 13 support and install source

The package originally required `illuminate/*: ^12.0`; this app is Laravel
`^13.8`. The constraints have been widened to `^12.0 || ^13.0` in the
package working copy (all 120 tests passing) — that change must be
**committed and pushed to GitHub** before the demo can install.

The package is not yet on Packagist, so the demo installs it from GitHub
via a Composer **VCS repository** (note the repo is named `laravel-ledger`
but the package is `academe/laravel-journal`):

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/academe/laravel-ledger" }
]
```

then `composer require academe/laravel-journal:dev-main`. Switch to a
tagged Packagist release once one exists.

## The scenario

**"Shiny & Sons"**, a VAT-registered window cleaning business:

- Customers subscribe to services ("Front only", "Full house",
  "Conservatory", "Gutter clean"…) at **per-customer prices**, on
  **individual schedules** (every 1/2/4/8 weeks, on a given weekday).
- All money is **GBP**. Consumer prices are **VAT-inclusive**; the standard
  VAT rate (20%) lives in config (`config/demo.php`).
- Customers hold an account balance: charges debit it, payments credit it.
  They may overpay or underpay freely — the balance just moves.
- Customers can "log in" (emulated) to see their account and pay online
  (a plain amount form — no card handling).
- The business can text balance reminders (emulated SMS).

## Domain model

Package tables (`journals`, `journal_transactions`, `journal_checkpoints`,
`journal_ledgers`) come from the package's published migrations. Demo tables
are prefixed `wc_` so a future second demo can share the database.

| Model | Notes |
| --- | --- |
| `Customer` | Name, address, phone (fake). **Uses `HasJournal`** — the journal is their account balance. |
| `Service` | Catalogue of offered services. |
| `ServicePlan` | Customer ⟷ service: per-customer `price` (minor units, VAT-inclusive), `interval_weeks` (1/2/4/8), `next_due_on` date, active flag. Carries "different services, different costs, different days, different periods". |
| `Visit` | One performed service: plan, date, price captured at charge time. **Uses `HasJournalTransactions`** — the entries that charged it are reachable from the visit (demos the `reference` morph). |
| `Payment` | Amount, method (always `online`), timestamp. Also **uses `HasJournalTransactions`**. |
| `CompanyAccount` | The README's stand-in owner pattern: three seeded rows — **Sales**, **VAT**, **Bank** — each owning a journal. |
| `SmsMessage` | The fake SMS outbox. |
| `Wallet` | Scratch model for the Playground page only; deliberately outside the business's books. |

## Accounting design

**Sign convention (falls out of the package naturally):** on a customer's
journal, payments are credits (positive), charges are debits (negative), so
**negative balance = customer owes money**. Viewed from the business's side,
the same journals under a debit-normal Debtors ledger report amounts owed as
positive.

**Ledgers (level C):**

| Ledger | Type | Journals |
| --- | --- | --- |
| Debtors | `asset` | **every customer journal** |
| Bank | `asset` | CompanyAccount "Bank" journal |
| Sales | `income` | CompanyAccount "Sales" journal |
| VAT owed | `liability` | CompanyAccount "VAT" journal |

Because every posting is a balanced group, the accounting equation holds at
all times: **Debtors + Bank (assets) = VAT owed (liabilities) + Sales
(income)**. The Books page shows this check live.

**Posting flows (level B)** — two action classes do *all* posting; they are
the files the Tour stars:

- `ChargeVisit` — for a £15.00 (VAT-inclusive) visit, one
  `TransactionGroup`:
  - debit customer £15.00
  - credit Sales £12.50
  - credit VAT £2.50

  The inclusive price splits via `Money::allocate([100, 20])` so no penny is
  lost (showcases moneyphp; tested against awkward amounts like £14.99).
  Creates the `Visit` row and sets it as the entries' `reference`. It does
  **not** touch scheduling — ad-hoc visits use it directly; scheduled runs
  go through `RunDueVisits`, which calls it for each due plan and then rolls
  that plan's `next_due_on` forward.
- `RecordPayment` — one `TransactionGroup`:
  - credit customer £X
  - debit Bank £X

  Overpayment and underpayment need no special handling. Creates the
  `Payment` row as the entries' `reference`.

**Tags:** entries carry tags so the feature is visible in statements —
e.g. `['kind' => 'visit', 'service' => 'gutter-clean']`,
`['kind' => 'payment', 'channel' => 'online']`.

**Checkpoints:** an admin **Close month** action checkpoints all journals
through the last month-end. The Tour demonstrates that a back-dated edit
behind a checkpoint throws `PeriodClosed`.

## Pages

Landing page lists the demos in the repo (just this one for now), linking to
three areas:

### Admin (the window cleaner's side)

- **Dashboard** — visits due today/overdue, total owed by customers, bank
  balance.
- **Customers** — list with balances; detail page shows plans, a statement
  (running balance), and buttons to record an ad-hoc visit or a manual
  payment.
- **Run due visits** — one button: charges every plan with
  `next_due_on <= today` via `ChargeVisit`. Also
  `php artisan demo:run-visits`. Real time only moves forward; there is no
  simulated clock.
- **Books** (level C) — ledger balances, the accounting-equation check, and
  recent `TransactionGroup`s rendered as balanced journal entries.
- **Close month** — creates checkpoints through the last month-end; lists
  existing checkpoints; demonstrates the `PeriodClosed` failure.
- **Send balance texts** — fires the `BalanceReminder` notification to every
  customer who owes money; links to the **SMS outbox** page, rendered like a
  phone conversation view.

### Customer portal

No real auth: an **"act as" customer switcher** stored in session — zero
auth code to distract from the journal code.

- **My account** — balance ("you owe £23.00" / "in credit £5.00"), services
  and prices, next visit date, statement.
- **Pay online** — plain amount form, prefilled with the amount owed but
  editable (over/underpay is one keystroke); posts via `RecordPayment`;
  confirmation page.

### Tour

One page per concept — **Level A**, **Level B**, **Level C**,
**Checkpoints** — each with a short written explanation, the actual code
excerpt, and a link to the live page where it's happening. Plus:

- **Playground** — a scratch `Wallet` journal with a form to post raw
  `credit()`/`debit()` calls and watch the balance: level A in true
  isolation, outside the business's books. Also the one place
  `CurrencyMismatch` can be provoked.

## SMS emulation

A real Laravel Notification (`BalanceReminder`) delivered through a custom
**demo SMS channel** that writes rows to `wc_sms_messages`, shown on the
outbox page. This is the realistic integration shape — swap the channel for
Twilio/Vonage in production — while staying fully offline.

## Time model and seeding

- The seeder builds ~10 customers with varied plans (weekly to 8-weekly,
  different weekdays and prices) and replays **~6 months of history through
  the same action classes** with historical post dates — so the seeded books
  balance by construction.
- Customers get payment personas (prompt payer, always-behind,
  standing-order overpayer, delinquent) so seeded balances land on both
  sides of zero.
- Seeding is **deterministic** (fixed faker seed) so screenshots and docs
  stay stable.
- One historical month is pre-checkpointed.
- Database: **SQLite** (the skeleton's default).

## Stack and structure

- **Plain Blade, no build step.** Controllers + Blade views; styling via a
  small hand-written classless CSS file in `public/`. The skeleton's
  Vite/Tailwind scaffolding is left unused (or removed) — running the demo
  must not require npm.
- Multi-demo readiness by **namespacing, not a module system**:

```text
app/Demos/WindowCleaner/
    Models/          Customer, Service, ServicePlan, Visit, Payment,
                     CompanyAccount, SmsMessage, Wallet
    Actions/         ChargeVisit, RecordPayment, RunDueVisits, CloseMonth,
                     SendBalanceTexts
    Notifications/   BalanceReminder (+ DemoSmsChannel)
    Http/            Controllers, FormRequests
routes/demos/window-cleaner.php      ← prefixed /window-cleaner
resources/views/demos/window-cleaner/
database/migrations/                 ← shared; demo tables prefixed wc_
database/seeders/WindowCleanerSeeder.php
```

A future demo drops in alongside (`app/Demos/GiftCards/…`) and the landing
page grows a card.

## Error handling

Deliberately thin — it's a demo — but interesting failures are **shown, not
hidden**:

- Payment form validates amount > 0 plus a sanity cap.
- Everything is GBP, so `CurrencyMismatch` can't fire in normal flows; the
  Tour mentions it and the Playground can provoke it.
- `PeriodClosed` is deliberately provoked on the Close-month tour page.
- `DebitsAndCreditsDoNotEqual` is unreachable through the action classes by
  construction — which the Tour points out as the design working.

## Testing

Pest feature tests that double as executable documentation:

- VAT split correctness, including awkward penny amounts (£14.99).
- Payment/overpayment moves the balance through zero.
- Books equation holds after a full seed run.
- `RunDueVisits` charges exactly the due plans and rolls `next_due_on`.
- Close month blocks back-dated edits (`PeriodClosed`).
- Balance texts write the right outbox rows for exactly the customers who
  owe.

CI: GitHub Actions running the suite on SQLite.

## Documentation

The repo README mirrors the Tour: what each level is, where to click, where
the code lives — plus the setup steps and the per-demo section pattern for
future demos.

## Out of scope

- Real authentication, real payments, real SMS providers.
- Deployment/hosting hardening (this is a clone-and-run demo).
- A polished UI (functional, classless-CSS styling only).
- The second demo scenario (structure anticipates it; nothing is built).
