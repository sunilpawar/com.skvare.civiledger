{* CiviLedger - Donor Cohort Retention Heatmap *}
<div class="civiledger-wrap">
  <div class="civiledger-header">
    <h1><i class="crm-i fa-th"></i> Donor Cohort Retention</h1>
    <p>Each row is a group of donors who made their <strong>first-ever gift</strong> in that month. Percentages show how many of that group gave again in each subsequent month.</p>
  </div>

  {* Filters *}
  <div class="civiledger-section civiledger-filters">
    <form method="get">
      {if $cms_type eq 'WordPress'}
        <input type="hidden" name="page" value="CiviCRM" />
        <input type="hidden" name="q" value="civicrm/civiledger/cohort-retention" />
      {elseif $cms_type eq 'Joomla'}
        <input type="hidden" name="option" value="com_civicrm" />
        <input type="hidden" name="task" value="civicrm/civiledger/cohort-retention" />
      {/if}
      <div class="filter-row">
        <label>Cohorts From: <input type="month" name="cohort_from" value="{$filters.cohort_from}"></label>
        <label>Cohorts To: <input type="month" name="cohort_to" value="{$filters.cohort_to}"></label>
        <label>Track Months:
          <select name="max_months">
            {foreach from=[6,9,12,18,24] item=mo}
              <option value="{$mo}" {if $filters.max_months == $mo}selected{/if}>{$mo} months</option>
            {/foreach}
          </select>
        </label>
        <label>Financial Type:
          <select name="financial_type_id">
            <option value="">— All —</option>
            {foreach from=$financialTypes key=val item=label}
              <option value="{$val}" {if $filters.financial_type_id == $val}selected{/if}>{$label}</option>
            {/foreach}
          </select>
        </label>
        <button type="submit" class="button">Run</button>
      </div>
    </form>
  </div>

  {if empty($matrix)}
    <div class="civiledger-section">
      <p class="rhm-empty"><i class="crm-i fa-info-circle"></i> No donor cohorts found for the selected period.</p>
    </div>
  {else}

  {* KPI Cards *}
  <div class="cohort-kpi-row">
    <div class="cohort-kpi-card">
      <div class="cohort-kpi-value">{$totalDonors}</div>
      <div class="cohort-kpi-label">New Donors in Period</div>
    </div>
    <div class="cohort-kpi-card {if $secondGiftRate !== null}{if $secondGiftRate >= 40}kpi-good{elseif $secondGiftRate >= 20}kpi-warn{else}kpi-bad{/if}{/if}">
      <div class="cohort-kpi-value">{if $secondGiftRate !== null}{$secondGiftRate}%{else}—{/if}</div>
      <div class="cohort-kpi-label">Avg Second-Gift Rate (M+1)</div>
    </div>
    <div class="cohort-kpi-card {if $thirdMonthRate !== null}{if $thirdMonthRate >= 30}kpi-good{elseif $thirdMonthRate >= 15}kpi-warn{else}kpi-bad{/if}{/if}">
      <div class="cohort-kpi-value">{if $thirdMonthRate !== null}{$thirdMonthRate}%{else}—{/if}</div>
      <div class="cohort-kpi-label">Avg 3-Month Retention (M+3)</div>
    </div>
    {if $yearRate !== null}
    <div class="cohort-kpi-card {if $yearRate >= 20}kpi-good{elseif $yearRate >= 10}kpi-warn{else}kpi-bad{/if}">
      <div class="cohort-kpi-value">{$yearRate}%</div>
      <div class="cohort-kpi-label">Avg 12-Month Retention (M+12)</div>
    </div>
    {/if}
    {if $bestCohort neq '—'}
    <div class="cohort-kpi-card kpi-good">
      <div class="cohort-kpi-value">{$bestCohort}</div>
      <div class="cohort-kpi-label">Best Cohort at M+1 ({$bestCohortRate}%)</div>
    </div>
    {/if}
  </div>

  {* How to read this chart *}
  <div class="civiledger-section cohort-guide-wrap">
    <button class="cohort-guide-toggle" id="cohort-guide-btn">
      <i class="crm-i fa-question-circle"></i> How to read this chart
      <span class="cohort-guide-caret">▼</span>
    </button>
    <div id="cohort-guide-body" style="display:none">

      <div class="cohort-guide-grid">

        <div class="cohort-guide-block">
          <h4><i class="crm-i fa-arrows-h"></i> Reading across a row</h4>
          <p>Each row is one cohort — all donors whose <strong>first-ever gift</strong> landed in that month. Reading left to right shows you the <strong>decay curve</strong> for that group.</p>
          <ul>
            <li><strong>M+0 (First Gift)</strong> is always dark green — 100% of the cohort gave, because that <em>is</em> their first gift.</li>
            <li><strong>M+1</strong> is the hardest jump: did they come back the very next month? This is your <em>second-gift rate</em> — the most predictive indicator of long-term loyalty.</li>
            <li>A healthy curve drops sharply from M+0 to M+1, then <em>flattens</em>. A flat tail means a loyal retained core has formed.</li>
            <li>A curve that keeps falling every month with no flattening means donors are leaving steadily — no one is converting into a repeat giver.</li>
          </ul>
        </div>

        <div class="cohort-guide-block">
          <h4><i class="crm-i fa-arrows-v"></i> Reading down a column</h4>
          <p>Each column is one month offset. Reading top to bottom shows whether your retention <strong>at that stage is improving over time</strong>.</p>
          <ul>
            <li>If the <strong>M+1 column</strong> gets greener as you go down (more recent cohorts), your second-gift conversion is improving — perhaps a new welcome series, stewardship program, or campaign is working.</li>
            <li>If it gets redder, something changed — a lapsed email cadence, a pricing or program shift, or a change in acquisition source.</li>
            <li>The <strong>Average row</strong> at the bottom is your benchmark. Any cohort row that's consistently greener than average is a strong cohort worth investigating — what made those donors different?</li>
          </ul>
        </div>

        <div class="cohort-guide-block">
          <h4><i class="crm-i fa-lightbulb-o"></i> What to act on</h4>
          <ul>
            <li><strong>M+1 is red across all cohorts</strong> — your onboarding experience needs attention. Almost no one is coming back for a second gift. Focus here first; it has the highest leverage.</li>
            <li><strong>M+1 is OK but M+3 drops sharply</strong> — donors are returning once but not sticking. This often means the second ask is poorly timed or irrelevant. Try a mid-cycle impact story instead of a straight ask.</li>
            <li><strong>One cohort stands out as much greener</strong> — that month's donors were acquired differently, received different messaging, or responded to a specific campaign. Dig into what acquisition source or appeal that cohort came from and replicate it.</li>
            <li><strong>Recent rows are mostly blank (striped)</strong> — those cohorts are too new to have retention data yet. Cohorts need at least M+1 of history to be meaningful; the <em>Cohorts To</em> filter defaults to 2 months ago to avoid this.</li>
          </ul>
        </div>

        <div class="cohort-guide-block">
          <h4><i class="crm-i fa-bar-chart"></i> Benchmarks</h4>
          <p>These are rough nonprofit sector averages. Your numbers will vary by mission, donor base, and giving culture.</p>
          <table class="cohort-bench-table">
            <thead><tr><th>Metric</th><th>Strong</th><th>Average</th><th>Needs work</th></tr></thead>
            <tbody>
              <tr><td>Second-gift rate (M+1)</td><td class="bench-good">≥ 40%</td><td class="bench-warn">20–39%</td><td class="bench-bad">&lt; 20%</td></tr>
              <tr><td>3-month retention (M+3)</td><td class="bench-good">≥ 30%</td><td class="bench-warn">15–29%</td><td class="bench-bad">&lt; 15%</td></tr>
              <tr><td>12-month retention (M+12)</td><td class="bench-good">≥ 20%</td><td class="bench-warn">10–19%</td><td class="bench-bad">&lt; 10%</td></tr>
            </tbody>
          </table>
          <p style="margin-top:8px;font-size:11px;color:#6c757d">Recurring (monthly) donors show dramatically higher M+3 and M+12 figures (often 70–90%) because their gifts are automated. Use the <em>Financial Type</em> filter to analyze recurring vs. one-time donors separately for a more meaningful comparison.</p>
        </div>

        <div class="cohort-guide-block">
          <h4><i class="crm-i fa-filter"></i> Using the filters</h4>
          <ul>
            <li><strong>Cohorts From / To</strong> — limits which first-gift months appear as rows. Narrow this to a specific campaign window (e.g., a year-end appeal) to measure only those donors.</li>
            <li><strong>Track Months</strong> — how many months forward each row extends. 12 is a good default. Use 24 to see whether your most loyal cohorts are still giving two years later.</li>
            <li><strong>Financial Type</strong> — filters both the first-gift detection <em>and</em> the subsequent-gift tracking to a single fund. Use this to ask "Of donors who first gave to the General Fund, how many gave to the General Fund again?" — a fund-specific loyalty metric.</li>
          </ul>
        </div>

      </div>
    </div>
  </div>

  {* Legend *}
  <div class="cohort-legend">
    <span class="cohort-legend-title">Retention %:</span>
    <span class="cohort-cell cohort-m0 cohort-legend-cell">First gift</span>
    <span class="cohort-cell cohort-zero cohort-legend-cell">0%</span>
    <span class="cohort-cell cohort-p10 cohort-legend-cell">1–10%</span>
    <span class="cohort-cell cohort-p20 cohort-legend-cell">11–20%</span>
    <span class="cohort-cell cohort-p35 cohort-legend-cell">21–35%</span>
    <span class="cohort-cell cohort-p50 cohort-legend-cell">36–50%</span>
    <span class="cohort-cell cohort-p70 cohort-legend-cell">51–70%</span>
    <span class="cohort-cell cohort-p100 cohort-legend-cell">71–100%</span>
    <span class="cohort-cell cohort-future cohort-legend-cell">Future</span>
  </div>

  {* Heatmap Table *}
  <div class="civiledger-section">
    <div class="cohort-table-wrap">
      <table class="cohort-table">
        <thead>
          <tr>
            <th class="cohort-th-month">Cohort</th>
            <th class="cohort-th-size text-right">Size</th>
            {foreach from=$monthOffsets item=m}
              <th class="cohort-th-offset {if $m eq 0}cohort-th-m0{/if}">
                {if $m eq 0}First Gift{else}M+{$m}{/if}
              </th>
            {/foreach}
          </tr>
        </thead>
        <tbody>
          {foreach from=$matrix item=row}
            <tr>
              <td class="cohort-month-cell">{$row.cohort_month}</td>
              <td class="cohort-size-cell text-right">{$row.cohort_size}</td>
              {foreach from=$row.cells item=cell}
                <td class="cohort-cell {$cell.color_class}" title="{$cell.tooltip}">
                  {$cell.pct_display}
                </td>
              {/foreach}
            </tr>
          {/foreach}
        </tbody>
        <tfoot>
          <tr class="cohort-avg-row">
            <td class="cohort-month-cell"><strong>Average</strong></td>
            <td class="cohort-size-cell text-right"><strong>{$totalDonors}</strong></td>
            {foreach from=$avgByMonth item=avg}
              <td class="cohort-cell {$avg.color_class}">
                <strong>{$avg.pct_display}</strong>
              </td>
            {/foreach}
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="rhm-chart-note" style="margin-top:8px">
      Hover over any cell for the exact donor count. The <strong>Average</strong> row shows the mean retention rate across all cohorts for each month offset.
      Recent cohorts have blank cells for months that have not yet occurred.
    </p>
  </div>

  {/if}

</div>

{literal}
<script>
(function() {
  var btn  = document.getElementById('cohort-guide-btn');
  var body = document.getElementById('cohort-guide-body');
  if (btn && body) {
    btn.addEventListener('click', function() {
      var open = body.style.display !== 'none';
      body.style.display = open ? 'none' : 'block';
      btn.querySelector('.cohort-guide-caret').textContent = open ? '▼' : '▲';
    });
  }
})();
</script>
<style>
/* KPI cards */
.cohort-kpi-row {
  display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 18px;
}
.cohort-kpi-card {
  background: #fff; border: 1px solid #dee2e6; border-radius: 6px;
  padding: 14px 20px; min-width: 140px; text-align: center;
  border-top: 4px solid #6c757d;
}
.cohort-kpi-card.kpi-good  { border-top-color: #28a745; }
.cohort-kpi-card.kpi-warn  { border-top-color: #ffc107; }
.cohort-kpi-card.kpi-bad   { border-top-color: #dc3545; }
.cohort-kpi-value { font-size: 26px; font-weight: 700; color: #212529; line-height: 1.2; }
.cohort-kpi-label { font-size: 11px; color: #6c757d; margin-top: 4px; }

/* Legend */
.cohort-legend {
  display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
  margin-bottom: 14px; font-size: 11px;
}
.cohort-legend-title { font-weight: 600; color: #495057; margin-right: 4px; }
.cohort-legend-cell {
  display: inline-block; padding: 3px 8px; border-radius: 3px;
  font-size: 11px; font-weight: 600;
}

/* Scrollable table wrapper */
.cohort-table-wrap {
  overflow-x: auto; -webkit-overflow-scrolling: touch;
}
.cohort-table {
  border-collapse: collapse; font-size: 12px; white-space: nowrap; width: 100%;
}
.cohort-table th, .cohort-table td {
  border: 1px solid #dee2e6; padding: 0;
}
.cohort-th-month  { padding: 6px 10px; background: #f8f9fa; font-weight: 700; min-width: 90px; }
.cohort-th-size   { padding: 6px 10px; background: #f8f9fa; font-weight: 700; min-width: 54px; }
.cohort-th-offset { padding: 6px 8px;  background: #f8f9fa; font-weight: 700; min-width: 64px; text-align: center; }
.cohort-th-m0     { background: #d4edda; }

.cohort-month-cell { padding: 5px 10px; font-weight: 600; color: #212529; background: #f8f9fa; }
.cohort-size-cell  { padding: 5px 10px; color: #495057; background: #f8f9fa; }

/* Heatmap cells */
.cohort-cell {
  padding: 5px 6px; text-align: center; font-size: 11px;
  font-weight: 600; cursor: default; min-width: 64px;
}

/* Color scale — low to high retention */
.cohort-m0     { background: #155724; color: #fff; }
.cohort-future {
  background: repeating-linear-gradient(
    45deg, #f8f9fa, #f8f9fa 3px, #e9ecef 3px, #e9ecef 6px
  );
  color: transparent;
}
.cohort-zero   { background: #f1f3f5; color: #adb5bd; }
.cohort-p10    { background: #f8d7da; color: #721c24; }
.cohort-p20    { background: #ffd8b1; color: #7c3c00; }
.cohort-p35    { background: #fff3cd; color: #856404; }
.cohort-p50    { background: #d4f0c0; color: #2d6a1c; }
.cohort-p70    { background: #9fd8a0; color: #155724; }
.cohort-p100   { background: #28a745; color: #fff; }

/* Average footer row */
.cohort-avg-row td { border-top: 2px solid #495057; }
.cohort-avg-row .cohort-month-cell,
.cohort-avg-row .cohort-size-cell  { background: #e9ecef; }

/* Guide panel */
.cohort-guide-wrap { padding: 0 !important; }
.cohort-guide-toggle {
  width: 100%; text-align: left; background: #f0f4ff;
  border: 1px solid #c5d0f5; border-radius: 6px;
  padding: 10px 16px; font-size: 13px; font-weight: 600;
  color: #084298; cursor: pointer; display: flex;
  align-items: center; gap: 8px;
}
.cohort-guide-toggle:hover { background: #e2eafc; }
.cohort-guide-caret { margin-left: auto; font-size: 11px; }
#cohort-guide-body {
  border: 1px solid #c5d0f5; border-top: none;
  border-radius: 0 0 6px 6px; background: #f8f9ff;
  padding: 18px 20px;
}
.cohort-guide-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}
.cohort-guide-block h4 {
  font-size: 13px; font-weight: 700; color: #212529;
  margin: 0 0 8px; display: flex; align-items: center; gap: 6px;
}
.cohort-guide-block p, .cohort-guide-block li {
  font-size: 12px; color: #495057; line-height: 1.6; margin: 0 0 6px;
}
.cohort-guide-block ul { padding-left: 18px; margin: 0 0 6px; }
.cohort-guide-block li { margin-bottom: 4px; }

/* Benchmark table */
.cohort-bench-table {
  width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 6px;
}
.cohort-bench-table th {
  background: #e9ecef; padding: 5px 8px;
  border: 1px solid #dee2e6; font-weight: 700; text-align: left;
}
.cohort-bench-table td { padding: 5px 8px; border: 1px solid #dee2e6; }
.bench-good { color: #155724; font-weight: 700; }
.bench-warn { color: #856404; font-weight: 700; }
.bench-bad  { color: #721c24; font-weight: 700; }
</style>
{/literal}
