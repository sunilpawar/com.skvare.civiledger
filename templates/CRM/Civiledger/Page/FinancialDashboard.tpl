{* CiviLedger — Financial Dashboard (Charts) *}
<div class="civiledger-wrap">

  <div class="civiledger-header">
    <h1><i class="crm-i fa-bar-chart"></i> {ts}Financial Dashboard{/ts}</h1>
    <p>{ts}Visual overview of payment trends, account type balances, and cash position.{/ts}</p>
  </div>

  {* ── Date filter ── *}
  <div class="civiledger-filter-bar">
    <form method="get">
      {if $cms_type eq 'WordPress'}
        <input type="hidden" name="page"  value="CiviCRM" />
      {/if}
      <input type="hidden" name="q"     value="civicrm/civiledger/financial-dashboard" />
      <input type="hidden" name="reset" value="1" />
      <label>{ts}From{/ts}: <input type="date" name="date_from" value="{$dateFrom}"></label>
      <label>{ts}To{/ts}:   <input type="date" name="date_to"   value="{$dateTo}"></label>
      <button type="submit" class="button">{ts}Filter{/ts}</button>
    </form>
  </div>

  {* ── KPI stat cards ── *}
  <div class="civiledger-stats-row">
    <div class="civiledger-stat-card">
      <div class="stat-number">{$kpis.total_trxns|default:0}</div>
      <div class="stat-label">{ts}Total Transactions{/ts}</div>
    </div>
    <div class="civiledger-stat-card civiledger-stat-ok">
      <div class="stat-number">{$kpis.total_income|crmMoney}</div>
      <div class="stat-label">{ts}Total Income{/ts}</div>
    </div>
    <div class="civiledger-stat-card {if $kpis.total_refunds > 0}civiledger-stat-alert{/if}">
      <div class="stat-number">{$kpis.total_refunds|crmMoney}</div>
      <div class="stat-label">{ts}Total Refunds{/ts}</div>
    </div>
    <div class="civiledger-stat-card">
      <div class="stat-number">{$kpis.refund_count|default:0}</div>
      <div class="stat-label">{ts}Refund Count{/ts}</div>
    </div>
  </div>

  {* ── Row 1: Monthly trend (wide) + Doughnut ── *}
  <div class="fd-chart-row">

    <div class="civiledger-section fd-chart-card fd-wide">
      <h2><i class="crm-i fa-line-chart"></i> {ts}Monthly Payment Trend — Last 12 Months{/ts}</h2>
      <canvas id="fdTrendChart" height="100"></canvas>
    </div>

    <div class="civiledger-section fd-chart-card fd-narrow">
      <h2><i class="crm-i fa-pie-chart"></i> {ts}Balance by Category{/ts}
        <small style="font-size:12px;color:#888">{ts}(filtered period){/ts}</small>
      </h2>
      <canvas id="fdDoughnutChart"></canvas>
    </div>

  </div>

  {* ── Row 2: Account type bar chart (full width) ── *}
  <div class="civiledger-section">
    <h2><i class="crm-i fa-bar-chart"></i> {ts}Credits vs. Debits by Account Type{/ts}
      <small style="font-size:12px;color:#888">{ts}(filtered period){/ts}</small>
    </h2>
    <canvas id="fdTypeChart" height="80"></canvas>
  </div>

  {* ── Links ── *}
  <div class="civiledger-section">
    <h2>{ts}Quick Links{/ts}</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a href="{crmURL p='civicrm/civiledger/balance' q="reset=1&date_from=`$dateFrom`&date_to=`$dateTo`"}" class="button">{ts}Account Balance Dashboard{/ts}</a>
      <a href="{crmURL p='civicrm/civiledger/tax-mapping' q="reset=1&date_from=`$dateFrom`&date_to=`$dateTo`"}" class="button">{ts}Tax Mapping{/ts}</a>
      <a href="{crmURL p='civicrm/civiledger/mismatch-detector' q="reset=1&date_from=`$dateFrom`&date_to=`$dateTo`"}" class="button">{ts}Mismatch Detector{/ts}</a>
      <a href="{crmURL p='civicrm/civiledger/audit-log' q="reset=1"}" class="button">{ts}Audit Log{/ts}</a>
    </div>
  </div>

</div>

<style>
.fd-chart-row  { display:flex; gap:20px; margin-bottom:0; }
.fd-chart-card { flex:1; margin-bottom:20px; }
.fd-wide       { flex:2; }
.fd-narrow     { flex:1; min-width:260px; }
@media (max-width:768px) { .fd-chart-row { flex-direction:column; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') {
    document.querySelectorAll('canvas').forEach(function (c) {
      c.parentElement.innerHTML += '<p style="color:#dc3545;padding:20px">{ts}Chart.js failed to load. Check your internet connection or install Chart.js locally at js/chart.min.js.{/ts}</p>';
    });
    return;
  }

  var palette = {
    blue:       'rgba(54,162,235,0.8)',
    blueLight:  'rgba(54,162,235,0.3)',
    green:      'rgba(40,167,69,0.8)',
    red:        'rgba(220,53,69,0.7)',
    yellow:     'rgba(255,193,7,0.8)',
    purple:     'rgba(111,66,193,0.7)',
    teal:       'rgba(32,201,151,0.8)',
    orange:     'rgba(253,126,20,0.8)',
  };

  // 1. Monthly trend — line chart
  new Chart(document.getElementById('fdTrendChart'), {
    type: 'line',
    data: {
      labels: {$trendLabels},
      datasets: [
        {
          label: '{ts}Payments In{/ts}',
          data: {$trendPayments},
          borderColor: palette.green,
          backgroundColor: 'rgba(40,167,69,0.1)',
          tension: 0.3,
          fill: true,
        },
        {
          label: '{ts}Refunds{/ts}',
          data: {$trendRefunds},
          borderColor: palette.red,
          backgroundColor: 'rgba(220,53,69,0.08)',
          tension: 0.3,
          fill: true,
        },
        {
          label: '{ts}Net{/ts}',
          data: {$trendNet},
          borderColor: palette.blue,
          borderDash: [5, 3],
          tension: 0.3,
          fill: false,
          pointRadius: 3,
        }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'top' } },
      scales: { y: { beginAtZero: true } }
    }
  });

  // 2. Balance by category — doughnut
  new Chart(document.getElementById('fdDoughnutChart'), {
    type: 'doughnut',
    data: {
      labels: {$doughnutLabels},
      datasets: [{
        data: {$doughnutData},
        backgroundColor: [palette.teal, palette.blue, palette.green, palette.red, palette.yellow],
        borderWidth: 2,
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 14 } },
        tooltip: {
          callbacks: {
            label: function (ctx) {
              return ' ' + ctx.label + ': ' + ctx.parsed.toFixed(2);
            }
          }
        }
      }
    }
  });

  // 3. Account type grouped bar chart
  new Chart(document.getElementById('fdTypeChart'), {
    type: 'bar',
    data: {
      labels: {$typeLabels},
      datasets: [
        {
          label: '{ts}Credits (Cr){/ts}',
          data: {$typeCredits},
          backgroundColor: palette.green,
        },
        {
          label: '{ts}Debits (Dr){/ts}',
          data: {$typeDebits},
          backgroundColor: palette.red,
        }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'top' } },
      scales: { y: { beginAtZero: true } }
    }
  });

});
</script>
