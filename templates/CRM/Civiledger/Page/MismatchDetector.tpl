{* CiviLedger - Amount Mismatch Detector *}
<div class="civiledger-wrap">
  <div class="civiledger-header">
    <h1><i class="crm-i fa-exclamation-triangle"></i> Amount Mismatch Detector</h1>
    <p>Finds contributions where amounts don't balance across line items, financial items, and transactions.</p>
  </div>

  <div class="civiledger-section civiledger-filters">
    <form method="get">
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

    {if $mismatches}
      <div class="civiledger-section">
        <table class="civiledger-table">
          <thead>
          <tr>
            <th>Contact</th>
            <th class="text-right">Contribution Amount</th>
            <th class="text-right">Line Items Sum</th>
            <th class="text-right">Financial Items Sum</th>
            <th class="text-right">Payments Sum</th>
            <th>Issues</th>
            <th>Actions</th>
          </tr>
          </thead>
          <tbody>
          {foreach from=$mismatches item=row}
            <tr class="row-mismatch">
              <td>
                <a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.contact_name}</a>
              </td>
              <td class="text-right"><strong>{$row.contribution_amount|crmMoney}</strong></td>
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
                  {foreach from=$row.issues item=issue}
                    <div class="issue-tag">{$issue}</div>
                  {/foreach}
              </td>
              <td>
                <a href="{crmURL p='civicrm/civiledger/audit-trail' q="reset=1&contribution_id=`$row.contribution_id`"}" class="button small">Audit Trail</a>
                <a href="{crmURL p='civicrm/civiledger/repair-detail' q="reset=1&cid=`$row.contribution_id`"}" class="button small">Repair</a>
              </td>
            </tr>
          {/foreach}
          </tbody>
        </table>
      </div>
    {/if}

</div>
