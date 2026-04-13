{* CiviLedger Dashboard Template *}
<div class="crm-container civiledger-dashboard">

  <div class="crm-block">
    <h3>{ts}CiviLedger — Financial Health Dashboard{/ts}</h3>

    {* Health Banner *}
    <div class="civiledger-health-banner civiledger-health-{$healthScore}">
      {if $healthScore == 'good'}
        ✅ {ts}All financial chains are healthy. No issues detected.{/ts}
      {elseif $healthScore == 'warning'}
        ⚠️ {ts}%1 issue(s) detected. Review integrity and mismatch reports.{/ts 1=$totalIssues}
      {else}
        🚨 {ts}%1 critical issues found. Immediate review recommended.{/ts 1=$totalIssues}
      {/if}
    </div>

    {* Date Filter *}
    <div class="civiledger-filter-bar">
      <form method="get">
        <input type="hidden" name="reset" value="1">
        <label>{ts}From{/ts}: <input type="date" name="date_from" value="{$dateFrom}"></label>
        <label>{ts}To{/ts}: <input type="date" name="date_to" value="{$dateTo}"></label>
        <input type="submit" value="{ts}Filter{/ts}" class="button">
      </form>
    </div>

    {* Stats Row *}
    <div class="civiledger-stats-row">
      <div class="civiledger-stat-card">
        <div class="stat-number">{$stats.total_transactions|default:0}</div>
        <div class="stat-label">{ts}Total Transactions{/ts}</div>
      </div>
      <div class="civiledger-stat-card">
        <div class="stat-number">{$stats.total_payments|crmMoney|default:0}</div>
        <div class="stat-label">{ts}Total Payments{/ts}</div>
      </div>
      <div class="civiledger-stat-card">
        <div class="stat-number">{$stats.refund_count|default:0}</div>
        <div class="stat-label">{ts}Refunds{/ts}</div>
      </div>
      <div class="civiledger-stat-card civiledger-stat-{if $integritySummary.missing_financial_items > 0}alert{else}ok{/if}">
        <div class="stat-number">{$integritySummary.missing_financial_items}</div>
        <div class="stat-label">{ts}Missing Financial Items{/ts}</div>
      </div>
      <div class="civiledger-stat-card civiledger-stat-{if $integritySummary.missing_eft_financial_item > 0}alert{else}ok{/if}">
        <div class="stat-number">{$integritySummary.missing_eft_financial_item}</div>
        <div class="stat-label">{ts}Broken EFT Links{/ts}</div>
      </div>
    </div>

    {* Quick Links *}
    <div class="civiledger-quick-links">
      <h4>{ts}Tools{/ts}</h4>
      <a href="{crmURL p='civicrm/civiledger/integrity-check' q='reset=1'}" class="button">{ts}🔍 Integrity Check{/ts}</a>
      <a href="{crmURL p='civicrm/civiledger/chain-repair'    q='reset=1'}" class="button">{ts}🛠 Chain Repair{/ts}</a>
      <a href="{crmURL p='civicrm/civiledger/audit-trail'     q='reset=1'}" class="button">{ts}📊 Audit Trail{/ts}</a>
      <a href="{crmURL p='civicrm/civiledger/mismatch-detector' q='reset=1'}" class="button">{ts}⚠️ Mismatch Detector{/ts}</a>
      <a href="{crmURL p='civicrm/civiledger/account-correction' q='reset=1'}" class="button">{ts}✏️ Account Correction{/ts}</a>
    </div>

    {* Account Balances Table *}
    <h4>{ts}Financial Account Balances{/ts} ({$dateFrom} – {$dateTo})</h4>
    {if $balances}
    <table class="display crm-data-table">
      <thead>
        <tr>
          <th>{ts}Account{/ts}</th>
          <th>{ts}Type{/ts}</th>
          <th>{ts}Code{/ts}</th>
          <th class="right">{ts}Credits (IN){/ts}</th>
          <th class="right">{ts}Debits (OUT){/ts}</th>
          <th class="right">{ts}Balance{/ts}</th>
          <th class="right">{ts}Transactions{/ts}</th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$balances item=row}
        <tr class="{cycle values='odd,even'}">
          <td>
            <a href="{crmURL p='civicrm/civiledger/dashboard' q="account_id=`$row.id`&date_from=`$dateFrom`&date_to=`$dateTo`"}">
              {$row.name}
            </a>
          </td>
          <td>{$row.account_type}</td>
          <td>{$row.accounting_code}</td>
          <td class="right">{$row.total_credits|crmMoney}</td>
          <td class="right">{$row.total_debits|crmMoney}</td>
          <td class="right {if $row.balance < 0}crm-error{/if}">{$row.balance|crmMoney}</td>
          <td class="right">{$row.trxn_count}</td>
        </tr>
        {/foreach}
      </tbody>
    </table>
    {else}
      <p class="crm-empty">{ts}No account activity found for this date range.{/ts}</p>
    {/if}
  </div>
</div>
