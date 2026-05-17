{* CiviLedger - Recurring Contribution Health Monitor *}
<div class="civiledger-wrap">
  <div class="civiledger-header">
    <h1><i class="crm-i fa-refresh"></i> Recurring Contribution Health Monitor</h1>
    <p>Scans recurring series for overdue payments, stuck statuses, unresolved failures, and amount drift.</p>
  </div>

  {* Filters *}
  <div class="civiledger-section civiledger-filters">
    <form method="get">
      {if $cms_type eq 'WordPress'}
        <input type="hidden" name="page" value="CiviCRM" />
        <input type="hidden" name="q" value="civicrm/civiledger/recurring-health" />
      {elseif $cms_type eq 'Joomla'}
        <input type="hidden" name="option" value="com_civicrm" />
        <input type="hidden" name="task" value="civicrm/civiledger/recurring-health" />
      {/if}
      <div class="filter-row">
        <label>Series Start From: <input type="date" name="date_from" value="{$filters.date_from}"></label>
        <label>Series Start To: <input type="date" name="date_to" value="{$filters.date_to}"></label>
        <label>Frequency:
          <select name="frequency_unit">
            <option value="">— All —</option>
            <option value="day"   {if $filters.frequency_unit eq 'day'}selected{/if}>Daily</option>
            <option value="week"  {if $filters.frequency_unit eq 'week'}selected{/if}>Weekly</option>
            <option value="month" {if $filters.frequency_unit eq 'month'}selected{/if}>Monthly</option>
            <option value="year"  {if $filters.frequency_unit eq 'year'}selected{/if}>Yearly</option>
          </select>
        </label>
        <label>Payment Method:
          <select name="payment_instrument_id">
            <option value="">— All —</option>
            {foreach from=$paymentInstruments key=val item=label}
              <option value="{$val}" {if $filters.payment_instrument_id == $val}selected{/if}>{$label}</option>
            {/foreach}
          </select>
        </label>
        <label>Recur Status:
          <select name="status_id">
            <option value="">— All —</option>
            {foreach from=$statusOptions key=val item=label}
              <option value="{$val}" {if $filters.status_id == $val}selected{/if}>{$label}</option>
            {/foreach}
          </select>
        </label>
        <label>Gap Analysis Month: <input type="month" name="gap_month" value="{$filters.gap_month}"></label>
        <button type="submit" class="button">Scan</button>
      </div>
    </form>
  </div>

  {* Summary Banner *}
  <div class="integrity-summary {if $summary.total > 0}summary-bad{else}summary-good{/if}">
    {if $summary.total == 0}
      <i class="crm-i fa-check-circle"></i> <strong>All clear!</strong> No recurring health issues found.
    {else}
      <i class="crm-i fa-exclamation-triangle"></i>
      <strong>{$summary.total} issue(s)</strong> across recurring series &nbsp;|&nbsp;
      <span class="rhm-badge rhm-badge-red">{$summary.overdue_series} Overdue</span>
      <span class="rhm-badge rhm-badge-yellow">{$summary.stuck_in_progress} Stuck</span>
      <span class="rhm-badge rhm-badge-orange">{$summary.unresolved_failures} Failures</span>
      <span class="rhm-badge rhm-badge-grey">{$summary.orphaned} Orphaned</span>
      <span class="rhm-badge rhm-badge-blue">{$summary.amount_drift} Drift</span>
    {/if}
  </div>

  {* Charts row *}
  {if $summary.total > 0}
  <div class="rhm-charts-row">

    <div class="rhm-chart-block" style="flex:1;min-width:260px;max-width:320px">
      <h3 class="rhm-chart-title">Issue Distribution</h3>
      <canvas id="rhm-doughnut" height="220"></canvas>
    </div>

    <div class="rhm-chart-block" style="flex:3;min-width:380px">
      <h3 class="rhm-chart-title">Expected vs. Actual Monthly Recurring Revenue <span class="rhm-chart-sub">(last 12 months, frequency-adjusted)</span></h3>
      <canvas id="rhm-mrr" height="110"></canvas>
      <p class="rhm-chart-note">Expected = frequency-normalized monthly value per active series (monthly ÷ interval, yearly ÷ 12, weekly × 52/12). Gap analysis below identifies which series missed payment.</p>
    </div>

  </div>

  {* Processor failure chart — only shown when 2+ processors *}
  {if $hasProcessorChart}
  <div class="rhm-charts-row">
    <div class="rhm-chart-block" style="flex:1;min-width:300px;max-width:480px">
      <h3 class="rhm-chart-title">Failure Rate by Payment Processor <span class="rhm-chart-sub">(last 12 months)</span></h3>
      <canvas id="rhm-processor" height="140"></canvas>
    </div>
    <div style="flex:2"></div>
  </div>
  {/if}

  {* Monthly success/failed per processor line chart *}
  {if $hasProcessorLineChart}
  <div class="rhm-charts-row">
    <div class="rhm-chart-block" style="flex:1;min-width:500px">
      <h3 class="rhm-chart-title">
        Recurring Payments per Processor
        <span class="rhm-chart-sub">(last 12 months — solid = success, dashed = failed)</span>
      </h3>
      <canvas id="rhm-proc-line" height="110"></canvas>
    </div>
  </div>
  {/if}
  {/if}

  {* ── Gap Analysis ── *}
  <div class="civiledger-section">
    <h2 class="rhm-section-head" style="cursor:pointer">
      <button class="rhm-toggle button small" data-target="rhm-gap-body" style="margin-right:8px">
        <span class="rhm-caret">▼</span>
      </button>
      <span class="issue-count {if $gapRowsCount > 0}count-bad{else}count-ok{/if}">{$gapRowsCount}</span>
      MRR Gap Analysis — active series with no payment in {$filters.gap_month}
      {if $gapRowsCount > 0}
        <span class="rhm-badge rhm-badge-blue" style="margin-left:10px">
          ~{$gapMissedTotal|crmMoney} missed MRR
        </span>
      {/if}
    </h2>
    <div id="rhm-gap-body">
      <p class="rhm-chart-note" style="margin-bottom:10px">
        Lists every active recurring series that had no completed contribution in <strong>{$filters.gap_month}</strong>.
        Non-monthly series (annual, quarterly) may legitimately skip months — use the Frequency column to distinguish true gaps from scheduled absences.
      </p>
      {if $gapRows}
        <table class="civiledger-table">
          <thead>
            <tr>
              <th>Recurring #</th>
              <th>Contact</th>
              <th>Processor</th>
              <th>Status</th>
              <th>Frequency</th>
              <th class="text-right">Scheduled Amt</th>
              <th class="text-right">Monthly Value</th>
              <th>Last Payment</th>
              <th>Start Date</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$gapRows item=row}
              <tr class="{if $row.frequency_unit eq 'month'}row-mismatch{/if}">
                <td><a target="_blank" href="{$row.recur_url}">#{$row.recur_id}</a></td>
                <td><a target="_blank" href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.contact_name}</a></td>
                <td>{$row.processor_name|default:'—'}</td>
                <td><span class="contrib-status-badge contrib-status-{$row.contribution_status_id}">{$row.status_label|default:'—'}</span></td>
                <td>
                  Every {$row.frequency_interval} {$row.frequency_unit}
                  {if $row.frequency_unit neq 'month'}<span class="rhm-badge rhm-badge-grey" style="font-size:10px;margin-left:4px">non-monthly</span>{/if}
                </td>
                <td class="text-right">{$row.amount|crmMoney:$row.currency}</td>
                <td class="text-right"><strong>{$row.monthly_value|crmMoney:$row.currency}</strong></td>
                <td>{if $row.last_payment_date}{$row.last_payment_date|crmDate}{else}<em style="color:#aaa">Never</em>{/if}</td>
                <td>{$row.start_date|crmDate}</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      {else}
        <p class="rhm-empty"><i class="crm-i fa-check-circle"></i> All active series collected a payment in {$filters.gap_month}.</p>
      {/if}
    </div>
  </div>

  {* ── Issue 1: Overdue Series ── *}
  <div class="civiledger-section">
    <h2 class="rhm-section-head" style="cursor:pointer">
      <button class="rhm-toggle button small" data-target="rhm-overdue-body" style="margin-right:8px">
        <span class="rhm-caret">▼</span>
      </button>
      <span class="issue-count {if $summary.overdue_series > 0}count-bad{else}count-ok{/if}">{$summary.overdue_series}</span>
      Overdue Series
      <span class="help-tip" title="Active recurring series where the next expected payment date has passed with no payment recorded.">?</span>
    </h2>
    <div id="rhm-overdue-body">
      {if $results.overdue_series}
        {* Days-late distribution bar chart *}
        <div style="max-width:420px;margin-bottom:14px">
          <canvas id="rhm-overdue-bar" height="100"></canvas>
        </div>
        <table class="civiledger-table">
          <thead>
            <tr>
              <th>Recurring #</th>
              <th>Contact</th>
              <th>Processor</th>
              <th>Status</th>
              <th>Frequency</th>
              <th class="text-right">Amount</th>
              <th>Start Date</th>
              <th>Last Payment</th>
              <th>Expected Next</th>
              <th class="text-right">Days Overdue</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$results.overdue_series item=row}
              <tr class="{if $row.days_overdue > 30}row-critical{else}row-mismatch{/if}">
                <td><a target="_blank" href="{$row.recur_url}">#{$row.recur_id}</a></td>
                <td><a target="_blank" href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.contact_name}</a></td>
                <td>{$row.processor_name|default:'—'}</td>
                <td><span class="contrib-status-badge contrib-status-{$row.contribution_status_id}">{$row.status_label|default:'—'}</span></td>
                <td>Every {$row.frequency_interval} {$row.frequency_unit}</td>
                <td class="text-right">{$row.amount|crmMoney:$row.currency}</td>
                <td>{$row.start_date|crmDate}</td>
                <td>{$row.last_payment_date|crmDate}</td>
                <td>{$row.expected_next_date|crmDate}</td>
                <td class="text-right">
                  <span class="rhm-overdue-days {if $row.days_overdue > 30}rhm-days-critical{elseif $row.days_overdue > 7}rhm-days-warn{else}rhm-days-mild{/if}">
                    {$row.days_overdue}d
                  </span>
                </td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      {else}
        <p class="rhm-empty"><i class="crm-i fa-check-circle"></i> No overdue series found.</p>
      {/if}
    </div>
  </div>

  {* ── Issue 2: Stuck In Progress ── *}
  <div class="civiledger-section">
    <h2 class="rhm-section-head">
      <button class="rhm-toggle button small" data-target="rhm-stuck-body" style="margin-right:8px">
        <span class="rhm-caret">▼</span>
      </button>
      <span class="issue-count {if $summary.stuck_in_progress > 0}count-bad{else}count-ok{/if}">{$summary.stuck_in_progress}</span>
      Stuck "In Progress" — all installments paid
    </h2>
    <div id="rhm-stuck-body">
      {if $results.stuck_in_progress}
        <table class="civiledger-table">
          <thead>
            <tr>
              <th>Recurring #</th>
              <th>Contact</th>
              <th>Processor</th>
              <th>Status</th>
              <th class="text-right">Amount</th>
              <th class="text-right">Installments</th>
              <th class="text-right">Completed</th>
              <th>Start Date</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$results.stuck_in_progress item=row}
              <tr>
                <td><a target="_blank" href="{$row.recur_url}">#{$row.recur_id}</a></td>
                <td><a target="_blank" href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.contact_name}</a></td>
                <td>{$row.processor_name|default:'—'}</td>
                <td><span class="contrib-status-badge contrib-status-{$row.contribution_status_id}">{$row.status_label|default:'—'}</span></td>
                <td class="text-right">{$row.amount|crmMoney:$row.currency}</td>
                <td class="text-right">{$row.installments}</td>
                <td class="text-right text-green">{$row.completed_count}</td>
                <td>{$row.start_date|crmDate}</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      {else}
        <p class="rhm-empty"><i class="crm-i fa-check-circle"></i> No stuck series found.</p>
      {/if}
    </div>
  </div>

  {* ── Issue 3: Unresolved Failures ── *}
  <div class="civiledger-section">
    <h2 class="rhm-section-head">
      <button class="rhm-toggle button small" data-target="rhm-failures-body" style="margin-right:8px">
        <span class="rhm-caret">▼</span>
      </button>
      <span class="issue-count {if $summary.unresolved_failures > 0}count-bad{else}count-ok{/if}">{$summary.unresolved_failures}</span>
      Unresolved Failures — no successful payment after last failure
    </h2>
    <div id="rhm-failures-body">
      {if $results.unresolved_failures}
        <table class="civiledger-table">
          <thead>
            <tr>
              <th>Recurring #</th>
              <th>Contact</th>
              <th>Processor</th>
              <th>Status</th>
              <th class="text-right">Amount</th>
              <th class="text-right">Failures</th>
              <th>Last Failure</th>
              <th>Last Success</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$results.unresolved_failures item=row}
              <tr class="row-critical">
                <td><a target="_blank" href="{$row.recur_url}">#{$row.recur_id}</a></td>
                <td><a target="_blank" href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.contact_name}</a></td>
                <td>{$row.processor_name|default:'—'}</td>
                <td><span class="contrib-status-badge contrib-status-{$row.contribution_status_id}">{$row.status_label|default:'—'}</span></td>
                <td class="text-right">{$row.amount|crmMoney:$row.currency}</td>
                <td class="text-right text-red">{$row.failure_count}</td>
                <td>{$row.last_failure_date|crmDate}</td>
                <td>{if $row.last_success_date}{$row.last_success_date|crmDate}{else}<em style="color:#aaa">Never</em>{/if}</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      {else}
        <p class="rhm-empty"><i class="crm-i fa-check-circle"></i> No unresolved failures found.</p>
      {/if}
    </div>
  </div>

  {* ── Issue 4: Orphaned Recurring ── *}
  <div class="civiledger-section">
    <h2 class="rhm-section-head">
      <button class="rhm-toggle button small" data-target="rhm-orphaned-body" style="margin-right:8px">
        <span class="rhm-caret">▼</span>
      </button>
      <span class="issue-count {if $summary.orphaned > 0}count-bad{else}count-ok{/if}">{$summary.orphaned}</span>
      Orphaned Recurring — no linked contributions
    </h2>
    <div id="rhm-orphaned-body">
      {if $results.orphaned}
        <table class="civiledger-table">
          <thead>
            <tr>
              <th>Recurring #</th>
              <th>Contact</th>
              <th>Processor</th>
              <th>Frequency</th>
              <th class="text-right">Amount</th>
              <th>Start Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$results.orphaned item=row}
              <tr>
                <td><a target="_blank" href="{$row.recur_url}">#{$row.recur_id}</a></td>
                <td><a target="_blank" href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.contact_name}</a></td>
                <td>{$row.processor_name|default:'—'}</td>
                <td>Every {$row.frequency_interval} {$row.frequency_unit}</td>
                <td class="text-right">{$row.amount|crmMoney:$row.currency}</td>
                <td>{$row.start_date|crmDate}</td>
                <td><span class="contrib-status-badge contrib-status-{$row.contribution_status_id}">{$row.status_label|default:'—'}</span></td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      {else}
        <p class="rhm-empty"><i class="crm-i fa-check-circle"></i> No orphaned series found.</p>
      {/if}
    </div>
  </div>

  {* ── Issue 5: Amount Drift ── *}
  <div class="civiledger-section">
    <h2 class="rhm-section-head">
      <button class="rhm-toggle button small" data-target="rhm-drift-body" style="margin-right:8px">
        <span class="rhm-caret">▼</span>
      </button>
      <span class="issue-count {if $summary.amount_drift > 0}count-bad{else}count-ok{/if}">{$summary.amount_drift}</span>
      Amount Drift — installment amount differs from recurring template
    </h2>
    <div id="rhm-drift-body">
      {if $results.amount_drift}
        <table class="civiledger-table">
          <thead>
            <tr>
              <th>Recurring #</th>
              <th>Contact</th>
              <th>Status</th>
              <th>Frequency</th>
              <th class="text-right">Scheduled</th>
              <th class="text-right">Actual Paid</th>
              <th class="text-right">Drift</th>
              <th>Contribution</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$results.amount_drift item=row}
              <tr>
                <td><a target="_blank" href="{$row.recur_url}">#{$row.recur_id}</a></td>
                <td><a target="_blank" href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.contact_name}</a></td>
                <td><span class="contrib-status-badge contrib-status-{$row.contribution_status_id}">{$row.status_label|default:'—'}</span></td>
                <td>Every {$row.frequency_interval} {$row.frequency_unit}</td>
                <td class="text-right">{$row.scheduled_amount|crmMoney:$row.currency}</td>
                <td class="text-right">{$row.actual_amount|crmMoney:$row.currency}</td>
                <td class="text-right text-red">Δ {$row.drift_amount|crmMoney:$row.currency}</td>
                <td><a target="_blank" href="{$row.contribution_url}">#{$row.contribution_id}</a></td>
                <td>{$row.receive_date|crmDate}</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      {else}
        <p class="rhm-empty"><i class="crm-i fa-check-circle"></i> No amount drift found.</p>
      {/if}
    </div>
  </div>

</div>

{literal}
<script>
window.addEventListener('load', function() {

  // Chart 1: Issue Distribution Doughnut
  var dCtx = document.getElementById('rhm-doughnut');
  if (dCtx) {
    new Chart(dCtx, {
      type: 'doughnut',
      data: {
        labels: {/literal}{$chartDoughnutLabels}{literal},
        datasets: [{
          data: {/literal}{$chartDoughnutData}{literal},
          backgroundColor: [
            'rgba(220,53,69,0.8)',
            'rgba(255,193,7,0.8)',
            'rgba(255,100,0,0.8)',
            'rgba(108,117,125,0.8)',
            'rgba(23,162,184,0.8)'
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'right' },
          tooltip: {
            callbacks: {
              label: function(c) { return c.label + ': ' + c.parsed; }
            }
          }
        }
      }
    });
  }

  // Chart 2: Expected vs Actual MRR
  var lCtx = document.getElementById('rhm-mrr');
  if (lCtx) {
    new Chart(lCtx, {
      type: 'line',
      data: {
        labels: {/literal}{$chartMonthLabels}{literal},
        datasets: [
          {
            label: 'Expected (active series)',
            data: {/literal}{$chartExpected}{literal},
            borderColor: 'rgba(0,123,255,1)',
            backgroundColor: 'rgba(0,123,255,0.06)',
            borderWidth: 2, pointRadius: 3, fill: true, tension: 0.3
          },
          {
            label: 'Actual Collected',
            data: {/literal}{$chartActual}{literal},
            borderColor: 'rgba(40,167,69,1)',
            backgroundColor: 'rgba(40,167,69,0.06)',
            borderWidth: 2, pointRadius: 3, fill: true, tension: 0.3
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
              label: function(c) {
                return c.dataset.label + ': $' +
                  c.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 });
              }
            }
          }
        },
        scales: {
          y: { beginAtZero: true, ticks: { callback: function(v) { return '$' + v.toLocaleString(); } } }
        }
      }
    });
  }

  // Chart 3: Days Overdue Buckets bar
  var bCtx = document.getElementById('rhm-overdue-bar');
  if (bCtx) {
    new Chart(bCtx, {
      type: 'bar',
      data: {
        labels: {/literal}{$chartBucketLabels}{literal},
        datasets: [{
          label: 'Series Count',
          data: {/literal}{$chartBucketData}{literal},
          backgroundColor: [
            'rgba(255,193,7,0.8)',
            'rgba(255,133,0,0.8)',
            'rgba(220,53,69,0.8)',
            'rgba(100,0,0,0.85)'
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  // Chart 4: Processor Failure Rate horizontal bar
  var pCtx = document.getElementById('rhm-processor');
  if (pCtx) {
    new Chart(pCtx, {
      type: 'bar',
      data: {
        labels: {/literal}{$chartProcLabels}{literal},
        datasets: [{
          label: 'Failure Rate (%)',
          data: {/literal}{$chartProcRates}{literal},
          backgroundColor: 'rgba(220,53,69,0.75)',
          borderWidth: 1
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(c) { return c.parsed.x + '% failure rate'; }
            }
          }
        },
        scales: {
          x: { beginAtZero: true, max: 100,
               ticks: { callback: function(v) { return v + '%'; } } }
        }
      }
    });
  }

  // Chart 5: Monthly success/failed per processor (multi-line)
  var plCtx = document.getElementById('rhm-proc-line');
  if (plCtx) {
    new Chart(plCtx, {
      type: 'line',
      data: {
        labels: {/literal}{$chartProcLineLabels}{literal},
        datasets: {/literal}{$chartProcLineDatasets}{literal}
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top' },
          tooltip: {
            callbacks: {
              label: function(c) {
                return c.dataset.label + ': ' + c.parsed.y;
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0 },
            title: { display: true, text: 'Contributions' }
          }
        }
      }
    });
  }

  // Collapsible issue sections
  document.querySelectorAll('.rhm-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var target = document.getElementById(btn.dataset.target);
      if (!target) return;
      var hidden = target.style.display === 'none' || target.style.display === '';
      target.style.display = hidden ? 'block' : 'none';
      btn.querySelector('.rhm-caret').textContent = hidden ? '▲' : '▼';
    });
  });

});
</script>
<style>
.rhm-charts-row {
  display: flex; gap: 18px; flex-wrap: wrap; margin-bottom: 18px;
}
.rhm-chart-block {
  background: #fff; border: 1px solid #dee2e6; border-radius: 6px;
  padding: 14px 16px;
}
.rhm-chart-title {
  font-size: 13px; font-weight: 700; margin: 0 0 10px; color: #212529;
}
.rhm-chart-sub { font-size: 11px; font-weight: 400; color: #6c757d; margin-left: 4px; }
.rhm-chart-note { font-size: 11px; color: #6c757d; margin: 6px 0 0; font-style: italic; }

.rhm-section-head { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.rhm-toggle { padding: 1px 7px !important; font-size: 11px !important; }

.rhm-badge {
  display: inline-block; font-size: 11px; font-weight: 700;
  padding: 2px 9px; border-radius: 10px; margin: 0 2px;
}
.rhm-badge-red    { background: #f8d7da; color: #721c24; }
.rhm-badge-yellow { background: #fff3cd; color: #856404; }
.rhm-badge-orange { background: #ffe5cc; color: #7c3c00; }
.rhm-badge-grey   { background: #e2e3e5; color: #383d41; }
.rhm-badge-blue   { background: #cfe2ff; color: #084298; }

.rhm-overdue-days {
  display: inline-block; font-size: 11px; font-weight: 700;
  padding: 2px 8px; border-radius: 10px;
}
.rhm-days-mild     { background: #fff3cd; color: #856404; }
.rhm-days-warn     { background: #ffe5cc; color: #7c3c00; }
.rhm-days-critical { background: #f8d7da; color: #721c24; }

.rhm-empty { color: #6c757d; font-style: italic; padding: 8px 0; }

.text-green { color: #155724; }
.text-red   { color: #721c24; }

.contrib-status-badge {
  display: inline-block; font-size: 11px; font-weight: 600;
  padding: 2px 8px; border-radius: 10px; white-space: nowrap;
  background: #e2e3e5; color: #383d41;
}
.contrib-status-1 { background: #d4edda; color: #155724; }
.contrib-status-2 { background: #fff3cd; color: #856404; }
.contrib-status-3 { background: #f8d7da; color: #721c24; }
.contrib-status-4 { background: #f8d7da; color: #721c24; }
.contrib-status-5 { background: #cfe2ff; color: #084298; }
</style>
{/literal}
