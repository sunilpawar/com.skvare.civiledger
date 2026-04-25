{* CiviLedger — Tax Mapping Template *}
<div class="civiledger-wrap">

  <div class="civiledger-header">
    <h1><i class="crm-i fa-balance-scale"></i> {ts}Tax Mapping{/ts}</h1>
    <p>{ts}Deductible vs. non-deductible contribution analysis based on CiviCRM's non_deductible_amount field and financial type settings.{/ts}</p>
  </div>

  {* ── Date filter ── *}
  <div class="civiledger-filter-bar">
    <form method="get">
      {if $cms_type eq 'WordPress'}
        <input type="hidden" name="page"  value="CiviCRM" />
      {/if}
      <input type="hidden" name="q"     value="civicrm/civiledger/tax-mapping" />
      <input type="hidden" name="reset" value="1" />
      <label>{ts}From{/ts}: <input type="date" name="date_from" value="{$dateFrom}"></label>
      <label>{ts}To{/ts}:   <input type="date" name="date_to"   value="{$dateTo}"></label>
      <button type="submit" class="button">{ts}Filter{/ts}</button>
    </form>
  </div>

  {* ── Summary cards ── *}
  <div class="civiledger-stats-row">
    <div class="civiledger-stat-card">
      <div class="stat-number">{$summary.total_contributions|default:0}</div>
      <div class="stat-label">{ts}Contributions{/ts}</div>
    </div>
    <div class="civiledger-stat-card">
      <div class="stat-number">{$summary.total_amount|crmMoney}</div>
      <div class="stat-label">{ts}Total Amount{/ts}</div>
    </div>
    <div class="civiledger-stat-card civiledger-stat-ok">
      <div class="stat-number">{$summary.total_deductible|crmMoney}</div>
      <div class="stat-label">{ts}Tax-Deductible{/ts}</div>
    </div>
    <div class="civiledger-stat-card">
      <div class="stat-number">{$summary.total_non_deductible|crmMoney}</div>
      <div class="stat-label">{ts}Non-Deductible (Benefit){/ts}</div>
    </div>
    <div class="civiledger-stat-card">
      <div class="stat-number">{$summary.split_count|default:0}</div>
      <div class="stat-label">{ts}Split Contributions{/ts}</div>
    </div>
    {if $summary.issues_count > 0}
      <div class="civiledger-stat-card civiledger-stat-alert">
        <div class="stat-number">{$summary.issues_count}</div>
        <div class="stat-label">{ts}Data Issues{/ts}</div>
      </div>
    {/if}
  </div>

  {* ── Monthly deductible trend chart ── *}
  <div class="civiledger-section">
    <h2><i class="crm-i fa-line-chart"></i> {ts}Monthly Deductible vs. Non-Deductible (Last 12 Months){/ts}</h2>
    <canvas id="taxTrendChart" height="90"></canvas>
  </div>

  {* ── By financial type ── *}
  <div class="civiledger-section">
    <h2><i class="crm-i fa-table"></i> {ts}Breakdown by Financial Type{/ts}</h2>
    {if $byType}
      <table class="civiledger-table">
        <thead>
        <tr>
          <th>{ts}Financial Type (line-item level){/ts}</th>
          <th>{ts}Deductible?{/ts}</th>
          <th class="text-right">{ts}Contributions{/ts}</th>
          <th class="text-right">{ts}Line Items{/ts}</th>
          <th class="text-right">{ts}Line Total{/ts}</th>
          <th class="text-right">{ts}Deductible Portion{/ts}</th>
          <th class="text-right">{ts}Non-Deductible (Benefit){/ts}</th>
          <th class="text-right">{ts}Split Items{/ts}</th>
          <th class="text-right">{ts}Issues{/ts}</th>
        </tr>
        </thead>
        <tbody>
        {foreach from=$byType item=row}
          <tr>
            <td><strong>{$row.financial_type_name}</strong></td>
            <td>
              {if $row.is_deductible}
                <span class="tm-badge tm-yes">{ts}Yes{/ts}</span>
              {else}
                <span class="tm-badge tm-no">{ts}No{/ts}</span>
              {/if}
            </td>
            <td class="text-right">{$row.contribution_count}</td>
            <td class="text-right">{$row.line_item_count}</td>
            <td class="text-right">{$row.total_amount|crmMoney}</td>
            <td class="text-right">{$row.deductible_amount|crmMoney}</td>
            <td class="text-right">{if $row.non_deductible_amount > 0}<span style="color:#856404">{$row.non_deductible_amount|crmMoney}</span>{else}—{/if}</td>
            <td class="text-right">{$row.split_count}</td>
            <td class="text-right">
              {if $row.issue_count > 0}
                <span style="color:#dc3545;font-weight:700">{$row.issue_count}</span>
              {else}—{/if}
            </td>
          </tr>
        {/foreach}
        </tbody>
      </table>
    {else}
      <p class="civiledger-empty">{ts}No contributions in this date range.{/ts}</p>
    {/if}
  </div>

  {* ── Issues ── *}
  {if $issues}
    <div class="civiledger-section">
      <h2><i class="crm-i fa-exclamation-triangle" style="color:#dc3545"></i>
        {ts}Data Issues Requiring Attention{/ts}
        <span class="rd-count">{$issues|@count}</span>
      </h2>
      <p style="color:#856404;font-size:13px">
        {ts}These contributions have tax mapping problems. Correct them in the contribution record.{/ts}
      </p>
      <p style="font-size:12px;color:#555;margin:0 0 8px">
        <strong>{ts}Sources:{/ts}</strong>
        {ts}Contrib. Non-Ded = civicrm_contribution.non_deductible_amount |
        LI Non-Ded = SUM(civicrm_line_item.non_deductible_amount){/ts}
      </p>
      <table class="civiledger-table">
        <thead>
        <tr>
          <th>{ts}Contribution{/ts}</th>
          <th>{ts}Date{/ts}</th>
          <th>{ts}Contact{/ts}</th>
          <th>{ts}Financial Type{/ts}</th>
          <th class="text-right">{ts}Total{/ts}</th>
          <th class="text-right">{ts}Contrib. Non-Ded{/ts}</th>
          <th class="text-right">{ts}LI Non-Ded{/ts}</th>
          <th>{ts}Issue{/ts}</th>
        </tr>
        </thead>
        <tbody>
        {foreach from=$issues item=row}
          <tr>
            <td>
              <a href="{crmURL p='civicrm/civiledger/audit-trail' q="reset=1&contribution_id=`$row.contribution_id`"}"
                 target="_blank">#{$row.contribution_id}</a>
            </td>
            <td>{$row.receive_date|crmDate}</td>
            <td>{$row.contact_name}</td>
            <td>
              {$row.financial_type_name}
              {if !$row.is_deductible}
                <span class="tm-badge tm-no" style="font-size:10px">{ts}Non-ded type{/ts}</span>
              {/if}
            </td>
            <td class="text-right">{$row.total_amount|crmMoney}</td>
            <td class="text-right {if $row.issue_type == 'non_deductible_exceeds_total'}tm-issue-cell{/if}">
              {$row.contrib_non_deductible|crmMoney}
            </td>
            <td class="text-right {if $row.issue_type == 'li_sum_mismatch'}tm-issue-cell{/if}">
              {if $row.li_non_deductible !== NULL}
                {$row.li_non_deductible|crmMoney}
              {else}—{/if}
            </td>
            <td><span class="tm-issue">{$issueLabels[$row.issue_type]|default:$row.issue_type}</span></td>
          </tr>
        {/foreach}
        </tbody>
      </table>
    </div>
  {/if}

</div>

<style>
.tm-badge      { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700; }
.tm-yes        { background:#d4edda; color:#155724; }
.tm-no         { background:#f8d7da; color:#721c24; }
.tm-issue      { font-size:12px; color:#856404; }
.tm-issue-cell { color:#dc3545; font-weight:600; }
</style>

<script>
(function initTaxChart() {
  if (typeof Chart === 'undefined') {
    // Chart.js not yet ready — retry once after scripts settle
    return window.addEventListener('load', initTaxChart);
  }
  new Chart(document.getElementById('taxTrendChart'), {
    type: 'bar',
    data: {
      labels: {$chartLabels},
      datasets: [
        {
          label: '{ts}Deductible{/ts}',
          data: {$chartDeductible},
          backgroundColor: 'rgba(40,167,69,0.7)',
        },
        {
          label: '{ts}Non-Deductible (Benefit){/ts}',
          data: {$chartNonDeduct},
          backgroundColor: 'rgba(255,193,7,0.7)',
        }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'top' } },
      scales: { x: { stacked: false }, y: { beginAtZero: true } }
    }
  });
}());
</script>
