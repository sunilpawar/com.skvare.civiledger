{* CiviLedger Mismatch Detector Template - Feature 5 *}
<div class="crm-container civiledger-mismatch">
  <h3>{ts}CiviLedger — Amount Mismatch Detector{/ts}</h3>
  <p>{ts}Validates the accounting invariant: SUM(line_items) = SUM(financial_items) = contribution.total_amount{/ts}</p>

  {* Counts *}
  <div class="civiledger-stats-row">
    <div class="civiledger-stat-card {if $counts.line_item_vs_contribution > 0}civiledger-stat-alert{else}civiledger-stat-ok{/if}">
      <div class="stat-number">{$counts.line_item_vs_contribution}</div>
      <div class="stat-label">{ts}Line Item vs Contribution{/ts}</div>
    </div>
    <div class="civiledger-stat-card {if $counts.financial_item_vs_line_item > 0}civiledger-stat-alert{else}civiledger-stat-ok{/if}">
      <div class="stat-number">{$counts.financial_item_vs_line_item}</div>
      <div class="stat-label">{ts}Financial Item vs Line Item{/ts}</div>
    </div>
    <div class="civiledger-stat-card {if $counts.trxn_vs_contribution > 0}civiledger-stat-alert{else}civiledger-stat-ok{/if}">
      <div class="stat-number">{$counts.trxn_vs_contribution}</div>
      <div class="stat-label">{ts}Transaction vs Contribution{/ts}</div>
    </div>
  </div>

  {* Line Item vs Contribution *}
  {if $mismatches.line_item_vs_contribution}
  <h4>{ts}Line Item Sum ≠ Contribution Amount{/ts}</h4>
  <table class="display crm-data-table">
    <thead><tr><th>{ts}Contrib ID{/ts}</th><th>{ts}Contact{/ts}</th><th>{ts}Contrib Amount{/ts}</th>
               <th>{ts}Line Item Sum{/ts}</th><th>{ts}Difference{/ts}</th><th>{ts}Date{/ts}</th><th>{ts}Action{/ts}</th></tr></thead>
    <tbody>
      {foreach from=$mismatches.line_item_vs_contribution item=r}
      <tr class="{cycle values='odd,even'}">
        <td>{$r.id}</td><td>{$r.contact_name}</td>
        <td>{$r.contribution_amount|crmMoney:$r.currency}</td>
        <td>{$r.line_item_sum|crmMoney:$r.currency}</td>
        <td class="crm-error">{$r.difference|crmMoney:$r.currency}</td>
        <td>{$r.receive_date|crmDate}</td>
        <td><a href="{crmURL p='civicrm/civiledger/audit-trail' q="contribution_id=`$r.id`&reset=1"}">{ts}Audit{/ts}</a></td>
      </tr>
      {/foreach}
    </tbody>
  </table>
  {/if}

  {* Transaction vs Contribution *}
  {if $mismatches.trxn_vs_contribution}
  <h4>{ts}Payment Sum ≠ Contribution Amount{/ts}</h4>
  <table class="display crm-data-table">
    <thead><tr><th>{ts}Contrib ID{/ts}</th><th>{ts}Contact{/ts}</th><th>{ts}Contrib Amount{/ts}</th>
               <th>{ts}Paid Amount{/ts}</th><th>{ts}Difference{/ts}</th><th>{ts}Date{/ts}</th></tr></thead>
    <tbody>
      {foreach from=$mismatches.trxn_vs_contribution item=r}
      <tr class="{cycle values='odd,even'}">
        <td>{$r.id}</td><td>{$r.contact_name}</td>
        <td>{$r.contribution_amount|crmMoney:$r.currency}</td>
        <td>{$r.paid_amount|crmMoney:$r.currency}</td>
        <td class="crm-error">{$r.difference|crmMoney:$r.currency}</td>
        <td>{$r.receive_date|crmDate}</td>
      </tr>
      {/foreach}
    </tbody>
  </table>
  {/if}

  {if !$mismatches.line_item_vs_contribution && !$mismatches.financial_item_vs_line_item && !$mismatches.trxn_vs_contribution}
    <div class="crm-success">✅ {ts}No amount mismatches found!{/ts}</div>
  {/if}

  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</div>
