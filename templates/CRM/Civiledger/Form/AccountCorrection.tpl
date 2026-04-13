{* CiviLedger Account Correction Template - Feature 6 *}
<div class="crm-container civiledger-correction">
  <h3>{ts}CiviLedger — Account Correction Tool{/ts}</h3>
  <p>{ts}Search for a financial transaction, then apply FROM/TO account corrections using proper double-entry reversal.{/ts}</p>

  {* Search form *}
  <div class="crm-block">
    <h4>{ts}Search Transactions{/ts}</h4>
    <div class="crm-section">
      <div class="label">{$form.search_contribution_id.label}</div>
      <div class="content">{$form.search_contribution_id.html}</div>
    </div>
    <div class="crm-section">
      <div class="label">{$form.search_date_from.label}</div>
      <div class="content">{$form.search_date_from.html}</div>
      <div class="label">{$form.search_date_to.label}</div>
      <div class="content">{$form.search_date_to.html}</div>
    </div>
    <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
  </div>

  {* Search results *}
  {if $transactions}
  <h4>{ts}Transactions{/ts}</h4>
  <table class="display crm-data-table">
    <thead>
      <tr><th>{ts}Trxn ID{/ts}</th><th>{ts}Date{/ts}</th><th>{ts}Contact{/ts}</th>
          <th>{ts}FROM Account{/ts}</th><th>→</th><th>{ts}TO Account{/ts}</th>
          <th>{ts}Amount{/ts}</th><th>{ts}Payment?{/ts}</th><th>{ts}Action{/ts}</th></tr>
    </thead>
    <tbody>
      {foreach from=$transactions item=t}
      <tr class="{cycle values='odd,even'}">
        <td>{$t.id}</td>
        <td>{$t.trxn_date|crmDate}</td>
        <td>{$t.contact_name}</td>
        <td>{$t.from_account}</td>
        <td>→</td>
        <td>{$t.to_account}</td>
        <td class="{if $t.total_amount < 0}crm-error{/if}">{$t.total_amount|crmMoney:$t.currency}</td>
        <td>{if $t.is_payment}✅{else}—{/if}</td>
        <td>
          <a href="{crmURL p='civicrm/civiledger/account-correction' q="trxn_id=`$t.id`&reset=1"}" class="button">{ts}Select{/ts}</a>
        </td>
      </tr>
      {/foreach}
    </tbody>
  </table>
  {/if}

  {* Correction form for selected transaction *}
  {if $trxn}
  <div class="crm-block civiledger-correction-form">
    <h4>{ts}Correcting Transaction #{/ts}{$trxnId}</h4>
    <div class="crm-section">
      <table class="form-layout-compressed">
        <tr><td><b>{ts}Contact{/ts}:</b></td><td>{$trxn.contact_name}</td>
            <td><b>{ts}Date{/ts}:</b></td><td>{$trxn.trxn_date|crmDate}</td></tr>
        <tr><td><b>{ts}Amount{/ts}:</b></td><td>{$trxn.total_amount|crmMoney:$trxn.currency}</td>
            <td><b>{ts}Contribution{/ts}:</b></td><td>#{$trxn.contribution_id}</td></tr>
        <tr>
          <td><b>{ts}Current FROM{/ts}:</b></td>
          <td class="crm-error"><strong>{$trxn.from_account_name}</strong></td>
          <td><b>{ts}Current TO{/ts}:</b></td>
          <td class="crm-error"><strong>{$trxn.to_account_name}</strong></td>
        </tr>
      </table>
    </div>

    <div class="crm-section crm-warning">
      ⚠️ {ts}A reversal transaction will be created on the OLD accounts, and a new transaction on the CORRECT accounts. The original transaction is NOT modified. This preserves your audit trail.{/ts}
    </div>

    <div class="crm-section">
      <div class="label">{$form.new_from_account_id.label}</div>
      <div class="content">{$form.new_from_account_id.html}
        <div class="description">{ts}Leave as "--" to keep the current FROM account.{/ts}</div>
      </div>
    </div>
    <div class="crm-section">
      <div class="label">{$form.new_to_account_id.label}</div>
      <div class="content">{$form.new_to_account_id.html}
        <div class="description">{ts}Leave as "--" to keep the current TO account.{/ts}</div>
      </div>
    </div>
    <div class="crm-section">
      <div class="label">{$form.reason.label}</div>
      <div class="content">{$form.reason.html}</div>
    </div>
    <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>

    {* Correction history *}
    {if $history}
    <h5>{ts}Correction History{/ts}</h5>
    <table class="crm-data-table">
      <thead><tr><th>{ts}Date{/ts}</th><th>{ts}By{/ts}</th><th>{ts}Old FROM→TO{/ts}</th><th>{ts}New FROM→TO{/ts}</th><th>{ts}Reason{/ts}</th></tr></thead>
      <tbody>
        {foreach from=$history item=h}
        <tr class="{cycle values='odd,even'}">
          <td>{$h.modified_date|crmDate}</td>
          <td>{$h.modified_by}</td>
          <td>{$h.data.old_from} → {$h.data.old_to}</td>
          <td>{$h.data.new_from} → {$h.data.new_to}</td>
          <td>{$h.data.reason}</td>
        </tr>
        {/foreach}
      </tbody>
    </table>
    {/if}
  </div>
  {/if}
</div>
