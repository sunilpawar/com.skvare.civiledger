# CiviLedger Copilot Instructions

This extension provides financial audit, integrity checking, and correction tools for CiviCRM 6.0+.

## Installation & Testing Commands

```bash
# Install the extension (from CiviCRM root directory)
cv ext:install com.skvare.civiledger

# Uninstall
cv ext:uninstall com.skvare.civiledger

# Flush caches after code changes (essential)
cv flush
```

**Important:** There is no build system, no `composer.json`, no `package.json`, and no test suite. After any PHP changes, always run `cv flush` to clear CiviCRM's cache. The extension uses Chart.js 4.4.4 from CDN (may need local hosting for offline environments).

## Architecture Overview

### MVC Structure

The extension follows the standard CiviCRM civix pattern:

- **`CRM/Civiledger/BAO/`** — Business logic; pure SQL via `CRM_Core_DAO::executeQuery()`. All financial queries, integrity checks, chain repairs, and corrections live here.
- **`CRM/Civiledger/Page/`** — Page controllers extending `CRM_Core_Page`. Fetch data from BAO, assign to templates. Use `CRM_Utils_Request::retrieve()` for params.
- **`CRM/Civiledger/Form/`** — Filter forms (date ranges, status, accounts) extending `CRM_Core_Form`.
- **`templates/CRM/Civiledger/`** — Smarty v2 templates (one per Page/Form).
- **`js/civiledger.js`** — Frontend: AJAX repair calls, repair log display, correction preview, mismatch buttons. Uses CiviCRM's bundled jQuery (`CRM.$`), no external deps.

### The Financial Chain (Core Model)

All audit logic validates CiviCRM's 5-link financial chain:

```
Contribution → LineItem → FinancialItem → EntityFinancialTrxn → FinancialTrxn
```

- **Integrity Checker** validates all links exist.
- **Mismatch Detector** ensures amounts reconcile across all levels.
- **Chain Repair** creates missing rows; **Repair Detail** shows per-layer totals.
- **Account Correction** inserts reversal + correction pair (never edits originals).

### Custom Tables

Four tables installed via `sql/auto_install.sql`:

- **`civicrm_civiledger_correction_log`** — Audit record per account correction (who/when/why/before/after accounts).
- **`civicrm_civiledger_audit_log`** — Hash-chained immutable log; every write operation appends a row with `entry_hash` (SHA-256) and `prev_hash`. Verified via `/civicrm/civiledger/audit-log?verify=1`.
- **`civicrm_civiledger_repair_log`** — Per-action entries (fixed/skip/warning/error/info) during chain repair.
- **`civicrm_civiledger_period_lock`** — Period lock records; `is_active=1` blocks corrections.

All BAO queries read from core CiviCRM tables (`civicrm_contribution`, `civicrm_line_item`, `civicrm_financial_item`, `civicrm_financial_trxn`, `civicrm_entity_financial_trxn`, `civicrm_financial_account`).

### Routing

Routes defined in `xml/Menu/civiledger.xml`. All under `/civicrm/civiledger/`, require `administer CiviCRM` (except `balancemovement` which requires `access CiviCRM`).

**AJAX endpoint** at `/civicrm/civiledger/ajax` (see `CRM/Civiledger/Page/Ajax.php`):
- `op=repair_contribution` — single contribution chain repair
- `op=search_contributions` — typeahead search
- `op=repair_mismatch_line_items` — regenerate line items
- `op=repair_mismatch_financial_items` — regenerate financial items
- `op=delete_financial_item` — delete duplicate and its `entity_financial_trxn` link

### Key BAO Classes

| Class | Purpose |
|---|---|
| `AuditTrail.php` | `getTrail()` — full chain per contribution; `getTransactions()` — with status/card/processor; `deleteFinancialItem()` — safe deletion in transaction |
| `AuditLog.php` | `record()` — hash-chained entry append; `verifyChain()` — recompute hashes; `getEntries()` / `getTotal()` — pagination |
| `IntegrityChecker.php` | Five-category chain integrity scan |
| `RepairTool.php` | Batch and single contribution repair |
| `MismatchDetector.php` | Line item / financial item / transaction amount reconciliation |
| `MismatchRepair.php` | `repairLineItems()`, `repairFinancialItems()` — called via AJAX |
| `AccountCorrection.php` | Double-entry reversal; checks active period lock |
| `AccountBalance.php` | `getAccountMovements()`, `getAccountSummaryStats()`, `getAccountOptions()` |
| `FinancialDashboard.php` | `getMonthlyTrend()`, `getAccountTypeChart()`, `getCashAndAR()`, `getKPIs()` — Chart.js integration |
| `TaxMapping.php` | `getSummary()`, `getByFinancialType()`, `getMonthlyBreakdown()` — deductible/non-deductible breakdown |
| `PeriodClose.php` | `lockPeriod()`, `unlockPeriod()`, `getActiveLock()`, `getLockHistory()` |
| `Utils.php` | Lookup maps, formatting, logging, URL builders |

## Code Conventions

### SQL Queries

All BAO queries follow these patterns:

- **Parameterized**: Use `CRM_Core_DAO::executeQuery()` with `@variables`.
- **Test exclusion**: `WHERE ... AND c.is_test = 0` always.
- **Broken chain detection**: LEFT JOINs with NULL checks on expected linked records.
- **Safety caps**: Detail result sets limited to `LIMIT 500`.
- **Filter injection**: `buildWhereClause()` / `buildContribWhere()` methods handle dynamic date/status filters.
- **Readable labels**: JOIN `civicrm_option_value` for contribution_status, payment_instrument, accept_creditcard, financial_item_status option groups.
- **Contribution without line items**: TaxMapping and FinancialDashboard queries start from `civicrm_contribution` and LEFT JOIN line items so contributions without line items are never silently excluded.

### Non-Deductible Amount Resolution (TaxMapping)

All three TaxMapping functions use the same three-branch fallback:

1. `li.non_deductible_amount > 0` → use line item value directly
2. All line items have `non_deductible_amount = 0` but `c.non_deductible_amount > 0` → distribute proportionally: `(li.line_total / c.total_amount) * c.non_deductible_amount`
3. No line items → use `c.non_deductible_amount`

This ensures `getByFinancialType()` and `getMonthlyBreakdown()` totals always match `getSummary()`.

### PHP Patterns

- **Request parameters**: `CRM_Utils_Request::retrieve('param_name', 'String', $this)` for POST/GET.
- **Database transactions**: Wrap multi-row writes in `CRM_Core_Transaction`.
- **Logging**: Use `CRM_Core_Error::log()` or `error_log()` for debugging; use `Utils::logAction()` for audit events.
- **Permissions**: Check `CRM_Core_Permission::check('administer CiviCRM')` or `check('access CiviCRM')`.

### Template Patterns

Smarty v2 syntax. Key patterns:

- `{$variable}` for interpolation
- `{foreach}...{/foreach}` for loops
- `{if}...{else}...{/if}` for conditionals
- `{include file='...'}` for partials
- Filters via `{$var|default:'fallback'}` or `{$money|crmMoney}`

### JavaScript

`js/civiledger.js` patterns:

- Uses CiviCRM's bundled jQuery as `CRM.$`.
- AJAX calls to `/civicrm/civiledger/ajax` with `op` parameter.
- Real-time status display with color-coded messages (success/error/warning).
- Modal dialogs for confirmations (e.g., duplicate financial item delete).
- No external dependencies.

## Hooks

Defined in `civiledger.php`:

- `hook_civicrm_navigationMenu` — Adds CiviLedger submenu under Contributions.
- `hook_civicrm_permission` — Declares `access civiledger` permission.
- `hook_civicrm_install` / `hook_civicrm_enable` — civix bootstrap + SQL setup.

## File Organization

```
com.skvare.civiledger/
├── CRM/Civiledger/
│   ├── BAO/              # Business logic (core functionality)
│   ├── Page/             # Page controllers (UI routes)
│   └── Form/             # Filter forms
├── templates/CRM/Civiledger/  # Smarty templates (one per Page/Form)
├── sql/                  # auto_install.sql for custom tables
├── xml/Menu/             # Routing config
├── js/civiledger.js      # Frontend logic
├── css/civiledger.css    # Styling
├── managed/              # Managed entities (scheduled jobs, etc.)
├── civiledger.php        # Main module file (hooks)
├── civiledger.civix.php  # civix bootstrap
├── info.xml              # Extension metadata
└── CLAUDE.md             # Detailed documentation
```

## Key Points for Contributors

1. **Always flush cache** after PHP changes: `cv flush`
2. **Don't modify originals** — Account Correction creates reversal pairs, never edits original transactions.
3. **Test data exclusion** — All queries filter `is_test = 0` to avoid polluting financial reports.
4. **Immutable audit log** — Every write operation appends to `civicrm_civiledger_audit_log` with hash chain; never update/delete existing entries.
5. **Chain integrity** — When fixing broken chains, ensure all five links exist and amounts reconcile across all levels.
6. **Transaction safety** — Multi-row writes (repair, correction) must be wrapped in `CRM_Core_Transaction`.

## External Dependencies

- **Chart.js 4.4.4** — Loaded via CDN (`cdn.jsdelivr.net`) in FinancialDashboard and TaxMapping. For offline environments, host locally and replace CDN URL in `FinancialDashboard.php` and `TaxMapping.php`.
