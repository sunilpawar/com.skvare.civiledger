# CiviLedger — Financial Audit & Integrity Extension

**Extension Key:** `com.skvare.civiledger`  
**Author:** [Skvare](https://skvare.com)  
**License:** AGPL-3.0  
**CiviCRM Compatibility:** 6.0+  
**PHP Compatibility:** 8.1+  

---

## Overview

CiviLedger is a comprehensive financial audit, integrity checking, and correction toolkit for CiviCRM. It fills six critical gaps that CiviCRM core does not address:

| # | Feature | What it does |
|---|---|---|
| 1 | 🔍 Integrity Checker | Detects broken links in the financial data chain |
| 2 | 🛠️ Chain Repair Tool | Auto-rebuilds missing financial records |
| 3 | 📊 Audit Trail UI | Per-contribution money flow drill-down |
| 4 | 💰 Account Balance Dashboard | Live balances per financial account |
| 5 | ⚠️ Amount Mismatch Detector | Flags contributions where amounts don't balance |
| 6 | ✏️ Account Correction Tool | Corrects FROM/TO accounts via proper double-entry reversal |

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

1. Download the latest release zip from [GitHub](  https://github.com/skvare/com.skvare.civiledger)
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

The install script creates one table:

```sql
civicrm_civiledger_correction_log
```

This stores an audit log of every account correction made via Feature 6.

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
| Audit Trail | `/civicrm/civiledger/audit-trail` |
| Account Balance | `/civicrm/civiledger/balance` |
| Mismatch Detector | `/civicrm/civiledger/mismatch-detector` |
| Account Correction | `/civicrm/civiledger/account-correction` |

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

Automatically reconstructs the complete financial chain for broken contributions.

**What it does per contribution:**

1. Verifies line items exist — creates a default one from contribution data if missing
2. Creates missing `civicrm_financial_item` rows, linked to the correct income account via `civicrm_entity_financial_account`
3. Creates missing `civicrm_financial_trxn` if none exists, using the contribution's payment instrument and financial type
4. Creates missing `civicrm_entity_financial_trxn` rows for both:
   - `entity_table = civicrm_contribution`
   - `entity_table = civicrm_financial_item`

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
  Contribution #123  |  ₹1,000  |  Donation  |  Completed
    └── Line Item #45  |  ₹1,000  |  Donation

Layer 2 — ACCOUNTING
  Financial Item #67  |  ₹1,000  |  Income Account: Donation Revenue
    └── entity_financial_trxn → trxn #89  ✓

Layer 3 — MONEY MOVEMENT
  Financial Trxn #89
    FROM: Accounts Receivable  →  TO: Stripe Payment Processor
    ₹1,000  |  2024-03-15  |  is_payment = 1
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
|---|---|---|--------------|-------------|---|---|
| Donation Revenue | Revenue | 4000 | ₹50,000      | ₹0          | ₹50,000 | 142 |
| Stripe Processor | Asset | 1200 | ₹48,500      | ₹1,500      | ₹47,000 | 142 |
| Accounts Receivable | Asset | 1100 | ₹1,500       | ₹50,000     | -₹48,500 | 142 |

**Drill-down:** Click **View Movements** on any account to see every individual transaction credit/debit of that account — with date, direction, amount, contact, and contribution link.

**Date filter** allows reporting for any custom period.

---

### Feature 5 — ⚠️ Amount Mismatch Detector

**URL:** `/civicrm/civiledger/mismatch-detector`

Enforces the CiviCRM financial golden rule:

```
contribution.total_amount
  == SUM(civicrm_line_item.line_total)
  == SUM(civicrm_financial_item.amount)
  == SUM(civicrm_financial_trxn.total_amount WHERE is_payment=1)
```

Any contribution where these four sums disagree by more than `₹0.01` is flagged.

**Three types of mismatch detected:**

| Type | Cause |
|---|---|
| Line item mismatch | Webform or API created contribution without correct line items |
| Financial item mismatch | Financial items were partially created or edited manually |
| Transaction mismatch | Partial payment recorded but contribution marked Completed |

Each mismatch row shows all four amounts side-by-side so you can see exactly where the discrepancy is and how large it is. Links to Audit Trail and Repair tool are provided for each.

---

### Feature 6 — ✏️ Account Correction Tool

**URL:** `/civicrm/civiledger/account-correction`

Allows authorised administrators to correct a wrong `from_financial_account_id` or `to_financial_account_id` on any financial transaction — using **proper double-entry reversal**, not a direct edit.

**Why reversal instead of direct edit?**

Direct editing breaks the audit trail. An accountant reviewing the books would see money appearing in an account with no explanation. The correct accounting approach is:

```
Step 1: Create NEGATIVE reversal transaction on OLD accounts
         FROM: [old from account]  →  TO: [old to account]  Amount: -₹1,000

Step 2: Create NEW positive transaction on CORRECT accounts
         FROM: [correct from account]  →  TO: [correct to account]  Amount: +₹1,000

Step 3: Link both new transactions to the original contribution
Step 4: Write entry to correction log (who, when, why, what changed)
```

**The original transaction is never modified.** The net effect on the ledger is zero for the old accounts and correct for the new accounts.

**Fields you can change:**
- `from_financial_account_id` — e.g. wrong payment processor account
- `to_financial_account_id` — e.g. wrong income category
- Both at the same time

**Required:** A written reason for the correction (mandatory for audit compliance).

**Correction history** is shown on the transaction detail page, listing every correction ever made to that transaction with the who/when/why.

---

## Permissions

CiviLedger adds one CiviCRM permission:

| Permission | Description |
|---|---|
| `administer CiviCRM` | Required for all CiviLedger pages (uses existing core permission) |

> All six tools are admin-only by design. Financial integrity operations should not be available to regular staff.

---

## Database Objects

### Table created on install

```sql
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

### Tables written by Account Correction

- `civicrm_financial_trxn` — inserts two new rows (reversal + correction)
- `civicrm_entity_financial_trxn` — links new rows to contribution
- `civicrm_civiledger_correction_log` — audit record
- `civicrm_log` — CiviCRM native activity log

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

---

## Known Limitations

- Chain Repair creates financial items using the **current** income account mapping for a financial type. If the account mapping has changed since the original contribution, the repaired records will reflect the current mapping, not the historical one. Review with an accountant before bulk-repairing old contributions.
- The Mismatch Detector runs on `contribution_status_id = 1` (Completed) only. Pending, Partially Paid, and Cancelled contributions are excluded.
- Account Correction creates two additional `civicrm_financial_trxn` rows per correction. On a heavily corrected system this may add rows to the bookkeeping batch reports; these can be filtered by the `REVERSAL-` and `CORRECTION-` prefixes on `trxn_id`.

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
