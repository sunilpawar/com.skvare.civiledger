{* CiviLedger - Integrity Checker *}
<div class="civiledger-wrap">
  <div class="civiledger-header">
    <h1><i class="crm-i fa-search"></i> Financial Integrity Checker</h1>
    <p>Detects broken links in CiviCRM's financial data chain.</p>
  </div>

  {* Filter Form *}
  <div class="civiledger-section civiledger-filters">
    <form method="get">
      <div class="filter-row">
        <label>Date From: <input type="date" name="date_from" value="{$filters.date_from}"></label>
        <label>Date To: <input type="date" name="date_to" value="{$filters.date_to}"></label>
        <label>Status:
          <select name="status_id">
            <option value="">— All —</option>
            {foreach from=$statusOptions key=val item=label}
            <option value="{$val}" {if $filters.contribution_status_id == $val}selected{/if}>{$label}</option>
            {/foreach}
          </select>
        </label>
        <button type="submit" class="button">Run Check</button>
      </div>
    </form>
  </div>

  {* Summary Banner *}
  <div class="integrity-summary {if $totalIssues > 0}summary-bad{else}summary-good{/if}">
    {if $totalIssues == 0}
      <i class="crm-i fa-check-circle"></i> <strong>All clear!</strong> No financial integrity issues found.
    {else}
      <i class="crm-i fa-exclamation-triangle"></i>
      <strong>{$totalIssues} issue(s) found.</strong>
      <a href="{$repairUrl}" class="button small">Go to Repair Tool →</a>
    {/if}
  </div>

  {* Issue 1: Missing Contribution → Trxn Link *}
  <div class="civiledger-section">
    <h2>
      <span class="issue-count {if $results.summary.missing_contribution_trxn_link > 0}count-bad{else}count-ok{/if}">
        {$results.summary.missing_contribution_trxn_link}
      </span>
      Contributions missing payment link
      <span class="help-tip" title="These contributions have no row in civicrm_entity_financial_trxn. CiviCRM cannot show payment status for them.">?</span>
    </h2>
    {if $results.missing_contribution_trxn_link}
    <table class="civiledger-table">
      <thead>
        <tr><th>ID</th><th>Contact</th><th>Amount</th><th>Date</th><th>Financial Type</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        {foreach from=$results.missing_contribution_trxn_link item=row}
        <tr>
          <td>#{$row.contribution_id}</td>
          <td>{$row.contact_name}</td>
          <td class="text-right">{$row.total_amount|crmMoney}</td>
          <td>{$row.receive_date|crmDate}</td>
          <td>{$row.financial_type}</td>
          <td><span class="status-badge">{$row.contribution_status_id}</span></td>
          <td>
            <a href="{$repairUrl}?action=repair_one&cid={$row.contribution_id}" class="button small crm-button-type-delete"
               onclick="return confirm('Repair financial chain for contribution #{$row.contribution_id}?')">Repair</a>
            <a href="{$auditUrl}?cid={$row.contribution_id}" class="button small">Audit Trail</a>
          </td>
        </tr>
        {/foreach}
      </tbody>
    </table>
    {/if}
  </div>

  {* Issue 2: Missing Financial Items *}
  <div class="civiledger-section">
    <h2>
      <span class="issue-count {if $results.summary.missing_financial_item > 0}count-bad{else}count-ok{/if}">
        {$results.summary.missing_financial_item}
      </span>
      Line items missing financial_item records
    </h2>
    {if $results.missing_financial_item}
    <table class="civiledger-table">
      <thead>
        <tr><th>Contribution</th><th>Line Item ID</th><th>Amount</th><th>Financial Type</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
        {foreach from=$results.missing_financial_item item=row}
        <tr>
          <td>#{$row.contribution_id}</td>
          <td>#{$row.line_item_id}</td>
          <td class="text-right">{$row.line_total|crmMoney}</td>
          <td>{$row.financial_type}</td>
          <td>{$row.receive_date|crmDate}</td>
          <td>
            <a href="{$repairUrl}?action=repair_one&cid={$row.contribution_id}" class="button small crm-button-type-delete"
               onclick="return confirm('Repair?')">Repair</a>
          </td>
        </tr>
        {/foreach}
      </tbody>
    </table>
    {/if}
  </div>

  {* Issue 3: Missing Financial Item → Trxn Link (most critical) *}
  <div class="civiledger-section">
    <h2>
      <span class="issue-count {if $results.summary.missing_financial_item_trxn_link > 0}count-bad{else}count-ok{/if}">
        {$results.summary.missing_financial_item_trxn_link}
      </span>
      Financial items not linked to any transaction <span class="badge-critical">Critical</span>
      <span class="help-tip" title="Cash exists. Accounting entries exist. But they are not linked. CiviCRM cannot explain why this money exists.">?</span>
    </h2>
    {if $results.missing_financial_item_trxn_link}
    <table class="civiledger-table">
      <thead>
        <tr><th>Financial Item ID</th><th>Contact</th><th>Amount</th><th>Account</th><th>Date</th><th>Contribution</th><th>Actions</th></tr>
      </thead>
      <tbody>
        {foreach from=$results.missing_financial_item_trxn_link item=row}
        <tr class="row-critical">
          <td>#{$row.financial_item_id}</td>
          <td>{$row.contact_name}</td>
          <td class="text-right">{$row.amount|crmMoney}</td>
          <td>{$row.financial_account}</td>
          <td>{$row.transaction_date|crmDate}</td>
          <td>{if $row.contribution_id}<a href="{$auditUrl}?cid={$row.contribution_id}">#{$row.contribution_id}</a>{else}—{/if}</td>
          <td>
            {if $row.contribution_id}
            <a href="{$repairUrl}?action=repair_one&cid={$row.contribution_id}" class="button small crm-button-type-delete"
               onclick="return confirm('Repair?')">Repair</a>
            {/if}
          </td>
        </tr>
        {/foreach}
      </tbody>
    </table>
    {/if}
  </div>

  {* Issue 4: Missing Line Items *}
  <div class="civiledger-section">
    <h2>
      <span class="issue-count {if $results.summary.missing_line_items > 0}count-bad{else}count-ok{/if}">
        {$results.summary.missing_line_items}
      </span>
      Contributions with no line items
    </h2>
    {if $results.missing_line_items}
    <table class="civiledger-table">
      <thead><tr><th>ID</th><th>Amount</th><th>Financial Type</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        {foreach from=$results.missing_line_items item=row}
        <tr>
          <td>#{$row.contribution_id}</td>
          <td class="text-right">{$row.total_amount|crmMoney}</td>
          <td>{$row.financial_type}</td>
          <td>{$row.receive_date|crmDate}</td>
          <td><a href="{$repairUrl}?action=repair_one&cid={$row.contribution_id}" class="button small crm-button-type-delete" onclick="return confirm('Repair?')">Repair</a></td>
        </tr>
        {/foreach}
      </tbody>
    </table>
    {/if}
  </div>

</div>
