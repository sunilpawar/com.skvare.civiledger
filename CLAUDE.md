# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Extension Does

CiviLedger (`com.skvare.civiledger`) is a CiviCRM 6.0+ extension that provides financial audit and correction tools. It addresses eleven features:

1. **Integrity Checker** — detects broken financial chains across CiviCRM financial tables
2. **Chain Repair** — auto-rebuilds missing financial records; Repair Detail page shows per-layer amount totals
3. **Audit Trail** — per-contribution drill-down of the full financial chain; includes duplicate financial item detection and deletion, transaction status labels, card/processor info display, and layer sum badges
4. **Account Balance Dashboard** — live balances per financial account
5. **Account Balance Movement** — per-account transaction drill-down with date filters
6. **Mismatch Detector** — flags contributions where amounts don't reconcile; one-click suggest-fix buttons for line item and financial item mismatches
7. **Account Correction** — corrects FROM/TO accounts via double-entry reversal; blocked when transaction falls within a locked period
8. **Financial Dashboard** — Chart.js 4.x charts: monthly trend (line), credits/debits by account type (grouped bar), cash/AR/revenue/expenses (doughnut)
9. **Tax Mapping** — deductible vs. non-deductible breakdown by financial type with 12-month bar chart; uses proportional fallback for contributions with no line items or zero line-item non_deductible_amount
10. **Period Close / Lock** — locks a financial period by date; Account Correction checks the active lock before allowing corrections
11. **Hash-Chained Audit Log** — immutable, tamper-evident log (SHA-256 chain) of all write operations (REPAIR, CORRECTION, PERIOD_LOCK, PERIOD_UNLOCK, DELETE_DUPLICATE_FI); verifiable via `/civicrm/civiledger/audit-log?verify=1`

## Installation & Development Commands

Install via CiviCRM CLI:
```bash
cv ext:install com.skvare.civiledger
cv ext:uninstall com.skvare.civiledger
cv flush   # flush caches after code changes
```

Or via the CiviCRM UI: **Administer → System Settings → Extensions → Add New**.

There are no build steps, no `composer.json`, no `package.json`, and no test suite.

## Architecture

### MVC Pattern

The extension follows the standard CiviCRM civix MVC structure:

- **`CRM/Civiledger/BAO/`** — Business logic objects; pure SQL via `CRM_Core_DAO::executeQuery()`. This is where all financial queries, integrity checks, repairs, and corrections live.
- **`CRM/Civiledger/Page/`** — Page controllers extending `CRM_Core_Page`. Each page fetches data from its BAO and assigns it to Smarty templates. Request params via `CRM_Utils_Request::retrieve()`.
- **`CRM/Civiledger/Form/`** — Filter forms (date ranges, status, account selectors) extending `CRM_Core_Form`.
- **`templates/CRM/Civiledger/`** — Smarty v2 templates, one per Page and Form.

### The Financial Chain Model

The core model this extension audits is CiviCRM's 5-link financial chain:
```
Contribution → LineItem → FinancialItem → EntityFinancialTrxn → FinancialTrxn
```
The Integrity Checker validates all links exist; the Mismatch Detector validates amounts reconcile across all levels; Chain Repair creates missing rows; Account Correction inserts a reversal + correction trxn pair rather than editing originals.

### Routing

Routes are defined in `xml/Menu/civiledger.xml`. All routes are under `/civicrm/civiledger/` and require `administer CiviCRM` (except `balancemovement` which requires `access CiviCRM`).

**AJAX endpoint** at `/civicrm/civiledger/ajax` handles these `op` values (see `CRM/Civiledger/Page/Ajax.php`):
- `op=repair_contribution` — single contribution chain repair
- `op=search_contributions` — typeahead search for contributions
- `op=repair_mismatch_line_items` — regenerate line items for a mismatched contribution
- `op=repair_mismatch_financial_items` — regenerate financial items for a mismatched contribution
- `op=delete_financial_item` — delete a duplicate financial item and its `civicrm_entity_financial_trxn` link

### Database

Four custom tables are installed via `sql/auto_install.sql`:

- **`civicrm_civiledger_correction_log`** — audit record for each account correction (who/when/why/before/after account IDs)
- **`civicrm_civiledger_audit_log`** — hash-chained immutable log; every CiviLedger write operation appends a row with `entry_hash` (SHA-256 of its own fields) and `prev_hash` (hash of previous row); verifiable by `BAO/AuditLog::verifyChain()`
- **`civicrm_civiledger_repair_log`** — per-action entries (fixed/skip/warning/error/info) written during chain repair
- **`civicrm_civiledger_period_lock`** — one row per lock/unlock cycle; `is_active=1` means the lock is currently in force

All BAO queries read from core CiviCRM financial tables (`civicrm_contribution`, `civicrm_line_item`, `civicrm_financial_item`, `civicrm_financial_trxn`, `civicrm_entity_financial_trxn`, `civicrm_financial_account`). Chain Repair and Account Correction write back to these tables.

### BAO Classes

| File | Key responsibilities |
|---|---|
| `AuditTrail.php` | `getTrail()` — full chain per contribution; `getTransactions()` — transactions with status/card/processor info; `deleteFinancialItem()` — safe duplicate FI deletion inside a `CRM_Core_Transaction` |
| `AuditLog.php` | `record()` — append a hash-chained entry; `verifyChain()` — re-compute every hash and return first broken link; `getEntries()` / `getTotal()` — paginated retrieval |
| `TaxMapping.php` | `getSummary()`, `getByFinancialType()`, `getMonthlyBreakdown()` — all start from `civicrm_contribution` (LEFT JOIN line items) with proportional fallback for non_deductible_amount |
| `PeriodClose.php` | `lockPeriod()`, `unlockPeriod()`, `getActiveLock()`, `getLockHistory()` |
| `MismatchDetector.php` | Detects line item / financial item / transaction amount mismatches |
| `MismatchRepair.php` | `repairLineItems()`, `repairFinancialItems()` — called via AJAX |
| `AccountBalance.php` | `getAccountMovements()`, `getAccountSummaryStats()`, `getAccountOptions()` |
| `FinancialDashboard.php` | `getMonthlyTrend()`, `getAccountTypeChart()`, `getCashAndAR()`, `getKPIs()` |
| `RepairTool.php` | Batch and single contribution chain repair |
| `AccountCorrection.php` | Double-entry reversal correction; checks active period lock |
| `IntegrityChecker.php` | Five-category chain integrity scan |
| `Utils.php` | Shared lookup maps and helpers (see below) |

### Shared Utilities

`CRM/Civiledger/BAO/Utils.php` provides helpers used across BAO classes:
- `getFinancialAccounts()`, `getAccountTypeName()`, `getContributionStatusName()`, `getPaymentInstrumentName()` — lookup maps
- `formatMoney($amount, $currency)` — currency formatting
- `logAction($action, $contributionId, $detail, $userId)` — writes to `civicrm_log`
- `getContributionUrl()`, `getAuditTrailUrl()` — URL builders

### SQL Conventions

All BAO queries follow these patterns:
- Parameterized queries with `CRM_Core_DAO::executeQuery()`
- `WHERE ... AND c.is_test = 0` to exclude test contributions
- LEFT JOINs with NULL checks to detect missing chain links
- Detail result sets use `LIMIT 500` as a safety cap
- `buildWhereClause()` / `buildContribWhere()` methods handle date/status filter injection
- `civicrm_option_value` joins for human-readable labels (contribution_status, payment_instrument, accept_creditcard, financial_item_status option groups)
- TaxMapping and FinancialDashboard queries start from `civicrm_contribution` and LEFT JOIN line items so contributions without line items are never silently excluded

### JavaScript

`js/civiledger.js` handles AJAX repair calls, real-time repair log display with color-coded status, correction preview, mismatch repair buttons, and the duplicate financial item delete modal on the Audit Trail page. It assumes CiviCRM's bundled jQuery (`CRM.$`) is available — no external dependencies.

### External Dependencies

- **Chart.js 4.4.4** — loaded via `addScriptUrl()` from `cdn.jsdelivr.net` in `FinancialDashboard.php` and `TaxMapping.php`. Requires internet access. For offline environments, host the file locally and replace the CDN URL.

### Hooks

Defined in `civiledger.php`:
- `hook_civicrm_navigationMenu` — adds CiviLedger submenu under Contributions
- `hook_civicrm_permission` — declares `access civiledger` permission
- `hook_civicrm_install` / `hook_civicrm_enable` — civix bootstrap + SQL setup

### Non-Deductible Amount Resolution (TaxMapping)

When computing deductible/non-deductible amounts, all three TaxMapping functions (`getSummary`, `getByFinancialType`, `getMonthlyBreakdown`) use the same three-branch fallback:

1. `li.non_deductible_amount > 0` → use line item value directly
2. All line items have `non_deductible_amount = 0` but `c.non_deductible_amount > 0` → distribute proportionally: `(li.line_total / c.total_amount) * c.non_deductible_amount`
3. No line items at all → use `c.non_deductible_amount`

This ensures `getByFinancialType` and `getMonthlyBreakdown` totals always match `getSummary`.
