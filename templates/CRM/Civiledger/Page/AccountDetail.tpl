{* CiviLedger — Account Detail Template *}
<div class="crm-container civiledger-dashboard">
  <div class="crm-block">

    {* Back link *}
    <p>
      <a href="{crmURL p='civicrm/civiledger/dashboard' q="date_from=`$dateFrom`&date_to=`$dateTo`"}" class="button small">
        {ts}← Back to Dashboard{/ts}
      </a>
    </p>

    <h3>{ts 1=$account.name}Account Detail — %1{/ts}</h3>

    {* Account summary card *}
    <div class="civiledger-stats-row">
      <div class="civiledger-stat-card">
        <div class="stat-number">{$account.account_type|default:'—'}</div>
        <div class="stat-label">{ts}Account Type{/ts}</div>
      </div>
      <div class="civiledger-stat-card">
        <div class="stat-number">{$account.accounting_code|default:'—'}</div>
        <div class="stat-label">{ts}Accounting Code{/ts}</div>
      </div>
      <div class="civiledger-stat-card">
        <div class="stat-number">{$account.total_credits|crmMoney}</div>
        <div class="stat-label">{ts}Credits (IN){/ts}</div>
      </div>
      <div class="civiledger-stat-card">
        <div class="stat-number">{$account.total_debits|crmMoney}</div>
        <div class="stat-label">{ts}Debits (OUT){/ts}</div>
      </div>
      <div class="civiledger-stat-card {if $account.balance < 0}civiledger-stat-alert{else}civiledger-stat-ok{/if}">
        <div class="stat-number">{$account.balance|crmMoney}</div>
        <div class="stat-label">{ts}Balance{/ts}</div>
      </div>
      <div class="civiledger-stat-card">
        <div class="stat-number">{$account.trxn_count}</div>
        <div class="stat-label">{ts}Total Transactions{/ts}</div>
      </div>
    </div>

    {* Date filter *}
    <div class="civiledger-filter-bar">
      <form method="get">
        {if $cms_type eq 'WordPress'}
          <input type="hidden" name="page" value="CiviCRM" />
        {/if}
        <input type="hidden" name="q" value="civicrm/civiledger/account-detail" />
        <input type="hidden" name="reset" value="1">
        <input type="hidden" name="account_id" value="{$accountId}">
        <label>{ts}From{/ts}: <input type="date" name="date_from" value="{$dateFrom}"></label>
        <label>{ts}To{/ts}: <input type="date" name="date_to" value="{$dateTo}"></label>
        <input type="submit" value="{ts}Filter{/ts}" class="button">
      </form>
    </div>

    {* Transaction movements *}
    <h4>{ts}Transaction Movements{/ts} ({$dateFrom} – {$dateTo})</h4>

    {if $movements}
      <table class="display crm-data-table">
        <thead>
          <tr>
            <th>{ts}Date{/ts}</th>
            <th>{ts}Contact{/ts}</th>
            <th>{ts}From Account{/ts}</th>
            <th>{ts}To Account{/ts}</th>
            <th class="right">{ts}Credit (IN){/ts}</th>
            <th class="right">{ts}Debit (OUT){/ts}</th>
            <th>{ts}Ref{/ts}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$movements item=mv}
          <tr class="{cycle values='odd,even'} direction-{$mv.direction}">
            <td>{$mv.trxn_date|crmDate}</td>
            <td>
              {if $mv.contribution_id}
                <a href="{crmURL p='civicrm/civiledger/audit-trail' q="contribution_id=`$mv.contribution_id`"}">{$mv.contact_name|default:'—'}</a>
              {else}
                {$mv.contact_name|default:'—'}
              {/if}
            </td>
            <td>{$mv.from_account|default:'—'}</td>
            <td>{$mv.to_account|default:'—'}</td>
            <td class="right">{if $mv.credit_amount > 0}{$mv.credit_amount|crmMoney}{/if}</td>
            <td class="right">{if $mv.debit_amount > 0}{$mv.debit_amount|crmMoney}{/if}</td>
            <td class="text-muted">{$mv.processor_ref|default:'—'}</td>
          </tr>
          {/foreach}
        </tbody>
      </table>

      {* Pagination *}
      <div class="civiledger-pagination">
        {if $hasPrev}
          <a href="{crmURL p='civicrm/civiledger/account-detail' q="account_id=`$accountId`&date_from=`$dateFrom`&date_to=`$dateTo`&page=`$page-1`"}" class="button small">{ts}← Previous{/ts}</a>
        {/if}
        &nbsp;{ts 1=$page}Page %1{/ts}&nbsp;
        {if $hasMore}
          <a href="{crmURL p='civicrm/civiledger/account-detail' q="account_id=`$accountId`&date_from=`$dateFrom`&date_to=`$dateTo`&page=`$page+1`"}" class="button small">{ts}Next →{/ts}</a>
        {/if}
      </div>

    {else}
      <p class="crm-empty">{ts}No transactions found for this account in the selected date range.{/ts}</p>
    {/if}

  </div>
</div>
