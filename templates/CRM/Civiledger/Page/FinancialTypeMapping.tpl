{* CiviLedger — Financial Type Account Mapping Validator *}
<div class="civiledger-wrap">

  <div class="civiledger-header">
    <h1><i class="crm-i fa-sitemap"></i> {ts}Financial Type Account Mapping{/ts}</h1>
    <p>{ts}Validates that every financial type has the correct account relationships configured. Required relationships: Income Account (→ Revenue), Accounts Receivable (→ Asset), Expense Account (→ Expenses), Cost of Sales (→ Cost of Sales).{/ts}</p>
  </div>

  {* ── Summary cards ──────────────────────────────────────────────────────── *}
  <div class="ftm-summary-row">
    <div class="ftm-card ftm-card-total">
      <div class="ftm-card-num">{$summary.total}</div>
      <div class="ftm-card-label">{ts}Financial Types{/ts}</div>
    </div>
    <div class="ftm-card ftm-card-active">
      <div class="ftm-card-num">{$summary.active}</div>
      <div class="ftm-card-label">{ts}Active{/ts}</div>
    </div>
    <div class="ftm-card ftm-card-inactive">
      <div class="ftm-card-num">{$summary.inactive}</div>
      <div class="ftm-card-label">{ts}Inactive{/ts}</div>
    </div>
    <div class="ftm-card {if $summary.errors > 0}ftm-card-error{else}ftm-card-ok{/if}">
      <div class="ftm-card-num">{$summary.errors}</div>
      <div class="ftm-card-label">{ts}Types with Errors{/ts}</div>
    </div>
    <div class="ftm-card {if $summary.warnings > 0}ftm-card-warn{else}ftm-card-ok{/if}">
      <div class="ftm-card-num">{$summary.warnings}</div>
      <div class="ftm-card-label">{ts}Types with Warnings{/ts}</div>
    </div>
    <div class="ftm-card ftm-card-ok">
      <div class="ftm-card-num">{$summary.ok}</div>
      <div class="ftm-card-label">{ts}Types OK{/ts}</div>
    </div>
  </div>

  {* ── Account type legend ───────────────────────────────────────────────── *}
  <div class="ftm-acct-legend">
    <span class="ftm-legend-title">{ts}Account Type Normal Balances{/ts}</span>
    {foreach from=$accountTypeLegend item=at}
      <span class="ftm-legend-chip {if $at.normal_balance eq 'debit'}ftm-chip-debit{else}ftm-chip-credit{/if}">
        <span class="ftm-chip-name">{$at.label}</span>
        {if $at.normal_balance eq 'debit'}
          <span class="ftm-chip-dir ftm-chip-active" title="{ts}Debit increases{/ts}">
            <i class="crm-i fa-arrow-up"></i> DR
          </span>
          <span class="ftm-chip-dir ftm-chip-dim" title="{ts}Credit decreases{/ts}">
            <i class="crm-i fa-arrow-down"></i> CR
          </span>
        {else}
          <span class="ftm-chip-dir ftm-chip-dim" title="{ts}Debit decreases{/ts}">
            <i class="crm-i fa-arrow-down"></i> DR
          </span>
          <span class="ftm-chip-dir ftm-chip-active" title="{ts}Credit increases{/ts}">
            <i class="crm-i fa-arrow-up"></i> CR
          </span>
        {/if}
      </span>
    {/foreach}
  </div>

  {* ── Filter tabs ─────────────────────────────────────────────────────────── *}
  <div class="ftm-filter-bar">
    <form method="get">
      {if $cms_type eq 'WordPress'}
        <input type="hidden" name="page" value="CiviCRM" />
      {/if}
      <input type="hidden" name="q" value="civicrm/civiledger/financial-type-mapping" />
      <div class="ftm-tabs">
        <button type="submit" name="filter" value="all"
                class="ftm-tab {if $filter eq 'all'}ftm-tab-active{/if}">
          {ts}All{/ts} <span class="ftm-tab-count">{$summary.total}</span>
        </button>
        <button type="submit" name="filter" value="active"
                class="ftm-tab {if $filter eq 'active'}ftm-tab-active{/if}">
          {ts}Active Only{/ts} <span class="ftm-tab-count">{$summary.active}</span>
        </button>
        <button type="submit" name="filter" value="issues"
                class="ftm-tab {if $filter eq 'issues'}ftm-tab-active{/if} {if $summary.errors > 0}ftm-tab-alert{/if}">
          <i class="crm-i fa-exclamation-triangle"></i> {ts}Issues Only{/ts}
          <span class="ftm-tab-count">{$summary.errors + $summary.warnings}</span>
        </button>
        <button type="submit" name="filter" value="inactive"
                class="ftm-tab {if $filter eq 'inactive'}ftm-tab-active{/if}">
          {ts}Inactive{/ts} <span class="ftm-tab-count">{$summary.inactive}</span>
        </button>
      </div>
    </form>
    <div class="ftm-actions">
      <a href="{$ftListUrl}" target="_blank" class="button small">
        <i class="crm-i fa-external-link"></i> {ts}Manage Financial Types{/ts}
      </a>
      <a href="{$acctUrl}" target="_blank" class="button small">
        <i class="crm-i fa-university"></i> {ts}Manage Financial Accounts{/ts}
      </a>
    </div>
  </div>

  {* ── No results ───────────────────────────────────────────────────────────── *}
  {if !$mapping}
    <div class="integrity-summary summary-good">
      <i class="crm-i fa-check-circle"></i>
      <strong>{ts}No issues found{/ts}</strong> — {ts}all financial types have correct account mappings.{/ts}
    </div>
  {/if}

  {* ── Financial type cards ─────────────────────────────────────────────────── *}
  {foreach from=$mapping item=ft}
    <div class="civiledger-section ftm-type-block ftm-status-{$ft.status}" id="ftm-{$ft.id}">

      {* Card header *}
      <div class="ftm-type-header">
        <div class="ftm-type-title">
          <span class="ftm-type-name">{$ft.label}</span>
          {if !$ft.is_active}
            <span class="ftm-badge ftm-badge-inactive">{ts}Inactive{/ts}</span>
          {/if}
          {if $ft.is_deductible}
            <span class="ftm-badge ftm-badge-deductible">{ts}Tax-Deductible{/ts}</span>
          {/if}
          {if $ft.is_reserved}
            <span class="ftm-badge ftm-badge-reserved">{ts}Reserved{/ts}</span>
          {/if}
          <span class="ftm-type-id">id:{$ft.id}</span>
        </div>
        <div class="ftm-type-status">
          {if $ft.status eq 'error'}
            <span class="ftm-status-badge ftm-status-error">
              <i class="crm-i fa-times-circle"></i>
              {$ft.issue_count} {ts}error(s){/ts}
            </span>
          {elseif $ft.status eq 'warning'}
            <span class="ftm-status-badge ftm-status-warning">
              <i class="crm-i fa-exclamation-triangle"></i>
              {$ft.issue_count} {ts}warning(s){/ts}
            </span>
          {else}
            <span class="ftm-status-badge ftm-status-ok">
              <i class="crm-i fa-check-circle"></i> {ts}OK{/ts}
            </span>
          {/if}
          <a href="{$ft.edit_url}" target="_blank" class="button small" style="margin-left:8px">
            <i class="crm-i fa-pencil"></i> {ts}Edit{/ts}
          </a>
        </div>
      </div>

      {* Issues list *}
      {if $ft.issues}
        <div class="ftm-issues">
          {foreach from=$ft.issues item=issue}
            <div class="ftm-issue ftm-issue-{$issue.severity}">
              {if $issue.severity eq 'error'}
                <i class="crm-i fa-times-circle"></i>
              {else}
                <i class="crm-i fa-exclamation-triangle"></i>
              {/if}
              {$issue.message}
              {if $issue.type eq 'missing'}
                <a href="{$ft.edit_url}" target="_blank" class="ftm-fix-link">
                  {ts}Fix in CiviCRM →{/ts}
                </a>
              {/if}
            </div>
          {/foreach}
        </div>
      {/if}

      {* Relationships table *}
      {if $ft.relationships}
        <table class="civiledger-table ftm-rel-table">
          <thead>
            <tr>
              <th>{ts}Relationship{/ts}</th>
              <th>{ts}Required{/ts}</th>
              <th>{ts}Mapped Account{/ts}</th>
              <th>{ts}Account Type{/ts}</th>
              <th>{ts}Expected Type{/ts}</th>
              <th>{ts}Accounting Code{/ts}</th>
              <th>{ts}Status{/ts}</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$ft.relationships item=rel}
              <tr class="ftm-rel-row ftm-rel-{$rel.status}">
                <td>
                  <strong>{$rel.rel_label}</strong>
                  <span class="ftm-rel-id">#{$rel.rel_id}</span>
                </td>
                <td>
                  {if $rel.is_required}
                    <span class="ftm-req-badge">{ts}Required{/ts}</span>
                  {else}
                    <span style="color:#aaa;font-size:11px">{ts}Optional{/ts}</span>
                  {/if}
                </td>
                <td>
                  {if $rel.account_name}
                    <i class="crm-i fa-university"></i> {$rel.account_name}
                    {if !$rel.account_is_active}
                      <span class="ftm-badge ftm-badge-inactive" style="font-size:9px">{ts}Inactive{/ts}</span>
                    {/if}
                  {else}
                    <span class="ftm-missing-account">
                      <i class="crm-i fa-times"></i> {ts}Not configured{/ts}
                    </span>
                  {/if}
                </td>
                <td>
                  {if $rel.account_type_label}
                    <span class="ftm-acct-type ftm-acct-type-{$rel.account_type_id}">
                      {$rel.account_type_label}
                    </span>
                    {if $rel.account_type_code}
                      <code class="ftm-type-code">{$rel.account_type_code}</code>
                    {/if}
                  {else}
                    <span style="color:#aaa">—</span>
                  {/if}
                </td>
                <td>
                  {if $rel.expected_type_label}
                    <span class="ftm-acct-type {if $rel.account_type_id neq $rel.expected_type_id and $rel.account_type_id neq null}ftm-expected-mismatch{/if}">
                      {$rel.expected_type_label}
                    </span>
                  {else}
                    <span style="color:#aaa">{ts}Any{/ts}</span>
                  {/if}
                </td>
                <td>
                  {if $rel.accounting_code}
                    <code class="ftm-type-code">{$rel.accounting_code}</code>
                  {else}
                    <span style="color:#aaa">—</span>
                  {/if}
                </td>
                <td>
                  {if $rel.status eq 'ok'}
                    <span class="ftm-row-status ftm-row-ok">
                      <i class="crm-i fa-check"></i> {ts}OK{/ts}
                    </span>
                  {elseif $rel.status eq 'missing'}
                    <span class="ftm-row-status ftm-row-error">
                      <i class="crm-i fa-times"></i> {ts}Missing{/ts}
                    </span>
                  {elseif $rel.status eq 'error'}
                    <span class="ftm-row-status ftm-row-error">
                      <i class="crm-i fa-times-circle"></i> {ts}Wrong Type{/ts}
                    </span>
                  {else}
                    <span class="ftm-row-status ftm-row-warning">
                      <i class="crm-i fa-exclamation-triangle"></i> {ts}Warning{/ts}
                    </span>
                  {/if}
                </td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      {elseif $ft.is_active}
        <div class="chain-missing rd-badge rd-badge-fail">
          ⚠ {ts}No account relationships configured for this active financial type.{/ts}
        </div>
      {else}
        <div class="rd-badge rd-badge-info" style="display:list-item">
          {ts}No account relationships configured (inactive type).{/ts}
        </div>
      {/if}

    </div>
  {/foreach}

</div>{* .civiledger-wrap *}

{literal}
<style>
/* Summary cards */
.ftm-summary-row {
  display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px;
}
.ftm-card {
  flex: 1; min-width: 110px; background: #fff; border-radius: 8px;
  border: 1px solid #dee2e6; padding: 14px 16px; text-align: center;
  box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.ftm-card-num {
  font-size: 28px; font-weight: 700; line-height: 1;
}
.ftm-card-label {
  font-size: 11px; color: #6c757d; margin-top: 4px; text-transform: uppercase; letter-spacing: .04em;
}
.ftm-card-error .ftm-card-num  { color: #dc3545; }
.ftm-card-warn  .ftm-card-num  { color: #856404; }
.ftm-card-ok    .ftm-card-num  { color: #155724; }
.ftm-card-active .ftm-card-num { color: #0d6efd; }

/* Filter bar */
.ftm-filter-bar {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 10px; margin-bottom: 18px;
}
.ftm-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
.ftm-tab {
  background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;
  padding: 6px 14px; font-size: 13px; cursor: pointer; color: #495057;
}
.ftm-tab:hover { background: #e9ecef; }
.ftm-tab-active { background: #0d6efd; color: #fff; border-color: #0d6efd; }
.ftm-tab-alert  { border-color: #dc3545; color: #dc3545; }
.ftm-tab-alert.ftm-tab-active { background: #dc3545; color: #fff; }
.ftm-tab-count  {
  display: inline-block; background: rgba(0,0,0,.12); border-radius: 10px;
  padding: 0 6px; font-size: 11px; margin-left: 4px;
}
.ftm-actions { display: flex; gap: 8px; }

/* Type blocks */
.ftm-type-block {
  margin-bottom: 16px; border-radius: 6px; overflow: hidden;
}
.ftm-status-error   { border-left: 4px solid #dc3545 !important; }
.ftm-status-warning { border-left: 4px solid #ffc107 !important; }
.ftm-status-ok      { border-left: 4px solid #28a745 !important; }

.ftm-type-header {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 8px; margin-bottom: 10px;
}
.ftm-type-title { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ftm-type-name  { font-size: 15px; font-weight: 700; color: #212529; }
.ftm-type-id    { font-size: 11px; color: #aaa; font-family: monospace; }

/* Status badges */
.ftm-status-badge {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 12px;
}
.ftm-status-badge.ftm-status-error   { background: #f8d7da; color: #721c24; }
.ftm-status-badge.ftm-status-warning { background: #fff3cd; color: #856404; }
.ftm-status-badge.ftm-status-ok      { background: #d4edda; color: #155724; }

/* Small badges */
.ftm-badge {
  display: inline-block; font-size: 9px; font-weight: 700; letter-spacing: .04em;
  padding: 1px 6px; border-radius: 3px; text-transform: uppercase; vertical-align: middle;
}
.ftm-badge-inactive   { background: #dee2e6; color: #495057; }
.ftm-badge-deductible { background: #d1ecf1; color: #0c5460; }
.ftm-badge-reserved   { background: #e2d9f3; color: #432874; }
.ftm-req-badge        { background: #cfe2ff; color: #084298; font-size: 10px; padding: 1px 6px; border-radius: 3px; }

/* Issues */
.ftm-issues { margin-bottom: 10px; }
.ftm-issue  {
  display: flex; align-items: flex-start; gap: 8px;
  font-size: 13px; padding: 6px 10px; border-radius: 4px; margin-bottom: 4px;
}
.ftm-issue-error   { background: #f8d7da; color: #721c24; }
.ftm-issue-warning { background: #fff3cd; color: #856404; }
.ftm-fix-link {
  margin-left: auto; white-space: nowrap; font-size: 12px;
  color: inherit; text-decoration: underline;
}

/* Relationship table rows */
.ftm-rel-table td { vertical-align: middle; }
.ftm-rel-row.ftm-rel-ok      { background: #fff; }
.ftm-rel-row.ftm-rel-error   { background: #fff5f5; }
.ftm-rel-row.ftm-rel-missing { background: #fff5f5; }
.ftm-rel-row.ftm-rel-warning { background: #fffbea; }
.ftm-rel-id      { font-size: 10px; color: #aaa; font-family: monospace; margin-left: 4px; }
.ftm-type-code   { background: #f1f3f5; padding: 1px 5px; border-radius: 3px; font-size: 11px; color: #495057; }

.ftm-missing-account { color: #dc3545; font-size: 12px; font-style: italic; }

.ftm-row-status      { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; }
.ftm-row-ok          { color: #155724; }
.ftm-row-error       { color: #721c24; }
.ftm-row-warning     { color: #856404; }

.ftm-acct-type   { font-size: 12px; font-weight: 600; }
.ftm-acct-type-1 { color: #0c5460; }  /* Asset    */
.ftm-acct-type-2 { color: #432874; }  /* Liability*/
.ftm-acct-type-3 { color: #155724; }  /* Revenue  */
.ftm-acct-type-4 { color: #856404; }  /* COS      */
.ftm-acct-type-5 { color: #721c24; }  /* Expenses */

.ftm-expected-mismatch { color: #dc3545 !important; }

/* Account type legend block */
.ftm-acct-legend {
  display: flex; align-items: center; flex-wrap: wrap; gap: 8px;
  background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px;
  padding: 10px 14px; margin-bottom: 16px;
}
.ftm-legend-title {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .05em; color: #6c757d; margin-right: 4px; white-space: nowrap;
}
.ftm-legend-chip {
  display: inline-flex; align-items: center; gap: 5px;
  border-radius: 5px; padding: 4px 10px; font-size: 11px; font-weight: 600;
  border: 1px solid transparent;
}
.ftm-chip-debit  { background: #dbeafe; color: #1e40af; border-color: #bfdbfe; }
.ftm-chip-credit { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
.ftm-chip-name   { font-weight: 700; }
.ftm-chip-dir    { font-family: monospace; font-size: 10px; white-space: nowrap; }
.ftm-chip-active { opacity: 1; }
.ftm-chip-dim    { opacity: .45; }
</style>
{/literal}
