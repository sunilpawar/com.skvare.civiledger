{* CiviLedger Integrity Check Template - Feature 1 *}
<div class="crm-container civiledger-integrity">
  <h3>{ts}CiviLedger — Financial Integrity Check{/ts}</h3>
  <p>{ts}This tool scans your CiviCRM database for broken financial data chains.{/ts}</p>

  {* Summary Cards *}
  <div class="civiledger-stats-row">
    <div class="civiledger-stat-card {if $summary.missing_line_items > 0}civiledger-stat-alert{else}civiledger-stat-ok{/if}">
      <div class="stat-number">{$summary.missing_line_items}</div>
      <div class="stat-label">{ts}Missing Line Items{/ts}</div>
    </div>
    <div class="civiledger-stat-card {if $summary.missing_financial_items > 0}civiledger-stat-alert{else}civiledger-stat-ok{/if}">
      <div class="stat-number">{$summary.missing_financial_items}</div>
      <div class="stat-label">{ts}Missing Financial Items{/ts}</div>
    </div>
    <div class="civiledger-stat-card {if $summary.missing_eft_contribution > 0}civiledger-stat-alert{else}civiledger-stat-ok{/if}">
      <div class="stat-number">{$summary.missing_eft_contribution}</div>
      <div class="stat-label">{ts}Missing EFT (Contribution){/ts}</div>
    </div>
    <div class="civiledger-stat-card {if $summary.missing_eft_financial_item > 0}civiledger-stat-alert{else}civiledger-stat-ok{/if}">
      <div class="stat-number">{$summary.missing_eft_financial_item}</div>
      <div class="stat-label">{ts}Missing EFT (Financial Item){/ts}</div>
    </div>
    <div class="civiledger-stat-card {if $summary.missing_financial_trxn > 0}civiledger-stat-alert{else}civiledger-stat-ok{/if}">
      <div class="stat-number">{$summary.missing_financial_trxn}</div>
      <div class="stat-label">{ts}Orphaned EFT Rows{/ts}</div>
    </div>
  </div>

  {* Filter buttons *}
  <div class="civiledger-tab-bar">
    <a href="?reset=1&check_type=all" class="button {if $checkType=='all'}active{/if}">{ts}Show All Issues{/ts}</a>
    <a href="?reset=1&check_type=missing_financial_items" class="button">{ts}Missing Financial Items{/ts}</a>
    <a href="?reset=1&check_type=missing_eft_fi" class="button">{ts}Missing EFT Links{/ts}</a>
    <a href="{crmURL p='civicrm/civiledger/chain-repair' q='reset=1'}" class="button crm-button-type-upload">{ts}🛠 Go to Repair Tool{/ts}</a>
  </div>

  {* Missing Financial Items *}
  {if $rows.missing_financial_items}
  <h4>{ts}Contributions Missing Financial Items{/ts} ({$rows.missing_financial_items|@count})</h4>
  <table class="display crm-data-table">
    <thead>
      <tr><th>{ts}Contrib ID{/ts}</th><th>{ts}Contact{/ts}</th><th>{ts}Amount{/ts}</th>
          <th>{ts}Date{/ts}</th><th>{ts}Financial Type{/ts}</th><th>{ts}Line Items{/ts}</th><th>{ts}Actions{/ts}</th></tr>
    </thead>
    <tbody>
      {foreach from=$rows.missing_financial_items item=r}
      <tr class="{cycle values='odd,even'}">
        <td>{$r.id}</td>
        <td>{$r.display_name}</td>
        <td>{$r.total_amount|crmMoney:$r.currency}</td>
        <td>{$r.receive_date|crmDate}</td>
        <td>{$r.financial_type_name}</td>
        <td>{$r.line_item_count}</td>
        <td>
          <a href="{crmURL p='civicrm/civiledger/audit-trail' q="contribution_id=`$r.id`&reset=1"}">{ts}Audit{/ts}</a> |
          <a href="{crmURL p='civicrm/civiledger/chain-repair' q="contribution_ids=`$r.id`&reset=1"}">{ts}Repair{/ts}</a>
        </td>
      </tr>
      {/foreach}
    </tbody>
  </table>
  {/if}

  {* Missing EFT for Financial Items *}
  {if $rows.missing_eft_fi}
  <h4>{ts}Contributions with Broken EFT Links (Financial Item){/ts} ({$rows.missing_eft_fi|@count})</h4>
  <p class="crm-inline-error">{ts}These contributions have financial items but the cash-to-accounting link (entity_financial_trxn) is missing. Income reports will be incorrect.{/ts}</p>
  <table class="display crm-data-table">
    <thead>
      <tr><th>{ts}Contrib ID{/ts}</th><th>{ts}Contact{/ts}</th><th>{ts}Amount{/ts}</th>
          <th>{ts}Date{/ts}</th><th>{ts}Financial Type{/ts}</th><th>{ts}Actions{/ts}</th></tr>
    </thead>
    <tbody>
      {foreach from=$rows.missing_eft_fi item=r}
      <tr class="{cycle values='odd,even'}">
        <td>{$r.id}</td>
        <td>{$r.display_name}</td>
        <td>{$r.total_amount|crmMoney:$r.currency}</td>
        <td>{$r.receive_date|crmDate}</td>
        <td>{$r.financial_type_name}</td>
        <td>
          <a href="{crmURL p='civicrm/civiledger/audit-trail' q="contribution_id=`$r.id`&reset=1"}">{ts}Audit{/ts}</a> |
          <a href="{crmURL p='civicrm/civiledger/chain-repair' q="contribution_ids=`$r.id`&reset=1"}">{ts}Repair{/ts}</a>
        </td>
      </tr>
      {/foreach}
    </tbody>
  </table>
  {/if}

  {if !$rows.missing_financial_items && !$rows.missing_eft_fi && !$rows.missing_line_items}
    <div class="crm-container crm-success">✅ {ts}No integrity issues found! Your financial data chains are healthy.{/ts}</div>
  {/if}

  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</div>
