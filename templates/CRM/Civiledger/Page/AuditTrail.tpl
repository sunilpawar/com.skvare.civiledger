{* CiviLedger - Audit Trail *}
<div class="civiledger-wrap">
  <div class="civiledger-header">
    <h1><i class="crm-i fa-history"></i> Contribution Audit Trail</h1>
    <p>Full financial chain drill-down for Contribution #{$contributionId}</p>
  </div>

  {if $chain.contribution}
  {assign var=c value=$chain.contribution}

  {* Chain Health Badge *}
  <div class="chain-health {if $chain.health.is_complete}health-ok{else}health-broken{/if}">
    {if $chain.health.is_complete}
      <i class="crm-i fa-check-circle"></i> <strong>Financial chain is complete and balanced.</strong>
    {else}
      <i class="crm-i fa-chain-broken"></i> <strong>Financial chain has issues.</strong>
      {if !$chain.health.has_line_items}<div>⚠ Missing line items</div>{/if}
      {if !$chain.health.has_financial_items}<div>⚠ Missing financial items</div>{/if}
      {if !$chain.health.has_trxns}<div>⚠ Missing financial transactions</div>{/if}
      {if !$chain.health.amounts_match}<div>⚠ Amounts do not balance</div>{/if}
    {/if}
  </div>

  {* Contribution Header *}
  <div class="civiledger-section">
    <h2>Layer 1 — Business Record</h2>
    <table class="civiledger-table detail-table">
      <tr><th>Contact</th><td><a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$c.contact_id`"}">{$c.contact_name}</a></td>
          <th>Status</th><td>{$c.status_label}</td></tr>
      <tr><th>Total Amount</th><td><strong>{$c.total_amount|crmMoney:$c.currency}</strong></td>
          <th>Financial Type</th><td>{$c.financial_type_name}</td></tr>
      <tr><th>Receive Date</th><td>{$c.receive_date|crmDate}</td>
          <th>Processor Trxn ID</th><td class="text-muted">{$c.trxn_id|default:'—'}</td></tr>
      <tr><th>Invoice Number</th><td>{$c.invoice_number|default:'—'}</td>
          <th>Source</th><td>{$c.source|default:'—'}</td></tr>
    </table>
  </div>

  {* Line Items *}
  <div class="civiledger-section">
    <h2>Layer 1 — Line Items (Why the money was paid)</h2>
    {foreach from=$chain.line_items item=li}
    <div class="chain-block">
      <div class="chain-block-header">
        Line Item #{$li.id} — {$li.financial_type_name}
        <span class="amount-badge">{$li.line_total|crmMoney}</span>
      </div>
      <table class="civiledger-table detail-table">
        <tr><th>Label</th><td>{$li.label|default:'—'}</td>
            <th>Qty</th><td>{$li.qty}</td></tr>
        <tr><th>Unit Price</th><td>{$li.unit_price|crmMoney}</td>
            <th>Line Total</th><td><strong>{$li.line_total|crmMoney}</strong></td></tr>
      </table>

      {* Financial Items for this line item *}
      {if $li.financial_items}
      <div class="sub-section">
        <h4>Layer 2 — Financial Items (Where this money belongs)</h4>
        {foreach from=$li.financial_items item=fi}
        <div class="chain-block sub-block">
          <span class="fi-label">Financial Item #{$fi.id}</span>
          <span class="fi-account"><i class="crm-i fa-university"></i> {$fi.account_name}</span>
          <span class="fi-amount">{$fi.amount|crmMoney}</span>
          <span class="fi-status badge-{$fi.status_id}">{$fi.status_label}</span>
        </div>
        {/foreach}
      </div>
      {else}
      <div class="chain-missing">⚠ No financial items found for this line item.</div>
      {/if}
    </div>
    {foreachelse}
    <div class="chain-missing">⚠ No line items found for this contribution.</div>
    {/foreach}
  </div>

  {* Financial Transactions *}
  <div class="civiledger-section">
    <h2>Layer 3 — Financial Transactions (How money moved)</h2>
    {foreach from=$chain.trxns item=trxn}
    <div class="chain-block {if $trxn.total_amount < 0}trxn-reversal{/if}">
      <div class="chain-block-header">
        Trxn #{$trxn.id}
        {if $trxn.total_amount < 0}<span class="badge-reversal">REVERSAL</span>{/if}
        {if $trxn.is_payment}<span class="badge-payment">PAYMENT</span>{/if}
        <span class="amount-badge">{$trxn.total_amount|crmMoney:$trxn.currency}</span>
        {if $trxn.total_amount >= 0}
        <a href="{$correctionUrl}?cid={$contributionId}&trxn_id={$trxn.id}" class="button small float-right">
          <i class="crm-i fa-exchange"></i> Correct Accounts
        </a>
        {/if}
      </div>
      <div class="trxn-flow">
        <div class="trxn-from">
          <span class="account-label">FROM</span>
          <span class="account-name">{$trxn.from_account_name|default:'—'}</span>
        </div>
        <div class="trxn-arrow">→</div>
        <div class="trxn-to">
          <span class="account-label">TO</span>
          <span class="account-name">{$trxn.to_account_name|default:'—'}</span>
        </div>
      </div>
      <table class="civiledger-table detail-table">
        <tr><th>Date</th><td>{$trxn.trxn_date|crmDate}</td>
            <th>Processor Ref</th><td>{$trxn.processor_trxn_id|default:'—'}</td></tr>
      </table>
    </div>
    {foreachelse}
    <div class="chain-missing">⚠ No financial transactions linked to this contribution.</div>
    {/foreach}
  </div>

  {* Audit Log *}
  {if $chain.audit_log}
  <div class="civiledger-section">
    <h2><i class="crm-i fa-history"></i> CiviLedger Change History</h2>
    <table class="civiledger-table">
      <thead>
        <tr><th>Date</th><th>Action</th><th>Details</th><th>Performed By</th></tr>
      </thead>
      <tbody>
        {foreach from=$chain.audit_log item=log}
        <tr>
          <td>{$log.created_date|crmDate}</td>
          <td><span class="action-badge action-{$log.action}">{$log.action|replace:'_':' '}</span></td>
          <td>
            {if $log.action == 'account_correction'}
              FROM: {$log.old_from_name} → {$log.new_from_name}<br>
              TO: {$log.old_to_name} → {$log.new_to_name}
            {else}
              {$log.notes}
            {/if}
          </td>
          <td>{$log.performed_by|default:'System'}</td>
        </tr>
        {/foreach}
      </tbody>
    </table>
  </div>
  {/if}

  {/if}
</div>
