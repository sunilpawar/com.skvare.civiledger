{* CiviLedger - Feature 4: Account Balance Dashboard *}
{crmStyle ext="com.skvare.civiledger" file="css/civiledger.css"}
{crmScript ext="com.skvare.civiledger" file="js/civiledger.js"}

<div class="civiledger-wrap">

  <div class="civiledger-header">
    <h1><i class="crm-i fa-bar-chart"></i> {ts}Account Balance Dashboard{/ts}</h1>
    <p>{ts}Live credit/debit/balance for every financial account. Click an account to drill into its movements.{/ts}</p>
  </div>

    {* Date filter *}
  <div class="civiledger-filter-bar">
    <form method="get" action="{crmURL p='civicrm/civiledger/balance'  a=1}">
      {if $cms_type eq 'WordPress'}
        <input type="hidden" name="page" value="CiviCRM" />
      {/if}
      <input type="hidden" name="q" value="civicrm/civiledger/balance" />
      <input type="hidden" name="reset" value="1">
      <label>{ts}From{/ts}: <input type="date" name="date_from" value="{$dateFrom}"></label>
      <label>{ts}To{/ts}: <input type="date" name="date_to" value="{$dateTo}"></label>
        {if $accountId}<input type="hidden" name="account_id" value="{$accountId}">{/if}
      <button type="submit" class="button">{ts}Filter{/ts}</button>
    </form>
  </div>

    {* Summary Stats *}
  <div class="civiledger-stats-row">
    <div class="civiledger-stat-card">
      <div class="stat-number">{$stats.total_transactions|default:0}</div>
      <div class="stat-label">{ts}Total Transactions{/ts}</div>
    </div>
    <div class="civiledger-stat-card">
      <div class="stat-number">{$stats.total_payments|crmMoney|default:'0.00'}</div>
      <div class="stat-label">{ts}Total Payments In{/ts}</div>
    </div>
    <div class="civiledger-stat-card">
      <div class="stat-number">{$stats.refund_count|default:0}</div>
      <div class="stat-label">{ts}Refunds / Reversals{/ts}</div>
    </div>
    <div class="civiledger-stat-card">
      <div class="stat-number">{$stats.accounts_with_activity|default:0}</div>
      <div class="stat-label">{ts}Active Accounts{/ts}</div>
    </div>
  </div>

    {* All accounts grouped by type *}
    {foreach from=$grouped key=typeName item=typeRows}
      <div class="civiledger-section">
        <h2><i class="crm-i fa-folder"></i> {$typeName}</h2>
        <table class="civiledger-table">
          <thead>
          <tr>
            <th>{ts}Account Name{/ts}</th>
            <th>{ts}Code{/ts}</th>
            <th class="text-right">{ts}Credits (Cr){/ts}</th>
            <th class="text-right">{ts}Debits (Dr){/ts}</th>
            <th class="text-right">{ts}Net Balance{/ts}</th>
            <th class="text-right">{ts}Transactions{/ts}</th>
            <th>{ts}Detail{/ts}</th>
          </tr>
          </thead>
          <tbody>
          {foreach from=$typeRows item=row}
            <tr>
              <td><strong>{$row.name}</strong></td>
              <td><code>{$row.accounting_code|default:'—'}</code></td>
              <td class="text-right">{$row.total_credits|crmMoney}</td>
              <td class="text-right">{$row.total_debits|crmMoney}</td>
              <td class="text-right {if $row.balance < 0}crm-error{elseif $row.balance > 0}crm-ok{/if}">
                <strong>{$row.balance|crmMoney}</strong>
              </td>
              <td class="text-right">{$row.trxn_count}</td>
              <td>
                  {if $row.trxn_count > 0}
                    <a href="{crmURL p='civicrm/civiledger/balancemovement' q="reset=1&account_id=`$row.id`&date_from=`$dateFrom`&date_to=`$dateTo`"}"
                       class="button small">{ts}View Movements{/ts}</a>
                  {else}—{/if}
              </td>
            </tr>
          {/foreach}
          </tbody>
        </table>
      </div>
    {/foreach}

    {if !$grouped}
      <div class="civiledger-section">
        <p class="civiledger-empty">
          <i class="crm-i fa-info-circle"></i>
            {ts}No financial accounts found. Ensure CiviCRM financial accounts are configured.{/ts}
        </p>
      </div>
    {/if}

</div>
