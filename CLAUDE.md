# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Extension Does

CiviLedger (`com.skvare.civiledger`) is a CiviCRM 6.0+ extension that provides financial audit and correction tools. It addresses six features:

1. **Integrity Checker** — detects broken financial chains across CiviCRM financial tables
2. **Chain Repair** — auto-rebuilds missing financial records
3. **Audit Trail** — per-contribution drill-down of the full financial chain
4. **Account Balance Dashboard** — live balances per financial account
5. **Mismatch Detector** — flags contributions where amounts don't reconcile
6. **Account Correction** — corrects FROM/TO accounts via double-entry reversal

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

Routes are defined in `xml/Menu/civiledger.xml`. All routes are under `/civicrm/civiledger/` and require `administer CiviCRM`. The AJAX endpoint at `/civicrm/civiledger/ajax` handles `op=repair_contribution` and `op=search_contributions`.

### Database

The extension installs one custom table via `sql/auto_install.sql`:
- **`civicrm_civiledger_correction_log`** — audit log for account corrections

All BAO queries read from core CiviCRM financial tables (`civicrm_contribution`, `civicrm_line_item`, `civicrm_financial_item`, `civicrm_financial_trxn`, `civicrm_entity_financial_trxn`, `civicrm_financial_account`). Chain Repair and Account Correction write back to these tables.

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
- `buildWhereClause()` methods handle date/status filter injection

### JavaScript

`js/civiledger.js` (340 lines) handles AJAX repair calls, real-time repair log display with color-coded status, and correction preview. It assumes CiviCRM's bundled jQuery is available — no external dependencies.

### Hooks

Defined in `civiledger.php`:
- `hook_civicrm_navigationMenu` — adds CiviLedger submenu under Contributions
- `hook_civicrm_permission` — declares `access civiledger` permission
- `hook_civicrm_install` / `hook_civicrm_enable` — civix bootstrap + SQL setup
