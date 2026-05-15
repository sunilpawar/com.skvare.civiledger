{* CiviLedger - Integrity Checker *}
<div class="civiledger-wrap">
  <div class="civiledger-header">
    <h1><i class="crm-i fa-search"></i> Financial Integrity Checker</h1>
    <p>Detects broken links in CiviCRM's financial data chain.</p>
  </div>

    {* Filter Form *}
  <div class="civiledger-section civiledger-filters">
    <form method="get">
      {if $cms_type eq 'WordPress'}
        <input type="hidden" name="page" value="CiviCRM" />
        <input type="hidden" name="q" value="civicrm/civiledger/integrity-check" />
      {elseif $cms_type eq 'Joomla'}
        <input type="hidden" name="option" value="com_civicrm" />
        <input type="hidden" name="task" value="civicrm/civiledger/integrity-check" />
      {/if}
      <div class="filter-row">
        <label>Date From: <input type="date" name="date_from" value="{$filters.date_from}"></label>
        <label>Date To: <input type="date" name="date_to" value="{$filters.date_to}"></label>
        <label>Status:
          <select name="status_id">
            <option value="">— All —</option>
              {foreach from=$statusOptions key=val item=label}
                <option value="{$val}" {if $filters.status_id == $val}selected{/if}>{$label}</option>
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
        <strong>{$totalIssues} issue(s) found.</strong> Please review the details below.
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
              <td><a target="_blank" href="{crmURL p='civicrm/contact/view/contribution' q="reset=1&action=view&context=contribution&id=`$row.contribution_id`&cid=`$row.contact_id`"}">#{$row.contribution_id}</a></td>
              <td>{$row.contact_name}</td>
              <td class="text-right">{$row.total_amount|crmMoney}</td>
              <td>{$row.receive_date|crmDate}</td>
              <td>{if !empty($row.financial_type)}{$row.financial_type}{/if}</td>
              <td><span class="contrib-status-badge contrib-status-{$row.contribution_status_id}">{$row.status_label|default:'—'}</span></td>
              <td>
                <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap">
                <a target="_blank" href="{crmURL p='civicrm/civiledger/audit-trail' q="reset=1&contribution_id=`$row.contribution_id`"}" class="button small">Audit Trail</a>
                <a target="_blank" href="{crmURL p='civicrm/civiledger/repair-detail' q="operation=repair_one&cid=`$row.contribution_id`"}" class="button small crm-button-type-delete">Repair</a>
                </div>
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
      {if $results.missing_financial_items}
        <table class="civiledger-table">
          <thead>
          <tr><th>Contribution</th><th>Line Item ID</th><th>Amount</th><th>Financial Type</th><th>Date</th><th>Actions</th></tr>
          </thead>
          <tbody>
          {foreach from=$results.missing_financial_items item=row}
            <tr>
              <td><a target="_blank" href="{crmURL p='civicrm/contact/view/contribution' q="reset=1&action=view&context=contribution&id=`$row.contribution_id`&cid=`$row.contact_id`"}">#{$row.contribution_id}</a></td>
              <td>#{$row.line_item_id}</td>
              <td class="text-right">{$row.line_total|crmMoney}</td>
              <td>{if !empty($row.financial_type)}{$row.financial_type}{/if}</td>
              <td>{$row.receive_date|crmDate}</td>
              <td>
                <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap">
                <a target="_blank" href="{crmURL p='civicrm/civiledger/audit-trail' q="reset=1&contribution_id=`$row.contribution_id`"}" class="button small">Audit Trail</a>
                <a target="_blank" href="{crmURL p='civicrm/civiledger/repair-detail' q="operation=repair_one&cid=`$row.contribution_id`"}" class="button small crm-button-type-delete">Repair</a>
                </div>
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
          <tr><th>Financial Item ID</th><th>Contact</th><th>Amount</th><th>Account</th><th>Date</th><th>Contribution</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
          {foreach from=$results.missing_financial_item_trxn_link item=row}
            <tr class="row-critical">
              <td>#{$row.financial_item_id}</td>
              <td><a target="_blank" href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.contact_name}</a></td>
              <td class="text-right">{if !empty($row.amount)}{$row.amount|crmMoney}{/if}</td>
              <td>{if !empty($row.financial_account)}{$row.financial_account}{/if}</td>
              <td>{if !empty($row.transaction_date)}{$row.transaction_date|crmDate}{/if}</td>
              <td>{if $row.contribution_id}<a target="_blank" href="{crmURL p='civicrm/contact/view/contribution' q="reset=1&action=view&context=contribution&id=`$row.contribution_id`&cid=`$row.contact_id`"}">#{$row.contribution_id}</a>{else}—{/if}</td>
              <td><span class="contrib-status-badge contrib-status-{$row.contribution_status_id}">{$row.status_label|default:'—'}</span></td>
              <td>
                  {if $row.contribution_id}
                    <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap">
                    <a target="_blank" href="{crmURL p='civicrm/civiledger/audit-trail' q="reset=1&contribution_id=`$row.contribution_id`"}" class="button small">Audit Trail</a>
                    <a target="_blank" href="{crmURL p='civicrm/civiledger/repair-detail' q="operation=repair_one&cid=`$row.contribution_id`"}" class="button small crm-button-type-delete">Repair</a>
                    </div>
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
          <thead><tr><th>Contribution ID</th><th>Amount</th><th>Financial Type</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          {foreach from=$results.missing_line_items item=row}
            <tr>
              <td><a target="_blank" href="{crmURL p='civicrm/contact/view/contribution' q="reset=1&action=view&context=contribution&id=`$row.contribution_id`&cid=`$row.contact_id`"}">#{$row.contribution_id}</a></td>
              <td class="text-right">{$row.total_amount|crmMoney}</td>
              <td>{if !empty($row.financial_type)}{$row.financial_type}{/if}</td>
              <td>{$row.receive_date|crmDate}</td>
              <td><span class="contrib-status-badge contrib-status-{$row.contribution_status_id}">{$row.status_label|default:'—'}</span></td>
              <td>
                <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap">
                <a target="_blank" href="{crmURL p='civicrm/civiledger/audit-trail' q="reset=1&contribution_id=`$row.contribution_id`"}" class="button small">Audit Trail</a>
                <a target="_blank" href="{crmURL p='civicrm/civiledger/repair-detail' q="operation=repair_one&cid=`$row.contribution_id`"}" class="button small crm-button-type-delete">Repair</a>
                </div>
              </td>
            </tr>
          {/foreach}
          </tbody>
        </table>
      {/if}
  </div>

</div>

<style>
{literal}
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
.contrib-status-1 { background: #d4edda; color: #155724; } /* Completed */
.contrib-status-2 { background: #fff3cd; color: #856404; } /* Pending */
.contrib-status-3 { background: #f8d7da; color: #721c24; } /* Cancelled */
.contrib-status-4 { background: #f8d7da; color: #721c24; } /* Failed */
.contrib-status-5 { background: #cfe2ff; color: #084298; } /* In Progress */
.contrib-status-6 { background: #e2e3e5; color: #383d41; } /* Overdue */
.contrib-status-7 { background: #d1ecf1; color: #0c5460; } /* Refunded */
.contrib-status-8 { background: #fce8d8; color: #7c3c00; } /* Partially paid */
{/literal}
</style>
