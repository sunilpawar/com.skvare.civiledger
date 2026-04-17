{* CiviLedger - Feature 4: Account Balance Dashboard *}
{crmStyle ext="com.skvare.civiledger" file="css/civiledger.css"}
{crmScript ext="com.skvare.civiledger" file="js/civiledger.js"}

<div class="civiledger-wrap">

  <div class="civiledger-header">
    <h1><i class="crm-i fa-bar-chart"></i> {ts}Account Balance Movement{/ts}</h1>
    <p>{ts}Live credit/debit/balance movement.{/ts}</p>
  </div>

    {* Date filter *}
  <div class="civiledger-filter-bar">
    <form method="get">
      <input type="hidden" name="reset" value="1">
      <label>{ts}From{/ts}: <input type="date" name="date_from" value="{$dateFrom}"></label>
      <label>{ts}To{/ts}:   <input type="date" name="date_to"   value="{$dateTo}"></label>
        {if $accountId}<input type="hidden" name="account_id" value="{$accountId}">{/if}
      <button type="submit" class="button">{ts}Filter{/ts}</button>
      <a href="{crmURL p='civicrm/civiledger/balance' q="reset=1&date_from=`$dateFrom`&date_to=`$dateTo`"}" class="button">{ts}← All Accounts{/ts}</a>
    </form>
  </div>


    {* Drill-down: account movements *}
    {if $accountId && $accountName}
      <div class="civiledger-section">
        <h2><i class="crm-i fa-list"></i> {ts 1=$accountName}Movements: %1{/ts} &nbsp;
          <small style="font-weight:normal;font-size:12px;color:#888">{$dateFrom} – {$dateTo}</small>
        </h2>
          {if $movements}
            <table class="civiledger-table">
              <thead>
              <tr>
                <th>{ts}Date{/ts}</th>
                <th>{ts}Direction{/ts}</th>
                <th class="text-right">{ts}Credit (IN){/ts}</th>
                <th class="text-right">{ts}Debit (OUT){/ts}</th>
                <th>{ts}FROM Account{/ts}</th>
                <th>{ts}TO Account{/ts}</th>
                <th>{ts}Contact{/ts}</th>
                <th>{ts}Contribution{/ts}</th>
                <th>{ts}Ref{/ts}</th>
              </tr>
              </thead>
              <tbody>
              {foreach from=$movements item=m}
                <tr class="{if $m.direction eq 'credit'}row-credit{else}row-debit{/if}">
                  <td>{$m.trxn_date|crmDate}</td>
                  <td>
                      {if $m.direction eq 'credit'}
                        <span class="badge" style="background:#d4edda;color:#155724">▲ {ts}Credit{/ts}</span>
                      {else}
                        <span class="badge" style="background:#f8d7da;color:#721c24">▼ {ts}Debit{/ts}</span>
                      {/if}
                  </td>
                  <td class="text-right">{if $m.credit_amount > 0}{$m.credit_amount|crmMoney}{else}—{/if}</td>
                  <td class="text-right">{if $m.debit_amount  > 0}{$m.debit_amount|crmMoney}{else}—{/if}</td>
                  <td>{$m.from_account|default:'—'}</td>
                  <td>{$m.to_account|default:'—'}</td>
                  <td>{$m.contact_name|default:'—'}</td>
                  <td>
                      {if $m.contribution_id}
                        <a href="{crmURL p='civicrm/civiledger/audit-trail' q="reset=1&contribution_id=`$m.contribution_id`"}">#{$m.contribution_id}</a>
                      {else}—{/if}
                  </td>
                  <td><small>{$m.processor_ref|truncate:20|default:'—'}</small></td>
                </tr>
              {/foreach}
              </tbody>
            </table>
          {else}
            <p class="civiledger-empty">{ts}No movements found for this account in the selected date range.{/ts}</p>
          {/if}
      </div>
    {/if}
</div>
