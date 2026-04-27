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
                  {ts}Financial Items (Where this money belongs){/ts}
                  <span class="fi-section-sum {if $li.fi_total != $li.line_total}sum-mismatch{else}sum-ok{/if}">
                    {$li.fi_total|crmMoney}
                  </span>
                </h3>

                {* ── Duplicate warning ── *}
                {if $li.has_fi_duplicates}
                  <div class="fi-dup-warning">
                    <i class="crm-i fa-exclamation-triangle"></i>
                    <strong>{ts}Duplicate financial items detected.{/ts}</strong>
                    {ts 1=$li.fi_total|crmMoney 2=$li.line_total|crmMoney}Sum is %1 but line total is %2.{/ts}
                    {ts}The rows marked below are candidates for deletion. Deleting them will also remove their EFT links.{/ts}
                  </div>
                {/if}

                <div class="fi-grid-header {if $li.has_fi_duplicates}fi-grid-header-wide{/if}">
                  <span>{ts}ID{/ts}</span>
                  <span>{ts}Account{/ts}</span>
                  <span>{ts}Description{/ts}</span>
                  <span class="text-right">{ts}Amount{/ts}</span>
                  <span>{ts}Status{/ts}</span>
                  {if $li.has_fi_duplicates}<span>{ts}Action{/ts}</span>{/if}
                </div>

                {foreach from=$li.financial_items item=fi}
                  <div class="fi-row {if $fi.amount < 0}fi-negative{/if} {if $fi.is_duplicate_candidate}fi-duplicate{/if}"
                       id="fi-row-{$fi.id}">
                    <span class="fi-id">
                      #{$fi.id}
                      {if $fi.is_duplicate_candidate}
                        <span class="fi-dup-tag">{ts}DUP{/ts}</span>
                      {/if}
                    </span>
                    <span class="fi-account">
                      <i class="crm-i fa-university"></i> {$fi.account_name}
                      {if $fi.account_type_label}<em class="fi-acct-type">{$fi.account_type_label}</em>{/if}
                    </span>
                    <span class="fi-desc">{$fi.description|default:'—'}</span>
                    <span class="fi-amount">{$fi.amount|crmMoney}</span>
                    <span class="fi-status fi-status-{$fi.status_id}">{$fi.status_label}</span>
                    {if $li.has_fi_duplicates}
                      <span class="fi-action">
                        {if $fi.is_duplicate_candidate}
                          <button type="button"
                                  class="fi-delete-btn"
                                  data-fi-id="{$fi.id}"
                                  data-cid="{$contributionId}"
                                  data-amount="{$fi.amount|crmMoney}"
                                  onclick="clDeleteFi(this)">
                            <i class="crm-i fa-trash"></i> {ts}Delete{/ts}
                          </button>
                        {else}
                          <span class="fi-keep-tag">{ts}Keep{/ts}</span>
                        {/if}
                      </span>
                    {/if}
                  </div>
                {/foreach}
              </div>
            {else}
              <div class="chain-missing rd-badge rd-badge-fail">⚠ {ts}No financial items found for this line item.{/ts}</div>
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
            <tr>
              <th>{ts}Date{/ts}</th>
              <td>{$trxn.trxn_date|crmDate}</td>
              <th>{ts}Status{/ts}</th>
              <td><span class="trxn-status-badge trxn-status-{$trxn.status_id}">{$trxn.status_label|default:'—'}</span></td>
            </tr>
            <tr>
              <th>{ts}Processor Ref{/ts}</th>
              <td class="rd-mono">{$trxn.processor_trxn_id|default:'—'}</td>
              <th>{ts}Payment Method{/ts}</th>
              <td>{$trxn.payment_instrument_label|default:'—'}</td>
            </tr>
            <tr>
              <th>{ts}Processor{/ts}</th>
              <td>{$trxn.payment_processor_name|default:'—'}</td>
            </tr>
            {if $trxn.card_type_label || $trxn.pan_truncation}
            <tr>
              <th>{ts}Card Type{/ts}</th>
              <td>{$trxn.card_type_label|default:'—'}</td>
              <th>{ts}Card (last 4){/ts}</th>
              <td class="rd-mono">{$trxn.pan_truncation|default:'—'}</td>
            </tr>
            {/if}
            {if $trxn.check_number}
            <tr>
              <th>{ts}Check #{/ts}</th>
              <td colspan="3" class="rd-mono">{$trxn.check_number}</td>
            </tr>
            {/if}
            {if $trxn.fee_amount != 0 || $trxn.net_amount}
            <tr>
              <th>{ts}Fee Amount{/ts}</th>
              <td>{$trxn.fee_amount|crmMoney:$trxn.currency}</td>
              <th>{ts}Net Amount{/ts}</th>
              <td>{$trxn.net_amount|crmMoney:$trxn.currency}</td>
            </tr>
            {/if}
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

{* ── Delete confirmation modal ──────────────────────────────────────────── *}
<div id="fi-delete-modal" class="fi-modal-overlay" style="display:none">
  <div class="fi-modal-box">
    <h3><i class="crm-i fa-trash"></i> {ts}Delete Duplicate Financial Item{/ts}</h3>
    <p id="fi-modal-body"></p>
    <div class="fi-modal-warning">
      {ts}This will permanently delete the financial item and its EFT link(s). This action cannot be undone.{/ts}
    </div>
    <div class="fi-modal-actions">
      <button id="fi-modal-confirm" class="button crm-button-type-delete">{ts}Yes, Delete{/ts}</button>
      <button onclick="document.getElementById('fi-delete-modal').style.display='none'" class="button">{ts}Cancel{/ts}</button>
    </div>
    <div id="fi-modal-status" style="display:none;margin-top:10px;padding:8px 12px;border-radius:4px"></div>
  </div>
</div>
{literal}
<style>
.fi-dup-warning {
  background: #fff3cd; border-left: 4px solid #ffc107; color: #856404;
  padding: 8px 12px; border-radius: 0 4px 4px 0; font-size: 12px;
  margin-bottom: 8px; display: flex; align-items: flex-start; gap: 8px; flex-wrap: wrap;
}
.fi-dup-warning strong { font-weight: 700; }
.fi-duplicate { background: #fffbea !important; border-left: 3px solid #ffc107; }
.fi-grid-header-wide,
.fi-row.fi-duplicate { grid-template-columns: 80px 1fr 1fr 100px 110px 90px; }
.fi-dup-tag {
  display: inline-block; background: #dc3545; color: #fff;
  font-size: 9px; font-weight: 700; padding: 0 4px; border-radius: 3px;
  vertical-align: middle; margin-left: 3px; letter-spacing: .04em;
}
.fi-keep-tag {
  display: inline-block; background: #d4edda; color: #155724;
  font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 3px;
}
.fi-delete-btn {
  background: #dc3545; color: #fff; border: none; border-radius: 4px;
  padding: 3px 10px; font-size: 11px; font-weight: 700; cursor: pointer; white-space: nowrap;
}
.fi-delete-btn:hover { background: #b02a37; }
.fi-delete-btn:disabled { background: #aaa; cursor: not-allowed; }
.fi-modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.5);
  z-index: 9999; display: flex; align-items: center; justify-content: center;
}
.fi-modal-box {
  background: #fff; border-radius: 8px; padding: 24px 28px;
  max-width: 480px; width: 90%; box-shadow: 0 8px 32px rgba(0,0,0,0.25);
}
.fi-modal-box h3 { margin: 0 0 12px; font-size: 16px; color: #721c24; }
.fi-modal-box p  { margin: 0 0 10px; font-size: 14px; color: #333; }
.fi-modal-warning {
  background: #f8d7da; color: #721c24; border-radius: 4px;
  padding: 8px 12px; font-size: 12px; margin-bottom: 16px;
}
.fi-modal-actions { display: flex; gap: 10px; }
</style>
{/literal}
{literal}
<script>
(function () {
  var pendingBtn = null;

  window.clDeleteFi = function (btn) {
    pendingBtn = btn;
    var fiId   = btn.getAttribute('data-fi-id');
    var amount = btn.getAttribute('data-amount');
    document.getElementById('fi-modal-body').textContent =
      'Delete financial item #' + fiId + ' (' + amount + ') and its EFT transaction links?';
    var st = document.getElementById('fi-modal-status');
    st.style.display = 'none'; st.textContent = '';
    var cb = document.getElementById('fi-modal-confirm');
    cb.disabled = false; cb.textContent = 'Yes, Delete';
    document.getElementById('fi-delete-modal').style.display = 'flex';
  };

  document.getElementById('fi-modal-confirm').addEventListener('click', function () {
    if (!pendingBtn) { return; }
    var fiId = pendingBtn.getAttribute('data-fi-id');
    var cid  = pendingBtn.getAttribute('data-cid');
    var confirmBtn = this;
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Deleting…';

    CRM.$.ajax({
      url: CRM.url('civicrm/civiledger/ajax'),
      data: {op: 'delete_financial_item', fi_id: fiId, cid: cid},
      method: 'POST',
      dataType: 'json',
      success: function (res) {
        var st = document.getElementById('fi-modal-status');
        st.style.display = 'block';
        if (res.success) {
          st.style.background = '#d4edda'; st.style.color = '#155724';
          st.textContent = res.message;
          var row = document.getElementById('fi-row-' + fiId);
          if (row) {
            row.style.transition = 'opacity 0.4s';
            row.style.opacity = '0';
            setTimeout(function () {
              row.remove();
              document.getElementById('fi-delete-modal').style.display = 'none';
            }, 450);
          }
        } else {
          st.style.background = '#f8d7da'; st.style.color = '#721c24';
          st.textContent = res.message || 'Deletion failed.';
          confirmBtn.disabled = false; confirmBtn.textContent = 'Yes, Delete';
        }
      },
      error: function () {
        var st = document.getElementById('fi-modal-status');
        st.style.display = 'block';
        st.style.background = '#f8d7da'; st.style.color = '#721c24';
        st.textContent = 'Network error — please try again.';
        confirmBtn.disabled = false; confirmBtn.textContent = 'Yes, Delete';
      }
    });
  });
}());
</script>
{/literal}