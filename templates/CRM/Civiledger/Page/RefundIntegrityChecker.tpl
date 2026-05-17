{* CiviLedger - Refund / Reversal Integrity Checker *}
<div class="civiledger-wrap">
  <div class="civiledger-header">
    <h1><i class="crm-i fa-undo"></i> Refund / Reversal Integrity Check</h1>
    <p>Finds refund anomalies: missing reversal transactions, amount mismatches, and negative transactions posted against non-refunded contributions.</p>
  </div>

  {* Filters *}
  <div class="civiledger-section civiledger-filters">
    <form method="get">
      {if $cms_type eq 'WordPress'}
        <input type="hidden" name="page" value="CiviCRM" />
        <input type="hidden" name="q" value="civicrm/civiledger/refund-integrity" />
      {elseif $cms_type eq 'Joomla'}
        <input type="hidden" name="option" value="com_civicrm" />
        <input type="hidden" name="task" value="civicrm/civiledger/refund-integrity" />
      {/if}
      <div class="filter-row">
        <label>Contribution Date From: <input type="date" name="date_from" value="{$filters.date_from}"></label>
        <label>Contribution Date To: <input type="date" name="date_to" value="{$filters.date_to}"></label>
        <label>Financial Type:
          <select name="financial_type_id">
            <option value="">— All —</option>
            {foreach from=$financialTypes key=val item=label}
              <option value="{$val}" {if $filters.financial_type_id == $val}selected{/if}>{$label}</option>
            {/foreach}
          </select>
        </label>
        <button type="submit" class="button">Scan</button>
      </div>
    </form>
  </div>

  {* Summary Banner *}
  <div class="integrity-summary {if $summary.total > 0}summary-bad{else}summary-good{/if}">
    {if $summary.total == 0}
      <i class="crm-i fa-check-circle"></i> <strong>All clear!</strong> No refund integrity issues found.
    {else}
      <i class="crm-i fa-exclamation-triangle"></i>
      <strong>{$summary.total} issue(s)</strong> across refunded contributions &nbsp;|&nbsp;
      <span class="rhm-badge rhm-badge-red">{$summary.no_reversal} No Reversal</span>
      <span class="rhm-badge rhm-badge-orange">{$summary.amount_mismatch} Amount Mismatch</span>
      <span class="rhm-badge rhm-badge-yellow">{$summary.orphaned_reversal} Orphaned Reversal</span>
    {/if}
  </div>

  {* ── Issue 1: No Reversal Transaction ── *}
  <div class="civiledger-section">
    <h2 class="rhm-section-head">
      <button class="rhm-toggle button small" data-target="ri-no-reversal-body" style="margin-right:8px">
        <span class="rhm-caret">▼</span>
      </button>
      <span class="issue-count {if $summary.no_reversal > 0}count-bad{else}count-ok{/if}">{$summary.no_reversal}</span>
      No Reversal Transaction
      {if $summary.no_reversal > 0}
        <span class="rhm-badge rhm-badge-red" style="margin-left:8px">{$totals.no_reversal|crmMoney} at risk</span>
      {/if}
      <span class="help-tip" title="Contribution is marked Refunded (status=7) but has no negative financial transaction linked. The refund was never recorded in the financial ledger.">?</span>
    </h2>
    <div id="ri-no-reversal-body">
      {if $results.no_reversal}
        <p class="rhm-chart-note" style="margin-bottom:10px">
          These contributions are flagged as Refunded in CiviCRM but have no corresponding negative financial transaction.
          The ledger still shows a positive balance. Investigate whether the refund was processed outside CiviCRM or the status was set manually.
        </p>
        <table class="civiledger-table">
          <thead>
            <tr>
              <th>Contribution #</th>
              <th>Contact</th>
              <th>Financial Type</th>
              <th>Payment Method</th>
              <th>Trxn ID</th>
              <th class="text-right">Amount</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$results.no_reversal item=row}
              <tr class="row-critical">
                <td><a target="_blank" href="{$row.contribution_url}">#{$row.contribution_id}</a></td>
                <td><a target="_blank" href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.contact_name}</a></td>
                <td>{$row.financial_type_name|default:'—'}</td>
                <td>{$row.payment_instrument|default:'—'}</td>
                <td>{$row.transaction_id|default:'—'}</td>
                <td class="text-right text-red"><strong>{$row.total_amount|crmMoney:$row.currency}</strong></td>
                <td>{$row.receive_date|crmDate}</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      {else}
        <p class="rhm-empty"><i class="crm-i fa-check-circle"></i> All refunded contributions have a reversal transaction.</p>
      {/if}
    </div>
  </div>

  {* ── Issue 2: Amount Mismatch ── *}
  <div class="civiledger-section">
    <h2 class="rhm-section-head">
      <button class="rhm-toggle button small" data-target="ri-mismatch-body" style="margin-right:8px">
        <span class="rhm-caret">▼</span>
      </button>
      <span class="issue-count {if $summary.amount_mismatch > 0}count-bad{else}count-ok{/if}">{$summary.amount_mismatch}</span>
      Reversal Amount Mismatch — partial or over-refund
      {if $summary.amount_mismatch > 0}
        <span class="rhm-badge rhm-badge-orange" style="margin-left:8px">{$totals.amount_mismatch|crmMoney} total gap</span>
      {/if}
      <span class="help-tip" title="A reversal transaction exists but its total doesn't match the original contribution amount. Positive gap = partially refunded. Negative gap = over-refunded.">?</span>
    </h2>
    <div id="ri-mismatch-body">
      {if $results.amount_mismatch}
        <p class="rhm-chart-note" style="margin-bottom:10px">
          A <strong>positive gap</strong> means the refund was less than the original (partial refund — verify if intentional).
          A <strong>negative gap</strong> means more was refunded than was originally charged (over-refund — likely a data entry error).
        </p>
        <table class="civiledger-table">
          <thead>
            <tr>
              <th>Contribution #</th>
              <th>Contact</th>
              <th>Financial Type</th>
              <th>Trxn ID</th>
              <th class="text-right">Original</th>
              <th class="text-right">Total Refunded</th>
              <th class="text-right">Gap</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$results.amount_mismatch item=row}
              <tr class="{if $row.gap_amount < 0}row-critical{else}row-mismatch{/if}">
                <td><a target="_blank" href="{$row.contribution_url}">#{$row.contribution_id}</a></td>
                <td><a target="_blank" href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.contact_name}</a></td>
                <td>{$row.financial_type_name|default:'—'}</td>
                <td>{$row.transaction_id|default:'—'}</td>
                <td class="text-right">{$row.original_amount|crmMoney:$row.currency}</td>
                <td class="text-right">{$row.refund_amount|crmMoney:$row.currency}</td>
                <td class="text-right">
                  {if $row.gap_amount < 0}
                    <span style="color:#721c24;font-weight:700">▲ {$row.abs_gap_amount|crmMoney:$row.currency} over-refunded</span>
                  {else}
                    <span style="color:#856404;font-weight:700">▼ {$row.abs_gap_amount|crmMoney:$row.currency} under-refunded</span>
                  {/if}
                </td>
                <td>{$row.receive_date|crmDate}</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      {else}
        <p class="rhm-empty"><i class="crm-i fa-check-circle"></i> All reversal amounts match their original contributions.</p>
      {/if}
    </div>
  </div>

  {* ── Issue 3: Orphaned Reversal ── *}
  <div class="civiledger-section">
    <h2 class="rhm-section-head">
      <button class="rhm-toggle button small" data-target="ri-orphaned-body" style="margin-right:8px">
        <span class="rhm-caret">▼</span>
      </button>
      <span class="issue-count {if $summary.orphaned_reversal > 0}count-bad{else}count-ok{/if}">{$summary.orphaned_reversal}</span>
      Orphaned Reversal — negative transaction without Refunded status
      {if $summary.orphaned_reversal > 0}
        <span class="rhm-badge rhm-badge-yellow" style="margin-left:8px">{$totals.orphaned_reversal|crmMoney} reversed</span>
      {/if}
      <span class="help-tip" title="A negative financial transaction exists linked to this contribution, but the contribution status is not Refunded. The ledger shows a reversal that CiviCRM doesn't acknowledge.">?</span>
    </h2>
    <div id="ri-orphaned-body">
      {if $results.orphaned_reversal}
        <p class="rhm-chart-note" style="margin-bottom:10px">
          These contributions have a negative financial transaction in the ledger (reversal was posted) but the contribution status was never updated to Refunded.
          The donor record shows the original payment as still successful, creating a reporting inconsistency.
        </p>
        <table class="civiledger-table">
          <thead>
            <tr>
              <th>Contribution #</th>
              <th>Contact</th>
              <th>Financial Type</th>
              <th>Current Status</th>
              <th class="text-right">Original Amount</th>
              <th class="text-right">Total Reversed</th>
              <th class="text-right">Reversal Trxns</th>
              <th>Latest Reversal</th>
              <th>Contribution Date</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$results.orphaned_reversal item=row}
              <tr class="row-mismatch">
                <td><a target="_blank" href="{$row.contribution_url}">#{$row.contribution_id}</a></td>
                <td><a target="_blank" href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.contact_name}</a></td>
                <td>{$row.financial_type_name|default:'—'}</td>
                <td><span class="contrib-status-badge contrib-status-{$row.contribution_status_id}">{$row.status_label|default:'—'}</span></td>
                <td class="text-right">{$row.original_amount|crmMoney:$row.currency}</td>
                <td class="text-right text-red">{$row.total_reversed|crmMoney:$row.currency}</td>
                <td class="text-right">{$row.reversal_count}</td>
                <td>{$row.latest_reversal_date|crmDate}</td>
                <td>{$row.receive_date|crmDate}</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      {else}
        <p class="rhm-empty"><i class="crm-i fa-check-circle"></i> No orphaned reversal transactions found.</p>
      {/if}
    </div>
  </div>

</div>

{literal}
<script>
document.querySelectorAll('.rhm-toggle').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var target = document.getElementById(btn.dataset.target);
    if (!target) return;
    var hidden = target.style.display === 'none' || target.style.display === '';
    target.style.display = hidden ? 'block' : 'none';
    btn.querySelector('.rhm-caret').textContent = hidden ? '▲' : '▼';
  });
});
</script>
{/literal}
