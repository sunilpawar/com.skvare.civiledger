{* CiviLedger - Amount Mismatch Detector *}
<div class="civiledger-wrap civiledger-mismatch-page">
  <div class="civiledger-header">
    <h1><i class="crm-i fa-exclamation-triangle"></i> Amount Mismatch Detector</h1>
    <p>Finds contributions where amounts don't balance across line items, financial items, and transactions.</p>
  </div>

  {* Period lock banner *}
  {if $activeLock}
    <div class="crm-container period-lock-banner">
      <i class="crm-i fa-lock"></i>
      <strong>Financial Period Locked</strong> — Repairs for contributions before
      <strong>{$activeLock.lock_date}</strong> are blocked.
      Locked by {$activeLock.locked_by_name|default:'System'} on {$activeLock.locked_at|crmDate}.
      {if $activeLock.lock_reason}<em>Reason: {$activeLock.lock_reason}</em>{/if}
      <a href="{$periodCloseUrl}" class="button small">Manage Lock</a>
    </div>
  {/if}

  <div class="civiledger-section civiledger-filters">
    <form method="get">
      {if $cms_type eq 'WordPress'}
        <input type="hidden" name="page" value="CiviCRM" />
        <input type="hidden" name="q" value="civicrm/civiledger/mismatch-detector" />
      {elseif $cms_type eq 'Joomla'}
        <input type="hidden" name="option" value="com_civicrm" />
        <input type="hidden" name="task" value="civicrm/civiledger/mismatch-detector" />
      {/if}
      <div class="filter-row">
        <label>Date From: <input type="date" name="date_from" value="{$filters.date_from}"></label>
        <label>Date To: <input type="date" name="date_to" value="{$filters.date_to}"></label>
        <button type="submit" class="button">Detect Mismatches</button>
      </div>
    </form>
  </div>

    {* Summary *}
  <div class="integrity-summary {if $summary.total > 0}summary-bad{else}summary-good{/if}">
      {if $summary.total == 0}
        <i class="crm-i fa-check-circle"></i> <strong>All amounts balance!</strong> No mismatches found.
      {else}
        <i class="crm-i fa-exclamation-triangle"></i>
        <strong>{$summary.total} contribution(s) with amount mismatches.</strong>
        &nbsp;|&nbsp; Line item issues: {$summary.line_item_mismatch}
        &nbsp;|&nbsp; Financial item issues: {$summary.financial_item_mismatch}
        &nbsp;|&nbsp; Transaction issues: {$summary.trxn_mismatch}
      {/if}
  </div>

  <div class="civiledger-section">
    <div class="mismatch-legend">
      <strong>The golden rule:</strong>
      <code>contribution.total_amount == SUM(line_items) == SUM(financial_items) == SUM(payments)</code>
    </div>
  </div>
    <div class="description">
      When it marked as 'Manual review required':
      Payment transaction totals differ from contribution amount. This usually indicates a genuine partial payment or refund. Use the Account Correction Tool to investigate.
    </div>

    {if $mismatches}
      <div class="civiledger-section">
        <table class="civiledger-table">
          <thead>
          <tr>
            <th>Contact</th>
            <th>{ts}Date / Time{/ts}</th>
            <th>{ts}Status{/ts}</th>
            <th class="text-right">Contribution Amount</th>
            <th class="text-right">Line Items Sum</th>
            <th class="text-right">Financial Items Sum</th>
            <th class="text-right">Payments Sum</th>
            <th>Actions</th>
            <th>Suggest Fix</th>
          </tr>
          </thead>
          <tbody>
          {foreach from=$mismatches item=row}
            <tr class="row-mismatch">
              <td>
                <a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.contact_name}</a>
              </td>
              <td style="white-space:nowrap;font-family:monospace;font-size:12px">{$row.receive_date}</td>
              <td><span class="contrib-status-badge contrib-status-{$row.contribution_status_id}">{$row.status_label|default:'—'}</span></td>
              <td class="text-left"><strong>{$row.contribution_amount|crmMoney}</strong></td>
              <td class="text-right {if $row.line_item_diff > 0.01}text-red{else}text-green{/if}">
                  {$row.line_item_total|crmMoney}
                  {if $row.line_item_diff > 0.01}<span class="diff-badge">Δ{$row.line_item_diff|crmMoney}</span>{/if}
              </td>
              <td class="text-right {if $row.financial_item_diff > 0.01}text-red{else}text-green{/if}">
                  {$row.financial_item_total|crmMoney}
                  {if $row.financial_item_diff > 0.01}<span class="diff-badge">Δ{$row.financial_item_diff|crmMoney}</span>{/if}
              </td>
              <td class="text-right {if $row.trxn_diff > 0.01}text-red{else}text-green{/if}">
                  {$row.trxn_total|crmMoney}
                  {if $row.trxn_diff > 0.01}<span class="diff-badge">Δ{$row.trxn_diff|crmMoney}</span>{/if}
              </td>
              <td>
                <div style="display:ruby-text;gap:4px;align-items:center;flex-wrap:wrap">
                <button class="button small btn-mismatch-detail" data-cid="{$row.contribution_id}"
                        title="{ts}Expand line-by-line breakdown{/ts}">
                  <i class="crm-i fa-search-plus"></i> {ts}Detail{/ts}
                </button>
                <a href="{crmURL p='civicrm/civiledger/audit-trail' q="reset=1&contribution_id=`$row.contribution_id`"}" class="button small">Audit Trail</a>
                <a href="{crmURL p='civicrm/civiledger/repair-detail' q="reset=1&cid=`$row.contribution_id`"}" class="button small">Repair</a>
                </div>
              </td>
              <td style="min-width:220px">
                {* ── Suggest Fix column ── *}
                {if $row.is_locked}
                  <span class="mismatch-period-locked-badge"
                        title="This contribution falls within the locked financial period (before {$activeLock.lock_date}). Repairs are not allowed.">
                    <i class="crm-i fa-lock"></i> {ts}Period Locked — repairs blocked{/ts}
                  </span>
                {else}
                  {if !empty($row.suggestions.line_items)}
                    {assign var="s" value=$row.suggestions.line_items}
                    {if $s.fixable}
                      <div class="suggest-fix" style="margin-bottom:6px">
                        <button class="button small crm-mismatch-repair" style="display:flex;"
                          data-op="repair_mismatch_line_items"
                          data-cid="{$row.contribution_id}"
                          data-ajax="{$ajaxUrl}"
                          title="{$s.warning}">
                          <i class="crm-i fa-wrench"></i> {$s.label}
                        </button>
                      </div>
                    {else}
                      <div style="margin-bottom:6px;font-size:12px;color:#856404">
                        <i class="crm-i fa-exclamation-triangle"></i>
                        {ts}Line items:{/ts} {$s.warning}
                      </div>
                    {/if}
                  {/if}

                  {if !empty($row.suggestions.financial_items)}
                    {assign var="s" value=$row.suggestions.financial_items}
                    {if $s.fixable}
                      <div class="suggest-fix" style="margin-bottom:6px">
                        <button class="button small crm-mismatch-repair" style="display:flex;"
                          data-op="repair_mismatch_financial_items"
                          data-cid="{$row.contribution_id}"
                          data-ajax="{$ajaxUrl}"
                          title="{$s.warning}">
                          <i class="crm-i fa-wrench"></i> {$s.label}
                        </button>
                      </div>
                    {else}
                      <div style="margin-bottom:6px;font-size:12px;color:#856404">
                        <i class="crm-i fa-exclamation-triangle"></i>
                        {ts}Financial items:{/ts} {$s.warning}
                      </div>
                    {/if}
                  {/if}

                  {if !empty($row.suggestions.trxn)}
                    <div style="font-size:12px;color:#721c24">
                      <i class="crm-i fa-ban"></i>
                      {ts}Payments:{/ts} {$row.suggestions.trxn.warning}
                    </div>
                  {/if}
                {/if}
              </td>
            </tr>
          {/foreach}
          </tbody>
        </table>
      </div>
    {/if}

</div>

{literal}
<style>
.period-lock-banner {
  background: #fff3cd;
  border-left: 4px solid #ffc107;
  color: #856404;
  padding: 10px 14px;
  border-radius: 0 4px 4px 0;
  font-size: 13px;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.period-lock-banner strong { font-weight: 700; }
.period-lock-banner .button.small { margin-left: auto; padding: 2px 10px; font-size: 11px; }
.mismatch-period-locked-badge {
  display: inline-block;
  background: #fff3cd;
  border: 1px solid #ffc107;
  color: #856404;
  font-size: 11px;
  font-weight: 700;
  padding: 4px 8px;
  border-radius: 4px;
}
/* Mismatch detail panel */
.mismatch-detail-row td { padding: 0 !important; background: #f8f9fa; }
.mmd-detail-wrap { padding: 14px 16px; }

.mmd-detail-header {
  display: flex; align-items: center; flex-wrap: wrap; gap: 10px;
  margin-bottom: 12px; padding: 8px 12px;
  background: #fff; border: 1px solid #dee2e6; border-radius: 5px;
  font-size: 13px;
}
.mmd-detail-label { font-weight: 700; color: #212529; margin-right: 4px; }
.mmd-detail-ref   { color: #495057; }
.mmd-detail-sum   { padding: 3px 10px; border-radius: 12px; font-weight: 600; font-size: 12px; }
.mmd-ok  { background: #d4edda; color: #155724; }
.mmd-bad { background: #f8d7da; color: #721c24; }

.mmd-detail-tables { display: flex; gap: 14px; flex-wrap: wrap; }
.mmd-detail-block  { flex: 1; min-width: 260px; }
.mmd-detail-block-title {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .05em; color: #6c757d; margin-bottom: 5px;
}

.mmd-inner-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.mmd-inner-table th {
  background: #e9ecef; padding: 4px 7px; text-align: left;
  font-weight: 600; border-bottom: 1px solid #dee2e6;
}
.mmd-inner-table td { padding: 3px 7px; border-bottom: 1px solid #f0f0f0; }
.mmd-inner-table tfoot td {
  font-weight: 700; border-top: 2px solid #dee2e6;
  background: #f8f9fa; padding: 4px 7px;
}
.mmd-amt  { text-align: right; font-family: monospace; }
.mmd-none { color: #aaa; font-style: italic; text-align: center; padding: 8px; }

.mmd-tag          { font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 3px; }
.mmd-tag-pay      { background: #cfe2ff; color: #084298; }
.mmd-tag-nonpay   { background: #f0f0f0; color: #666; }

.contrib-status-badge {
  display: inline-block;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 10px;
  white-space: nowrap;
  background: #e2e3e5;
  color: #383d41;
}
.contrib-status-1  { background: #d4edda; color: #155724; } /* Completed */
.contrib-status-2  { background: #fff3cd; color: #856404; } /* Pending */
.contrib-status-3  { background: #f8d7da; color: #721c24; } /* Cancelled */
.contrib-status-4  { background: #f8d7da; color: #721c24; } /* Failed */
.contrib-status-5  { background: #cfe2ff; color: #084298; } /* In Progress */
.contrib-status-6  { background: #e2e3e5; color: #383d41; } /* Overdue */
.contrib-status-7  { background: #d1ecf1; color: #0c5460; } /* Refunded */
.contrib-status-8  { background: #fce8d8; color: #7c3c00; } /* Partially paid */
</style>
{/literal}
