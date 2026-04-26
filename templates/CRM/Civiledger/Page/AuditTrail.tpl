{* CiviLedger - Audit Trail *}
<div class="civiledger-wrap">
  <div class="civiledger-header">
    <h1><i class="crm-i fa-history"></i> Contribution Audit Trail</h1>
    <p>Full financial chain drill-down for Contribution #{$contributionId}</p>
  </div>

    {if !empty($chain.contribution)}
    {assign var=c value=$chain.contribution}

    {* Chain Health Badge *}
  <div class="chain-health {if $chain.health.is_complete}health-ok{else}health-broken{/if}">
      {if $chain.health.is_complete}
    <div class="rd-badge rd-badge-ok" style="display: list-item;"<i class="crm-i fa-check-circle"></i> <strong>Financial chain is complete and balanced.</strong></div>
    {else}
  <i class="crm-i fa-chain-broken"></i> <strong>Financial chain has issues.</strong>
    {if !$chain.health.has_line_items}<div class="rd-badge rd-badge-fail" style="display: list-item;">⚠ Missing line items</div>{/if}
    {if !$chain.health.has_financial_items}<div class="rd-badge rd-badge-fail" style="display: list-item;">⚠ Missing financial items</div>{/if}
    {if !$chain.health.has_trxns}<div class="rd-badge rd-badge-fail" style="display: list-item;">⚠ Missing financial transactions</div>{/if}
    {if !$chain.health.amounts_match}
      {if $chain.health.line_item_diff > 0.01}<div class="rd-badge rd-badge-fail" style="display: list-item;">⚠ Line items do not match contribution total (diff: {$chain.health.line_item_diff|crmMoney})</div>{/if}
      {if $chain.health.financial_item_diff > 0.01}<div class="rd-badge rd-badge-fail" style="display: list-item;">⚠ Financial items do not match contribution total (diff: {$chain.health.financial_item_diff|crmMoney})</div>{/if}
      {if $chain.health.trxn_diff > 0.01}<div class="rd-badge rd-badge-fail" style="display: list-item;">⚠ Payment transactions do not match contribution total (diff: {$chain.health.trxn_diff|crmMoney})</div>{/if}
    {/if}
  <a target="_blank" href="{crmURL p='civicrm/civiledger/repair-detail' q="cid=`$contributionId`"}" class="button small crm-button-type-delete">Check Details</a>
    {/if}
</div>

{* Contribution Header *}
  <div class="civiledger-section">
    <h2>Layer 1 — Business Record</h2>
    <table class="civiledger-table detail-table">
      <tr><th>Contact</th><td><a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$c.contact_id`"}">{$c.contact_name}</a></td>
        <th>Status</th><td>{$c.status_label}</td></tr>
      <tr><th>Total Amount</th><td><strong>{$c.total_amount|crmMoney:$c.currency}</strong> <a target="_blank" href="{crmURL p='civicrm/contact/view/contribution' q="reset=1&action=view&context=contribution&id=`$contributionId`&cid=`$c.contact_id`"}">View Payment</a></td>
        <th>Financial Type</th><td>{$c.financial_type_name}</td></tr>
      <tr><th>Receive Date</th><td>{$c.receive_date|crmDate}</td>
        <th>Processor Trxn ID</th><td class="text-muted">{$c.trxn_id|default:'—'}</td></tr>
      <tr><th>Invoice Number</th><td>{$c.invoice_number|default:'—'}</td>
        <th>Source</th><td>{$c.source|default:'—'}</td></tr>
    </table>
  </div>

{* Line Items *}
  <div class="civiledger-section">
    <h2>Layer 2 — Line Items (Why the money was paid)
      {if $chain.line_items}
        <span class="layer-sum">
          Line Total: <strong>{$chain.line_item_total|crmMoney}</strong>
          &nbsp;·&nbsp;
          FI Total: <strong class="{if $chain.financial_item_total < 0}sum-negative{elseif $chain.financial_item_total != $chain.line_item_total}sum-mismatch{else}sum-ok{/if}">{$chain.financial_item_total|crmMoney}</strong>
        </span>
      {/if}
    </h2>
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
                <h3 class="fi-section-heading">
                  Financial Items (Where this money belongs)
                  <span class="fi-section-sum {if $li.fi_total != $li.line_total}sum-mismatch{else}sum-ok{/if}">
                    {$li.fi_total|crmMoney}
                  </span>
                </h3>
                <div class="fi-grid-header">
                  <span>ID</span>
                  <span>Account</span>
                  <span>Description</span>
                  <span class="text-right">Amount</span>
                  <span>Status</span>
                </div>
                {foreach from=$li.financial_items item=fi}
                  <div class="fi-row {if $fi.amount < 0}fi-negative{/if}">
                    <span class="fi-id">#{$fi.id}</span>
                    <span class="fi-account">
                      <i class="crm-i fa-university"></i> {$fi.account_name}
                      {if $fi.account_type_label}<em class="fi-acct-type">{$fi.account_type_label}</em>{/if}
                    </span>
                    <span class="fi-desc">{$fi.description|default:'—'}</span>
                    <span class="fi-amount">{$fi.amount|crmMoney}</span>
                    <span class="fi-status fi-status-{$fi.status_id}">{$fi.status_label}</span>
                  </div>
                {/foreach}
              </div>
            {else}
              <div class="chain-missing rd-badge rd-badge-fail">⚠ No financial items found for this line item.</div>
            {/if}
        </div>
          {if !$li@last}<hr class="chain-block-separator">{/if}
          {foreachelse}
        <div class="chain-missing rd-badge rd-badge-fail">⚠ No line items found for this contribution.</div>
      {/foreach}
  </div>

{* Financial Transactions *}
  <div class="civiledger-section">
    <h2>Layer 3 — Financial Transactions (How money moved)</h2>
      {foreach from=$chain.trxns item=trxn}
        <div class="chain-block {if $trxn.total_amount < 0}trxn-reversal{elseif !$trxn.is_payment}trxn-fee{/if}">
          <div class="chain-block-header">
            Trxn #{$trxn.id}
              {if $trxn.total_amount < 0}<span class="badge-reversal">REVERSAL</span>{/if}
              {if $trxn.is_payment}
                <span class="badge-payment">PAYMENT</span>
              {else}
                <span class="badge-fee" title="This transaction records a processor fee or account transfer, not a direct payment.">PROCESSOR FEE / TRANSFER</span>
              {/if}
            <span class="amount-badge">{$trxn.total_amount|crmMoney:$trxn.currency}</span>
              {if $trxn.total_amount >= 0}
                <a href="{crmURL p='civicrm/civiledger/account-correction' q="cid=`$contributionId`&trxn_id=`$trxn.id`"}" class="button small float-right">
                  <i class="crm-i fa-exchange"></i> Correct Accounts
                </a>
              {/if}
          </div>
          {if !$trxn.is_payment}
            <div class="rd-badge rd-badge-info" style="display: list-item; margin-bottom: 6px;">
              <i class="crm-i fa-info-circle"></i> This entry records a processor fee or internal account transfer — it is not counted toward the contribution payment total.
            </div>
          {/if}
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
          {if !$trxn@last}<hr class="chain-block-separator">{/if}
          {foreachelse}
        <div class="chain-missing rd-badge rd-badge-fail">⚠ No financial transactions linked to this contribution.</div>
      {/foreach}
  </div>

{* Audit Log *}
    {if $chain.audit_log}
      <div class="civiledger-section">
        <h2><i class="crm-i fa-history"></i> CiviLedger Change History</h2>
        <table class="civiledger-table">
          <thead>
          <tr><th>Date</th><th>Event</th><th>Details</th><th>Performed By</th></tr>
          </thead>
          <tbody>
          {foreach from=$chain.audit_log item=log}
            <tr>
              <td>{$log.logged_at|crmDate}</td>
              <td><span class="action-badge action-{$log.event_type|lower}">{$log.event_type|replace:'_':' '}</span></td>
              <td>
                {if $log.event_type == 'CORRECTION'}
                  FROM: {$log.old_from_name|default:'—'} → {$log.new_from_name|default:'—'}<br>
                  TO: {$log.old_to_name|default:'—'} → {$log.new_to_name|default:'—'}
                  {if $log.detail_decoded.reason}
                    <br><em>{$log.detail_decoded.reason}</em>
                  {/if}
                {elseif $log.event_type == 'REPAIR'}
                  {if $log.detail_decoded.fixed}{$log.detail_decoded.fixed} fixed{/if}
                  {if $log.detail_decoded.skipped}, {$log.detail_decoded.skipped} skipped{/if}
                  {if $log.detail_decoded.warning}, {$log.detail_decoded.warning} warning{/if}
                  {if $log.detail_decoded.errors}, {$log.detail_decoded.errors} errors{/if}
                {elseif $log.event_type == 'PERIOD_LOCK'}
                  Locked before {$log.detail_decoded.lock_date|default:''}
                  {if $log.detail_decoded.reason}: {$log.detail_decoded.reason}{/if}
                {elseif $log.event_type == 'PERIOD_UNLOCK'}
                  {$log.detail_decoded.unlock_reason|default:''}
                {else}
                  {$log.detail|truncate:120}
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
