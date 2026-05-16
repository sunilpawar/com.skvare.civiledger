{* CiviLedger - Soft Credit Integrity Check *}
<div class="civiledger-wrap">
  <div class="civiledger-header">
    <h1><i class="crm-i fa-users"></i> Soft Credit Integrity Check</h1>
    <p>Flags contributions where the sum of soft credits exceeds the hard contribution amount.</p>
  </div>

  {* Filters *}
  <div class="civiledger-section civiledger-filters">
    <form method="get">
      {if $cms_type eq 'WordPress'}
        <input type="hidden" name="page" value="CiviCRM" />
        <input type="hidden" name="q" value="civicrm/civiledger/soft-credit-check" />
      {elseif $cms_type eq 'Joomla'}
        <input type="hidden" name="option" value="com_civicrm" />
        <input type="hidden" name="task" value="civicrm/civiledger/soft-credit-check" />
      {/if}
      <div class="filter-row">
        <label>Date From: <input type="date" name="date_from" value="{$filters.date_from}"></label>
        <label>Date To: <input type="date" name="date_to" value="{$filters.date_to}"></label>
        <label>Status:
          <select name="status_id">
            <option value="">— All —</option>
            {foreach from=$statusOptions key=val item=label}
              <option value="{$val}" {if $filters.status_id == $val}selected{/if}>{$label}</option>
            {/foreach}
          </select>
        </label>
        <button type="submit" class="button">Check</button>
      </div>
    </form>
  </div>

  {* Summary Banner *}
  <div class="integrity-summary {if $summary.total > 0}summary-bad{else}summary-good{/if}">
    {if $summary.total == 0}
      <i class="crm-i fa-check-circle"></i> <strong>All clear!</strong> No over-credited contributions found.
    {else}
      <i class="crm-i fa-exclamation-triangle"></i>
      <strong>{$summary.total} over-credited contribution(s) found.</strong>
      Total excess: <strong>{$summary.total_excess|crmMoney}</strong>
    {/if}
  </div>

  {* Line Chart *}
  {if $chartLabels neq '[]'}
    <div class="civiledger-section">
      <h2>
        Over-Credit Trend
        <span style="font-size:12px;font-weight:400;color:#6c757d;margin-left:8px">
          ({if $chartGranularity eq 'day'}daily{else}monthly{/if})
        </span>
      </h2>
      <canvas id="sc-chart" height="90"></canvas>
    </div>
  {/if}

  {* Results Table *}
  {if $rows}
    <div class="civiledger-section">
      <table class="civiledger-table">
        <thead>
          <tr>
            <th>Contribution</th>
            <th>Contact</th>
            <th>Date</th>
            <th>Status</th>
            <th class="text-right">Hard Amount</th>
            <th class="text-right">Soft Credit Total</th>
            <th class="text-right">Over-Credit</th>
            <th># Soft Credits</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$rows item=row}
            <tr class="sc-over-row">
              <td>
                <a target="_blank" href="{$row.contribution_url}">#{$row.contribution_id}</a>
              </td>
              <td>
                <a target="_blank" href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">
                  {$row.contact_name}
                </a>
              </td>
              <td style="white-space:nowrap">{$row.receive_date|crmDate}</td>
              <td>
                <span class="contrib-status-badge contrib-status-{$row.contribution_status_id}">
                  {$row.status_label|default:'—'}
                </span>
              </td>
              <td class="text-right">{$row.contribution_amount|crmMoney}</td>
              <td class="text-right text-red">{$row.soft_credit_total|crmMoney}</td>
              <td class="text-right">
                <span class="sc-excess-badge">+{$row.over_credit_amount|crmMoney}</span>
              </td>
              <td class="text-right">{$row.soft_credit_count}</td>
              <td>
                <button class="button small sc-expand-btn" data-cid="{$row.contribution_id}">
                  <i class="crm-i fa-search-plus"></i> Detail
                </button>
                <a target="_blank"
                   href="{crmURL p='civicrm/civiledger/audit-trail' q="reset=1&contribution_id=`$row.contribution_id`"}"
                   class="button small">Audit Trail</a>
              </td>
            </tr>
            {* Expandable soft credit detail row *}
            <tr class="sc-detail-row" id="sc-detail-{$row.contribution_id}" style="display:none">
              <td colspan="9" style="padding:0;background:#f8f9fa">
                <div style="padding:12px 16px">
                  <strong>Soft Credits for Contribution #{$row.contribution_id}:</strong>
                  {if $row.soft_credits}
                    <table class="civiledger-table" style="margin-top:8px">
                      <thead>
                        <tr><th>Soft Credit Contact</th><th>Type</th><th class="text-right">Amount</th></tr>
                      </thead>
                      <tbody>
                        {foreach from=$row.soft_credits item=sc}
                          <tr>
                            <td>
                              <a target="_blank" href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$sc.contact_id`"}">
                                {$sc.contact_name}
                              </a>
                            </td>
                            <td>{$sc.soft_credit_type|default:'—'}</td>
                            <td class="text-right">{$sc.amount|crmMoney}</td>
                          </tr>
                        {/foreach}
                      </tbody>
                      <tfoot>
                        <tr>
                          <td colspan="2"><strong>Total Soft Credits</strong></td>
                          <td class="text-right text-red"><strong>{$row.soft_credit_total|crmMoney}</strong></td>
                        </tr>
                        <tr>
                          <td colspan="2">Hard Contribution Amount</td>
                          <td class="text-right"><strong>{$row.contribution_amount|crmMoney}</strong></td>
                        </tr>
                      </tfoot>
                    </table>
                  {else}
                    <p style="color:#6c757d;font-style:italic">No soft credit rows found.</p>
                  {/if}
                </div>
              </td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  {/if}
</div>

{literal}
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
      document.querySelectorAll('canvas').forEach(function (c) {
        c.parentElement.innerHTML += '<p style="color:#dc3545;padding:20px">{ts}Chart.js failed to load. Check your internet connection or install Chart.js locally at js/chart.min.js.{/ts}</p>';
      });
      return;
    }
  var labels = {/literal}{$chartLabels}{literal};
  var hard   = {/literal}{$chartHard}{literal};
  var soft   = {/literal}{$chartSoft}{literal};
  var over   = {/literal}{$chartOver}{literal};

  var ctx = document.getElementById('sc-chart');
  if (!ctx || !labels.length) return;

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Hard Amount',
          data: hard,
          borderColor: 'rgba(40, 167, 69, 1)',
          backgroundColor: 'rgba(40, 167, 69, 0.08)',
          borderWidth: 2,
          pointRadius: 3,
          fill: false,
          tension: 0.3
        },
        {
          label: 'Soft Credit Total',
          data: soft,
          borderColor: 'rgba(255, 153, 0, 1)',
          backgroundColor: 'rgba(255, 153, 0, 0.08)',
          borderWidth: 2,
          pointRadius: 3,
          fill: false,
          tension: 0.3
        },
        {
          label: 'Over-Credit',
          data: over,
          borderColor: 'rgba(220, 53, 69, 1)',
          backgroundColor: 'rgba(220, 53, 69, 0.12)',
          borderWidth: 2,
          pointRadius: 3,
          fill: true,
          tension: 0.3
        }
      ]
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'top' },
        tooltip: {
          callbacks: {
            label: function(ctx) {
              return ctx.dataset.label + ': $' +
                ctx.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 });
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { callback: function(v) { return '$' + v.toLocaleString(); } }
        }
      }
    }
  });
});
</script>
<script>
(function($) {
  $(document).on('click', '.sc-expand-btn', function() {
    var cid = $(this).data('cid');
    var row = $('#sc-detail-' + cid);
    row.toggle();
    var icon = $(this).find('i');
    if (row.is(':visible')) {
      icon.removeClass('fa-search-plus').addClass('fa-search-minus');
    } else {
      icon.removeClass('fa-search-minus').addClass('fa-search-plus');
    }
  });
})(CRM.$);
</script>
<style>
.sc-over-row { background: #fff5f5; }
.sc-excess-badge {
  display: inline-block;
  background: #f8d7da; color: #721c24;
  font-size: 11px; font-weight: 700;
  padding: 2px 8px; border-radius: 10px;
}
.text-red { color: #721c24; }
.contrib-status-badge {
  display: inline-block; font-size:11px; font-weight:600;
  padding:2px 8px; border-radius:10px; white-space:nowrap;
  background:#e2e3e5; color:#383d41;
}
.contrib-status-1 { background:#d4edda; color:#155724; }
.contrib-status-2 { background:#fff3cd; color:#856404; }
.contrib-status-3 { background:#f8d7da; color:#721c24; }
.contrib-status-4 { background:#f8d7da; color:#721c24; }
.contrib-status-5 { background:#cfe2ff; color:#084298; }
.contrib-status-7 { background:#d1ecf1; color:#0c5460; }
</style>
{/literal}
