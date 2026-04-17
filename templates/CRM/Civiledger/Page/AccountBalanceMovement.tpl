{* CiviLedger - Account Balance Movement *}
{crmStyle ext="com.skvare.civiledger" file="css/civiledger.css"}
{crmScript ext="com.skvare.civiledger" file="js/civiledger.js"}

<div class="civiledger-wrap">

  <div class="civiledger-header">
    <h1><i class="crm-i fa-exchange"></i> {ts}Account Balance Movement{/ts}</h1>
    <p>
      {if $accountName}
        {ts 1=$accountName}Detailed credit / debit movements for <strong>%1</strong>.{/ts}
      {else}
        {ts}Select an account and date range to view movements.{/ts}
      {/if}
    </p>
  </div>

  {* ── Filter bar ── *}
  <div class="civiledger-filter-bar">
    <form method="get">
      <input type="hidden" name="reset" value="1">

      <label>{ts}Account{/ts}:
        <select name="account_id" style="min-width:200px">
          {foreach from=$accountOptions key=optId item=optLabel}
            <option value="{$optId}"{if $optId == $accountId} selected="selected"{/if}>{$optLabel}</option>
          {/foreach}
        </select>
      </label>

      <label>{ts}From{/ts}: <input type="date" name="date_from" value="{$dateFrom}"></label>
      <label>{ts}To{/ts}:   <input type="date" name="date_to"   value="{$dateTo}"></label>

      <button type="submit" class="button">{ts}Filter{/ts}</button>
      <a href="{crmURL p='civicrm/civiledger/balance' q="reset=1&date_from=`$dateFrom`&date_to=`$dateTo`"}"
         class="button">{ts}← All Accounts{/ts}</a>
    </form>
  </div>

  {* ── Per-account summary stats (only when an account is selected) ── *}
  {if $accountId && $accountName}

    <div class="civiledger-stats-row">

      <div class="civiledger-stat-card" style="border-top:4px solid #28a745">
        <div class="stat-number" style="color:#28a745">
          {if $accountStats.total_credits}{$accountStats.total_credits|crmMoney}{else}0.00{/if}
        </div>
        <div class="stat-label">{ts}Total Credits (IN){/ts}</div>
        <div class="stat-sub">{ts}Money received into this account{/ts}</div>
      </div>

      <div class="civiledger-stat-card" style="border-top:4px solid #dc3545">
        <div class="stat-number" style="color:#dc3545">
          {if $accountStats.total_debits}{$accountStats.total_debits|crmMoney}{else}0.00{/if}
        </div>
        <div class="stat-label">{ts}Total Debits (OUT){/ts}</div>
        <div class="stat-sub">{ts}Money sent out from this account{/ts}</div>
      </div>

      <div class="civiledger-stat-card"
           style="border-top:4px solid {if $accountStats.net_balance < 0}#dc3545{else}#007bff{/if}">
        <div class="stat-number"
             style="color:{if $accountStats.net_balance < 0}#dc3545{else}#007bff{/if}">
          {if $accountStats.net_balance}{$accountStats.net_balance|crmMoney}{else}0.00{/if}
        </div>
        <div class="stat-label">{ts}Net Balance{/ts}</div>
        <div class="stat-sub">{ts}Credits minus Debits{/ts}</div>
      </div>

      <div class="civiledger-stat-card" style="border-top:4px solid #6c757d">
        <div class="stat-number">{$accountStats.trxn_count|default:0}</div>
        <div class="stat-label">{ts}Transactions{/ts}</div>
        <div class="stat-sub">
          {if $accountStats.payment_count}
            {ts 1=$accountStats.payment_count}%1 payment(s){/ts}
          {else}
            {ts}0 payments{/ts}
          {/if}
        </div>
      </div>

    </div>

    {* Date range note *}
    <p style="margin:0 0 12px;font-size:13px;color:#666">
      <i class="crm-i fa-calendar"></i>
      {ts}Period:{/ts} <strong>{$dateFrom}</strong> &ndash; <strong>{$dateTo}</strong>
      {if $accountStats.first_trxn_date && $accountStats.last_trxn_date}
        &nbsp;|&nbsp; {ts}First trxn:{/ts} {$accountStats.first_trxn_date|crmDate}
        &nbsp;&nbsp; {ts}Last trxn:{/ts} {$accountStats.last_trxn_date|crmDate}
      {/if}
    </p>

    {* ── Movement detail table ── *}
    <div class="civiledger-section">
      <h2>
        <i class="crm-i fa-list"></i>
        {ts 1=$accountName}Movements: %1{/ts}
        <small style="font-weight:normal;font-size:12px;color:#888">
          ({$movements|@count} {ts}records shown{/ts})
        </small>
      </h2>

      {if $movements}

        {* Running totals for footer *}
        {assign var="sumCredit" value=0}
        {assign var="sumDebit"  value=0}
        {foreach from=$movements item=m}
          {assign var="sumCredit" value=$sumCredit+$m.credit_amount}
          {assign var="sumDebit"  value=$sumDebit+$m.debit_amount}
        {/foreach}

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
                  <span class="badge" style="background:#d4edda;color:#155724">&#9650; {ts}Credit{/ts}</span>
                {else}
                  <span class="badge" style="background:#f8d7da;color:#721c24">&#9660; {ts}Debit{/ts}</span>
                {/if}
              </td>
              <td class="text-right">
                {if $m.credit_amount > 0}
                  <span style="color:#28a745;font-weight:600">{$m.credit_amount|crmMoney}</span>
                {else}&mdash;{/if}
              </td>
              <td class="text-right">
                {if $m.debit_amount > 0}
                  <span style="color:#dc3545;font-weight:600">{$m.debit_amount|crmMoney}</span>
                {else}&mdash;{/if}
              </td>
              <td>{$m.from_account|default:'&mdash;'}</td>
              <td>{$m.to_account|default:'&mdash;'}</td>
              <td>
                {if $m.contact_name}
                  <a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$m.contact_id`"}">{$m.contact_name}</a>
                {else}&mdash;{/if}
              </td>
              <td>
                {if $m.contribution_id}
                  <a href="{crmURL p='civicrm/civiledger/audit-trail' q="reset=1&contribution_id=`$m.contribution_id`"}">#{$m.contribution_id}</a>
                {else}&mdash;{/if}
              </td>
              <td><small>{$m.processor_ref|truncate:20|default:'&mdash;'}</small></td>
            </tr>
          {/foreach}
          </tbody>
          <tfoot>
          <tr style="background:#f8f9fa;font-weight:700;border-top:2px solid #dee2e6">
            <td colspan="2">{ts}Totals ({$movements|@count} rows){/ts}</td>
            <td class="text-right" style="color:#28a745">{$sumCredit|crmMoney}</td>
            <td class="text-right" style="color:#dc3545">{$sumDebit|crmMoney}</td>
            <td colspan="5"></td>
          </tr>
          </tfoot>
        </table>

        {if $movements|@count >= 50}
          <p style="margin-top:8px;font-size:12px;color:#999">
            <i class="crm-i fa-info-circle"></i>
            {ts}Showing up to 50 records. Narrow the date range to see more specific results.{/ts}
          </p>
        {/if}

      {else}
        <p class="civiledger-empty">
          <i class="crm-i fa-info-circle"></i>
          {ts}No movements found for this account in the selected date range.{/ts}
        </p>
      {/if}

    </div>

  {else}

    <div class="civiledger-section">
      <p class="civiledger-empty">
        <i class="crm-i fa-hand-o-up"></i>
        {ts}Select an account above and click Filter to view its movements.{/ts}
      </p>
    </div>

  {/if}

</div>
