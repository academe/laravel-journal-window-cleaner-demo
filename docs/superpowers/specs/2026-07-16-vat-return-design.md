# VAT Return Report — Design

**Date:** 2026-07-16
**Status:** Approved design, pending implementation plan
**Builds on:** `2026-07-14-window-cleaner-demo-design.md` (the shipped Shiny & Sons demo)

## Goal

Add a quarterly VAT detail report to the window-cleaner demo, netting output VAT
(collected on sales) against input VAT (paid on purchases of supplies and
equipment). This requires building the purchases side of the business, which
also brings the package's `EXPENSE` ledger type — the only `StandardLedgerType`
the demo does not yet use — into play.

Everything follows the demo's established rules: docs-by-example doc-blocks on
action/support classes, all posting through action classes as balanced
`TransactionGroup`s, GBP integer minor units with VAT-inclusive consumer-facing
prices, `wc_` table prefix, no npm, Pest feature tests, no git commits by
agents.

## Decisions made during brainstorming

| Question | Decision |
| --- | --- |
| Scope | Full return: build purchases/input VAT, not output-only |
| Purchase payment model | Paid immediately from Bank — no supplier accounts/Creditors ledger |
| Report shape | Summary totals + two detail listings (sales, purchases); not HMRC box format |
| Input VAT account | Debited to the existing "VAT owed" journal; its live balance is the net position |
| Report data source | Pure read over the VAT journal's transactions (Approach A) — never the domain tables |
| Period selection | Drop-down of calendar quarters (Jan–Mar, Apr–Jun, …) derived from the data |
| Filed-returns workflow | Out of scope (possible later increment); report only *notes* checkpoint state |

## Section 1: Books changes

`EnsureBooksExist` gains, idempotently:

- Ledger **"Expenses"**, `StandardLedgerType::EXPENSE`.
- CompanyAccount **"Expenses"** with a GBP journal assigned to that ledger.

`Books` gains constants/lookups: `EXPENSES = 'Expenses'`,
`LEDGER_EXPENSES = 'Expenses'`, `expensesJournal(): Journal`,
`expensesLedger(): Ledger`.

The Books page equation becomes:

> **Assets (Debtors + Bank) = Liabilities (VAT owed) + Income (Sales) − Expenses**

computed with `Ledger::currentBalance('GBP')` per side (each ledger already
signs by its normal balance). The existing `AdminOperationsTest` equation
assertions update to match.

## Section 2: `Purchase` model

Migration `wc_purchases`:

| column | type | notes |
| --- | --- | --- |
| `supplier` | string | free text — purchases are paid at the till, no supplier accounts |
| `category` | string | `supplies` \| `equipment` |
| `price` | unsignedInteger | gross, VAT-inclusive, minor units — same convention as visits |
| `purchased_on` | date | |

Model `App\Demos\WindowCleaner\Models\Purchase`: uses `HasJournalTransactions`
(journal legs reference it), `priceAsMoney(): Money`, `casts` for the date,
factory. Morph map (AppServiceProvider) gains `'purchase' => Purchase::class`.

## Section 3: `RecordPurchase` action

The demo's third posting action; mirrors `ChargeVisit`/`RecordPayment` exactly
in shape (generous doc-block, whole body in `DB::transaction`, tags via
`TagsTransactionGroups`).

```php
RecordPurchase::run(string $supplier, string $category, Money $gross, ?CarbonInterface $date = null): Purchase
```

One balanced three-leg group from `Gbp::vatSplit($gross)`:

| leg | journal | amount |
| --- | --- | --- |
| debit | Expenses | net |
| debit | VAT owed | input VAT |
| credit | Bank | gross |

All legs reference the `Purchase`; tags
`['kind' => 'purchase', 'category' => $category]`. Consequences the doc-block
teaches: the VAT journal's running balance is now the *net* VAT position
(output collected − input reclaimable), and Bank falls by what was actually
paid, keeping the extended accounting equation live.

## Section 4: `VatReturn` support class

`App\Demos\WindowCleaner\Support\VatReturn` — pure read, no storage, static
API in the style of `Statement::for()`.

- `VatReturn::quarters(): array` — every calendar quarter (Jan–Mar, Apr–Jun,
  Jul–Sep, Oct–Dec) between the earliest and latest entry on the VAT journal,
  newest first, keyed/labelled like `2026-Q1`. Feeds the drop-down; default
  selection is the newest quarter.
- `VatReturn::for(string $quarter)` — parses `YYYY-Qn`, then queries the VAT
  journal's transactions with `post_date` inside the quarter:
  - **credit legs → output VAT rows** (sales);
  - **debit legs → input VAT rows** (purchases).
  - Each row recovers its sibling legs by `transaction_group` to get the net
    amount (the Sales or Expenses leg) and follows the `reference` morph to
    the Visit/Purchase for the description (service/customer or
    supplier/category).
  - Returns rows for both sections plus totals: output VAT, input VAT,
    **net VAT due** (output − input), net sales, net purchases.

This is deliberately the package showcase: date-ranged `post_date` queries,
`debit`/`credit` Money casts, `transaction_group` reassembly, and `reference`
morphs — the report cannot drift from the books because it *is* the books.

## Section 5: Routes and pages

Additions inside the existing admin route group (`wc.admin.*`):

| Method+URI (under /window-cleaner) | Name |
| --- | --- |
| GET /admin/purchases | wc.admin.purchases.index |
| POST /admin/purchases | wc.admin.purchases.store |
| GET /admin/vat-return | wc.admin.vat-return |

- **Purchases page**: record form (supplier text, category select, gross
  price; posts at today, matching the visit/payment forms) above a
  recent-purchases table. Controller validates and calls `RecordPurchase` —
  controllers never touch journals directly.
- **VAT return page**: quarter drop-down (GET form, `?quarter=2026-Q1`,
  defaulting to the newest quarter), summary cards (output VAT, input VAT,
  net VAT due), then the sales and purchases detail tables — one aligned
  table structure per section with net + VAT columns and footer totals. A
  one-line read-only note states whether the quarter's end falls within the
  VAT journal's checkpointed (closed) period.
- Dashboard links to both pages. The Books page needs no change beyond the
  Section 1 equation; its recent-entries table picks up purchase groups
  automatically.

## Section 6: Seeder

Deterministic, rule-based purchases woven into the existing 6-month replay:

- Monthly **supplies** purchase: £23.94 gross (net £19.95 / VAT £3.99 —
  awkward pennies exercise `vatSplit`), "Squeaky Wholesale", on the first
  Monday of each history month.
- Two one-off **equipment** purchases: a ladder, £180.00 gross, "Ladders R
  Us", on the 15th of history month 2; a water-fed pole, £249.00 gross,
  "PureClean Systems", on the 15th of history month 5.

Both fully-seeded quarters therefore contain sales *and* purchases, so every
drop-down choice yields a meaningful report. Re-seeding still requires
`migrate:fresh --seed`; the existing already-seeded guard is unchanged.

## Section 7: Tests

- `RecordPurchaseTest`: balanced three-leg group with shared UUID and tags;
  VAT journal balance moves to the net position; Bank falls by gross;
  historical dating; extended accounting equation holds on awkward pennies.
- `VatReturnTest`: seed known visits + purchases straddling a quarter
  boundary; assert per-quarter output/input/net totals, detail row counts,
  and that rows land in the correct quarter; `quarters()` lists exactly the
  populated quarters newest-first.
- `PurchasesPageTest` (or extension of `AdminPagesTest`): form → action →
  purchase row + journal legs exist; validation rejects bad category/amount.
- `VatReturnPageTest`: page renders totals, quarter options, detail rows;
  quarter switch changes the numbers.
- Updated: `BooksFoundationTest` (5 ledgers, 4 company accounts, new lookups);
  `AdminOperationsTest` (extended equation wording).

## Knock-on updates

- README: short paragraph on purchases + the VAT return page.
- Tour Level C page: brief pointer to the VAT return as "reading both sides
  of one journal" with a link.
- `AppServiceProvider` morph map: add `purchase`.

## Out of scope

- Filed/stored VAT returns, filing workflow, or checkpointing quarters from
  the report page (possible later increment; the report only displays
  checkpoint state).
- Supplier accounts / Creditors ledger (purchases are cash-at-till).
- Cash-basis VAT accounting (the demo is invoice-basis: VAT dates from
  `post_date`).
- HMRC box-numbered return format.
