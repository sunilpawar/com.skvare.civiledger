# CiviLedger — Financial Audit & Integrity Extension

**Extension Key:** `com.skvare.civiledger`  
**Author:** [Skvare](https://skvare.com)  
**License:** AGPL-3.0  
**CiviCRM Compatibility:** 6.0+  
**PHP Compatibility:** 8.1+  

---

## Overview

CiviLedger is a comprehensive financial audit, integrity checking, and correction toolkit for CiviCRM. It fills critical gaps that CiviCRM core does not address:

| # | Feature | What it does |
|---|---|---|
| 1 | 🔍 Integrity Checker | Detects broken links in the financial data chain |
| 2 | 🛠️ Chain Repair Tool | Auto-rebuilds missing financial records |
| 3 | 📊 Audit Trail UI | Per-contribution money flow drill-down with duplicate FI detection |
| 4 | 💰 Account Balance Dashboard | Live balances per financial account |
| 5 | 📈 Account Balance Movement | Per-account transaction drill-down with filters |
| 6 | ⚠️ Amount Mismatch Detector | Flags contributions where amounts don't balance; suggests fixes |
| 7 | ✏️ Account Correction Tool | Corrects FROM/TO accounts via proper double-entry reversal |
| 8 | 📉 Financial Dashboard | Chart.js visualisations — monthly trend, account type bars, doughnut |
| 9 | 🧾 Tax Mapping | Deductible vs. non-deductible breakdown by financial type with bar chart |
| 10 | 🔒 Period Close / Lock | Lock a financial period; protects transactions from correction |
| 11 | 📋 Hash-Chained Audit Log | Immutable, tamper-evident log of all write operations |

---

## Background: The CiviCRM Financial Chain

CiviCRM stores financial data across six related tables. For any contribution to be financially complete, a full chain must exist:

```
civicrm_contribution
├── id
├── total_amount
├── trxn_id
│
├── civicrm_line_item
│   ├── id
│   ├── total_amount
│   ├── financial_type_id
│   ├── contribution_id
│   │
│   └── civicrm_financial_item
│       ├── id
│       ├── entity_table = civicrm_line_item
│       ├── entity_id = line_item.id
│       ├── amount
│       ├── contact_id
│       ├── financial_account_id
│       │
│       └── civicrm_entity_financial_trxn
│           ├── entity_table = civicrm_financial_item
│           ├── entity_id = financial_item.id
│           ├── financial_trxn_id
│           ├── amount
│
├── civicrm_financial_trxn
│   ├── id
│   ├── trxn_id (matches contribution.trxn_id)
│   ├── total_amount
│   ├── from_financial_account_id
│   ├── to_financial_account_id
│   ├── trxn_date
│   ├── payment_processor_id
│
└── civicrm_entity_financial_trxn
    ├── entity_table = civicrm_contribution
    ├── entity_id = contribution.id
    ├── financial_trxn_id
    ├── amount
```

**If any link in this chain is missing:**
- Income reports show wrong totals
- Bookkeeping batch exports are incomplete
- Deferred revenue tracking breaks
- Audit trails are unusable
- Refunds cannot be properly processed

This is a known issue in CiviCRM that occurs when contributions are created via webforms, third-party integrations, or IPN payment processor callbacks. CiviLedger detects, reports, and fixes all of these silently broken records.

---

## Installation

### Via CiviCRM UI (recommended)

1. Download the latest release zip from [GitHub](https://github.com/skvare/com.skvare.civiledger)
2. Go to **Administer → System Settings → Extensions**
3. Click **Add New** and upload the zip file
4. Click **Install**

### Via command line (cv)

```bash
cv ext:install com.skvare.civiledger
```

### Manual

1. Unzip into your CiviCRM extensions directory:
   ```
   sites/default/files/civicrm/ext/com.skvare.civiledger/
   ```
2. Go to **Administer → System Settings → Extensions → Refresh**
3. Find **CiviLedger** and click **Install**

### Post-install

The install script creates four tables:

```sql
civicrm_civiledger_correction_log  -- account correction audit records
civicrm_civiledger_audit_log       -- hash-chained immutable event log
civicrm_civiledger_repair_log      -- per-action repair detail log
civicrm_civiledger_period_lock     -- financial period lock records
```

---

## Menu Location

After installation, all tools are accessible under:

> **Contributions → CiviLedger → ...**

Direct URLs:

| Tool | URL |
|---|---|
| Dashboard | `/civicrm/civiledger/dashboard` |
| Integrity Checker | `/civicrm/civiledger/integrity-check` |
| Chain Repair | `/civicrm/civiledger/chain-repair` |
| Repair Detail | `/civicrm/civiledger/repair-detail?cid=XXX` |
| Audit Trail | `/civicrm/civiledger/audit-trail?contribution_id=XXX` |
| Account Balance | `/civicrm/civiledger/balance` |
| Account Balance Movement | `/civicrm/civiledger/balancemovement?account_id=XXX` |
| Mismatch Detector | `/civicrm/civiledger/mismatch-detector` |
| Account Correction | `/civicrm/civiledger/account-correction` |
| Financial Dashboard | `/civicrm/civiledger/financial-dashboard` |
| Tax Mapping | `/civicrm/civiledger/tax-mapping` |
| Period Close | `/civicrm/civiledger/period-close` |
| Audit Log | `/civicrm/civiledger/audit-log` |

---

## Feature Details

---

### Feature 1 — 🔍 Integrity Checker

**URL:** `/civicrm/civiledger/integrity-check`

Scans your database and detects five categories of broken financial chains:

| Issue | Table affected | Impact |
|---|---|---|
| Missing line items | `civicrm_line_item` | No breakdown of what was purchased |
| Missing financial items | `civicrm_financial_item` | Income accounts not credited |
| Missing contribution→trxn link | `civicrm_entity_financial_trxn` | Payment status unknown |
| Missing financial item→trxn link | `civicrm_entity_financial_trxn` | **Most critical** — cash cannot be explained |
| Orphaned financial transactions | `civicrm_financial_trxn` | Transactions exist with no parent |

**Filters available:** Date range, Contribution status

Each broken record has a direct **Repair** button and an **Audit Trail** link.

---

### Feature 2 — 🛠️ Chain Repair Tool

**URL:** `/civicrm/civiledger/chain-repair`  
**Detail page:** `/civicrm/civiledger/repair-detail?cid=XXX`

Automatically reconstructs the complete financial chain for broken contributions.

**What it does per contribution:**

1. Verifies line items exist — creates a default one from contribution data if missing
2. Creates missing `civicrm_financial_item` rows, linked to the correct income account via `civicrm_entity_financial_account`
3. Creates missing `civicrm_financial_trxn` if none exists, using the contribution's payment instrument and financial type
4. Creates missing `civicrm_entity_financial_trxn` rows for both:
   - `entity_table = civicrm_contribution`
   - `entity_table = civicrm_financial_item`

The **Repair Detail** page shows a full pre- and post-repair chain analysis, including layer-by-layer amount totals (line items, financial items, transactions) so you can see exactly what changed.

**Modes:**
- **Single repair** — repair one contribution at a time (from Integrity Checker)
- **Batch repair** — select multiple broken contributions and repair all at once
- **Repair all** — repair up to N broken contributions in one run (configurable limit)

> ⚠️ **Always backup your database before running a batch repair.**

A real-time repair log is shown on screen with colour-coded entries:
- `✓ fixed` — record was created
- `— skip` — record already existed, no change needed
- `⚠ warning` — fallback account was used
- `✗ error` — something failed

---

### Feature 3 — 📊 Audit Trail UI

**URL:** `/civicrm/civiledger/audit-trail?contribution_id=XXX`

Shows the complete financial hierarchy for any single contribution across all six tables, as a layered drill-down:

```
Layer 1 — BUSINESS
  Contribution #123  |  $1,000  |  Donation  |  Completed
    └── Line Item #45  |  $1,000  |  Donation

Layer 2 — ACCOUNTING
  Line Item Total: $1,000  |  Financial Items Total: $1,000
  Financial Item #67  |  $1,000  |  Income Account: Donation Revenue  |  Paid
    └── entity_financial_trxn → trxn #89  ✓

Layer 3 — MONEY MOVEMENT
  Financial Trxn #89  |  Status: Completed
    FROM: Accounts Receivable  →  TO: Stripe Payment Processor
    $1,000  |  2024-03-15  |  Stripe  |  Visa ending 4242
    └── entity_financial_trxn → contribution #123  ✓
```

**Integrity flags** are shown at the top of each trail:

| Flag | Meaning |
|---|---|
| ✅ has_line_items | Line items exist |
| ✅ has_financial_items | Financial items exist |
| ✅ has_financial_trxn | A payment transaction exists |
| ✅ has_contribution_link | Contribution is linked to its transaction |
| ✅ has_financial_item_links | All financial items are linked to transactions |
| ✅ amount_match | All layer sums equal contribution total |

**Layer 2 enhancements:**
- Sum totals for line items and financial items are displayed next to the heading in colour-coded badges (green = match, red = mismatch)
- Each financial item shows its `status_id` label (Paid / Partially paid / Unpaid) as a colour-coded badge
- Financial transactions display `payment_instrument_id`, `payment_processor_id`, card type, card last-4, check number, fee amount, and `status_id` label

**Duplicate Financial Item detection:**
- When a line item has more than one Paid or Partially paid financial item and their sum exceeds the line total, a warning banner is shown
- Each financial item is labelled as **Keep** or **Duplicate candidate**
- A **Delete** button per duplicate opens a confirmation modal; on confirm the financial item and its `civicrm_entity_financial_trxn` link are deleted and the event is written to the audit log
- Unpaid (status=3) financial items are never flagged as duplicates — they represent legitimate adjustments

Any red flag links directly to the Repair tool.

---

### Feature 4 — 💰 Account Balance Dashboard

**URL:** `/civicrm/civiledger/balance`

Calculates live balances for every active financial account by summing all `civicrm_financial_trxn` movements.

**Summary stats row:**
- Total transactions in period
- Total payments received
- Refund / reversal count
- Number of accounts with activity

**Balance table (grouped by account type):**

| Account | Type | Code | Credits (Cr) | Debits (Dr) | Net Balance | Transactions |
|---|---|---|---|---|---|---|
| Donation Revenue | Revenue | 4000 | $50,000 | $0 | $50,000 | 142 |
| Stripe Processor | Asset | 1200 | $48,500 | $1,500 | $47,000 | 142 |
| Accounts Receivable | Asset | 1100 | $1,500 | $50,000 | -$48,500 | 142 |

**Date filter** allows reporting for any custom period.

---

### Feature 5 — 📈 Account Balance Movement

**URL:** `/civicrm/civiledger/balancemovement?account_id=XXX`

Drill-down from the Account Balance Dashboard into a single account's full transaction history for any date range.

- Displays every credit and debit movement with date, direction, amount, contact name, and a link to the originating contribution
- Shows account summary stats: total credits, total debits, net balance, and account type
- Account selector dropdown to switch between accounts without leaving the page

---

### Feature 6 — ⚠️ Amount Mismatch Detector

**URL:** `/civicrm/civiledger/mismatch-detector`

Enforces the CiviCRM financial golden rule:

```
contribution.total_amount
  == SUM(civicrm_line_item.line_total)
  == SUM(civicrm_financial_item.amount)
  == SUM(civicrm_financial_trxn.total_amount WHERE is_payment=1)
```

Any contribution where these four sums disagree by more than `$0.01` is flagged.

**Three types of mismatch detected:**

| Type | Cause |
|---|---|
| Line item mismatch | Webform or API created contribution without correct line items |
| Financial item mismatch | Financial items were partially created or edited manually |
| Transaction mismatch | Partial payment recorded but contribution marked Completed |

Each mismatch row shows all four amounts side-by-side with a `Δ` difference badge. The **Suggest Fix** column offers one-click repair buttons where the fix is safe to apply automatically:
- **Repair line items** — regenerates line items from contribution data
- **Repair financial items** — regenerates financial items from line items
- Payments mismatches are flagged as manual-review-only (no auto-fix)

Links to Audit Trail and the Repair Detail page are provided for each row.

---

### Feature 7 — ✏️ Account Correction Tool

**URL:** `/civicrm/civiledger/account-correction`

Allows authorised administrators to correct a wrong `from_financial_account_id` or `to_financial_account_id` on any financial transaction — using **proper double-entry reversal**, not a direct edit.

**Why reversal instead of direct edit?**

Direct editing breaks the audit trail. The correct accounting approach is:

```
Step 1: Create NEGATIVE reversal transaction on OLD accounts
         FROM: [old from account]  →  TO: [old to account]  Amount: -$1,000

Step 2: Create NEW positive transaction on CORRECT accounts
         FROM: [correct from account]  →  TO: [correct to account]  Amount: +$1,000

Step 3: Link both new transactions to the original contribution
Step 4: Write entry to audit log (who, when, why, what changed)
```

**The original transaction is never modified.** The net effect on the ledger is zero for the old accounts and correct for the new accounts.

**Fields you can change:**
- `from_financial_account_id` — e.g. wrong payment processor account
- `to_financial_account_id` — e.g. wrong income category
- Both at the same time

**Required:** A written reason for the correction (mandatory for audit compliance). Corrections are blocked if the transaction falls within a locked period (see Feature 10).

**Correction history** is shown on the transaction detail page, listing every correction ever made to that transaction with the who/when/why.

---

### Feature 8 — 📉 Financial Dashboard

**URL:** `/civicrm/civiledger/financial-dashboard`

Three Chart.js 4.x visualisations rendered from live financial data:

| Chart | Type | What it shows |
|---|---|---|
| Monthly Payment Trend | Line chart | Payments, refunds, and net per month (last 12 months) |
| Credits vs. Debits by Account Type | Grouped bar | Credits and debits per account type for the selected date range |
| Cash / AR / Revenue / Expenses | Doughnut | Relative balances of the four major account categories |

KPI stat cards at the top show total payments, total refunds, net movement, and active account count for the selected period.

Chart.js 4.4.4 is loaded from jsDelivr CDN. If offline operation is required, replace `addScriptUrl()` with a local file reference in `CRM/Civiledger/Page/FinancialDashboard.php`.

---

### Feature 9 — 🧾 Tax Mapping

**URL:** `/civicrm/civiledger/tax-mapping`

Surfaces CiviCRM's `non_deductible_amount` data for tax reporting and donor receipting.

**Three panels:**

1. **Summary totals** — total contributions, total deductible, total non-deductible, count of split contributions (partially deductible), and data issues count for the selected date range.

2. **Breakdown by financial type** — per-type table showing contribution count, line item count, total amount, non-deductible amount, deductible amount, and partially-deductible (split) count. Starts from `civicrm_contribution` (LEFT JOIN to line items) so contributions with no line items are included, and uses a proportional fallback when all line items have `non_deductible_amount=0` but the contribution-level field is set.

3. **Monthly bar chart** — 12-month deductible vs. non-deductible trend (Chart.js 4.x). Uses the same fallback logic as the breakdown for accuracy.

**Non-deductible amount resolution priority:**
1. `civicrm_line_item.non_deductible_amount` (most granular — used when > 0)
2. Proportional share of `civicrm_contribution.non_deductible_amount` (fallback when all line items = 0)
3. `civicrm_contribution.non_deductible_amount` (for contributions with no line items)

---

### Feature 10 — 🔒 Period Close / Lock

**URL:** `/civicrm/civiledger/period-close`

Protects completed accounting periods from accidental correction.

**How it works:**
- An administrator sets a **lock date** and a reason
- All account corrections (Feature 7) where `trxn_date` is before the lock date are blocked
- The lock is stored in `civicrm_civiledger_period_lock`
- An active lock can be **unlocked** with a separate reason (unlock is also logged)
- Full lock history (locked by / date / reason / unlocked by / date / reason) is displayed on the page

Lock and unlock events are written to the hash-chained audit log (Feature 11) with event types `PERIOD_LOCK` and `PERIOD_UNLOCK`.

---

### Feature 11 — 📋 Hash-Chained Audit Log

**URL:** `/civicrm/civiledger/audit-log`

An immutable, tamper-evident log of all write operations performed by CiviLedger.

**Events recorded:**
- `REPAIR` — chain repair actions
- `CORRECTION` — account corrections (with before/after account IDs)
- `PERIOD_LOCK` / `PERIOD_UNLOCK` — period lock state changes
- `DELETE_DUPLICATE_FI` — duplicate financial item deletions from Audit Trail

**Hash chain:** Each log entry stores a SHA-256 hash of its own fields (`entry_hash`) and the hash of the previous entry (`prev_hash`), forming a linked chain. The **Verify Chain** button re-computes every hash in sequence and reports the first broken link, detecting any tampering or manual edits to the log table.

**Filters:** Date range, event type  
**Pagination:** 50 entries per page  
**Detail column:** JSON snapshot of before/after values, decoded and displayed inline

---

## Permissions

All CiviLedger pages require `administer CiviCRM`. This is enforced in `xml/Menu/civiledger.xml`.

> All tools are admin-only by design. Financial integrity operations should not be available to regular staff.

---

## Database Objects

### Tables created on install

```sql
-- Account correction audit records
civicrm_civiledger_correction_log
  id                    INT UNSIGNED  -- Primary key
  financial_trxn_id     INT UNSIGNED  -- Original transaction corrected
  reversal_trxn_id      INT UNSIGNED  -- Reversal transaction created
  new_trxn_id           INT UNSIGNED  -- Replacement transaction created
  old_from_account_id   INT UNSIGNED
  old_to_account_id     INT UNSIGNED
  new_from_account_id   INT UNSIGNED
  new_to_account_id     INT UNSIGNED
  reason                TEXT          -- Required correction reason
  corrected_by          INT UNSIGNED  -- CiviCRM contact ID of user
  corrected_date        DATETIME

-- Hash-chained immutable event log
civicrm_civiledger_audit_log
  id            INT UNSIGNED
  event_type    VARCHAR(50)   -- REPAIR, CORRECTION, PERIOD_LOCK, PERIOD_UNLOCK, DELETE_DUPLICATE_FI
  entity_type   VARCHAR(50)   -- contribution, financial_trxn, period_lock, etc.
  entity_id     INT UNSIGNED
  actor_id      INT UNSIGNED  -- CiviCRM contact ID
  logged_at     DATETIME
  detail        TEXT          -- JSON snapshot
  entry_hash    VARCHAR(64)   -- SHA-256 of this row
  prev_hash     VARCHAR(64)   -- SHA-256 of previous row

-- Per-action repair detail log
civicrm_civiledger_repair_log
  id              INT UNSIGNED
  contribution_id INT UNSIGNED
  action          VARCHAR(20)   -- fixed, skip, warning, error, info
  message         TEXT
  repaired_by     INT UNSIGNED
  repaired_at     DATETIME

-- Financial period lock records
civicrm_civiledger_period_lock
  id             INT UNSIGNED
  lock_date      DATE          -- Transactions before this date are locked
  lock_reason    TEXT
  locked_by      INT UNSIGNED
  locked_at      DATETIME
  unlock_reason  TEXT
  unlocked_by    INT UNSIGNED
  unlocked_at    DATETIME
  is_active      TINYINT
```

### Tables read (never modified by Integrity Checker / Mismatch Detector)

- `civicrm_contribution`
- `civicrm_line_item`
- `civicrm_financial_item`
- `civicrm_financial_trxn`
- `civicrm_entity_financial_trxn`
- `civicrm_entity_financial_account`
- `civicrm_financial_account`
- `civicrm_financial_type`

### Tables written by Chain Repair

- `civicrm_line_item` — only if missing entirely
- `civicrm_financial_item` — creates missing rows
- `civicrm_financial_trxn` — creates missing rows
- `civicrm_entity_financial_trxn` — creates missing link rows
- `civicrm_civiledger_repair_log` — per-action log entries

### Tables written by Account Correction

- `civicrm_financial_trxn` — inserts two new rows (reversal + correction)
- `civicrm_entity_financial_trxn` — links new rows to contribution
- `civicrm_civiledger_correction_log` — correction record
- `civicrm_civiledger_audit_log` — hash-chained audit event

### Tables written by Audit Trail (duplicate FI deletion)

- `civicrm_entity_financial_trxn` — deletes link rows for the removed FI
- `civicrm_financial_item` — deletes the duplicate row
- `civicrm_civiledger_audit_log` — hash-chained audit event

---

## Recommended Usage Workflow

### First run on an existing site

```
1. Run Integrity Checker   →  note total issues
2. Run Mismatch Detector   →  note mismatches
3. Review Audit Trail      →  pick 2-3 contributions and verify the trail looks correct
4. Run Chain Repair        →  start with 1 contribution, verify with Audit Trail
5. Run Chain Repair batch  →  repair remaining broken records
6. Re-run Integrity Checker →  confirm zero issues
```

### Ongoing maintenance

- Run Integrity Checker monthly or after bulk imports
- Run Mismatch Detector after any payment processor integration changes
- Use Account Correction Tool whenever a wrong income account is discovered on a completed transaction
- Lock prior-year periods via Period Close before year-end reporting
- Review the Audit Log after any bulk operation to verify all events were recorded correctly

---

## Known Limitations

- Chain Repair creates financial items using the **current** income account mapping for a financial type. If the account mapping has changed since the original contribution, the repaired records will reflect the current mapping, not the historical one. Review with an accountant before bulk-repairing old contributions.
- The Mismatch Detector runs on `contribution_status_id = 1` (Completed) only. Pending, Partially Paid, and Cancelled contributions are excluded.
- Account Correction creates two additional `civicrm_financial_trxn` rows per correction. On a heavily corrected system this may add rows to the bookkeeping batch reports; these can be filtered by the `REVERSAL-` and `CORRECTION-` prefixes on `trxn_id`.
- The Financial Dashboard and Tax Mapping charts require an internet connection to load Chart.js from jsDelivr CDN. For air-gapped environments, host `chart.umd.min.js` locally and update the `addScriptUrl()` call in the respective Page class.
- Tax Mapping's non-deductible breakdown correctly handles contributions with no line items and line items with `non_deductible_amount=0` by falling back to the contribution-level field proportionally. However, if both the contribution and line items have mismatched values, the `getIssues()` function should be consulted to resolve the discrepancy before relying on Tax Mapping totals.

---

## Support & Contributing

- **Bug reports:** [GitHub Issues](https://github.com/skvare/com.skvare.civiledger/issues)
- **Documentation:** [GitHub Wiki](https://github.com/skvare/com.skvare.civiledger/wiki)
- **Author:** [Skvare](https://skvare.com) — `info@skvare.com`

Pull requests welcome. Please include a test case or reproduction steps with any bug report.

---

## License

This extension is licensed under [AGPL-3.0](https://www.gnu.org/licenses/agpl-3.0.en.html).

© 2025 Skvare
