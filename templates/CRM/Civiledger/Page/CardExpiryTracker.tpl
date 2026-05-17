{* CiviLedger - Payment Card Expiry Tracker *}
<div class="civiledger-wrap">
  <div class="civiledger-header">
    <h1><i class="crm-i fa-credit-card"></i> Payment Card Expiry Tracker</h1>
    <p>Shows active recurring series whose vaulted payment card is expired or expiring soon.
       Only series with a stored payment token are included — recurring donors paying by check, ACH, or cash
       do not appear here.</p>
  </div>

  {* Compatibility note *}
  <div style="margin-bottom:16px;border-left:4px solid #0c5460;padding:10px 14px;background:#d1ecf1;border-radius:4px;font-size:13px">
    <i class="crm-i fa-info-circle"></i>
    <strong>Processor compatibility:</strong>
    Only processors that vault card tokens into <code>civicrm_payment_token</code> (e.g.&nbsp;Stripe, iATS, Authorize.Net CIM)
    will have data here. If your processor stores card info externally without syncing the expiry date back to CiviCRM,
    those donors will not appear even if their cards are expiring.
  </div>

  {* Filters *}
  <div class="civiledger-section civiledger-filters">
    <form method="get">
      {if $cms_type eq 'WordPress'}
        <input type="hidden" name="page" value="CiviCRM" />
        <input type="hidden" name="q" value="civicrm/civiledger/card-expiry" />
      {elseif $cms_type eq 'Joomla'}
        <input type="hidden" name="option" value="com_civicrm" />
        <input type="hidden" name="task" value="civicrm/civiledger/card-expiry" />
      {/if}
      <div class="filter-row">
        <label>Look-ahead Window:
          <select name="days_window">
            <option value="30"  {if $filters.days_window == 30}selected{/if}>30 days</option>
            <option value="60"  {if $filters.days_window == 60}selected{/if}>60 days</option>
            <option value="90"  {if $filters.days_window == 90}selected{/if}>90 days (recommended)</option>
            <option value="180" {if $filters.days_window == 180}selected{/if}>180 days</option>
          </select>
        </label>
        {if $processors}
        <label>Payment Processor:
          <select name="payment_processor_id">
            <option value="">— All —</option>
            {foreach from=$processors key=pid item=pname}
              <option value="{$pid}" {if $filters.payment_processor_id == $pid}selected{/if}>{$pname}</option>
            {/foreach}
          </select>
        </label>
        {/if}
        <button type="submit" class="button">Scan</button>
      </div>
    </form>
  </div>

  {* Summary Banner *}
  <div class="integrity-summary {if $summary.total > 0}summary-bad{else}summary-good{/if}">
    {if $summary.total == 0}
      <i class="crm-i fa-check-circle"></i>
      <strong>All clear!</strong> No expiring cards found within the selected window.
    {else}
      <i class="crm-i fa-exclamation-triangle"></i>
      <strong>{$summary.total} series</strong> with expiring or expired cards &nbsp;|&nbsp;
      <span class="rhm-badge rhm-badge-red">{$summary.expired} Expired</span>
      <span class="rhm-badge rhm-badge-orange">{$summary.expiring_soon} Expiring ≤30d</span>
      <span class="rhm-badge rhm-badge-yellow">{$summary.expiring_later} Expiring 31–{$filters.days_window}d</span>
      &nbsp;&mdash;&nbsp;
      <strong>~{$mrrAtRisk.total|crmMoney} MRR at risk</strong>
      <span class="help-tip" title="Monthly Recurring Revenue at risk = frequency-adjusted monthly value summed across all affected series.">?</span>
    {/if}
  </div>

  {* ── Bucket 1: Already Expired ── *}
  <div class="civiledger-section">
    <h2 class="rhm-section-head">
      <button class="rhm-toggle button small" data-target="cet-expired-body" style="margin-right:8px">
        <span class="rhm-caret">▼</span>
      </button>
      <span class="issue-count {if $summary.expired > 0}count-bad{else}count-ok{/if}">{$summary.expired}</span>
      Already Expired
      {if $summary.expired > 0}
        <span class="rhm-badge rhm-badge-red" style="margin-left:8px">~{$mrrAtRisk.expired|crmMoney}/mo at risk</span>
      {/if}
      <span class="help-tip" title="Cards where the expiry date has already passed. Future charge attempts will be declined. Contact donors immediately.">?</span>
    </h2>
    <div id="cet-expired-body">
      {if $results.expired}
        <p class="rhm-chart-note" style="margin-bottom:10px">
          These cards have already expired. Any future charge attempt will be declined by the processor.
          Contact these donors immediately to update their payment method.
        </p>
        <table class="civiledger-table">
          <thead>
            <tr>
              <th>Contact</th>
              <th>Email</th>
              <th>Card</th>
              <th>Expires</th>
              <th>Since</th>
              <th class="text-right">Amount</th>
              <th>Frequency</th>
              <th class="text-right">MRR</th>
              <th>Processor</th>
              <th>Series</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$results.expired item=row}
              <tr class="row-critical">
                <td><a target="_blank" href="{$row.contact_url}">{$row.contact_name}</a></td>
                <td>{$row.contact_email|default:'—'}</td>
                <td class="card-masked">{$row.masked_account_number|default:'—'}</td>
                <td><strong>{$row.expiry_display}</strong></td>
                <td><span class="{$row.days_class}">{$row.days_label}</span></td>
                <td class="text-right">{$row.amount|crmMoney:$row.currency}</td>
                <td>{$row.frequency_display}</td>
                <td class="text-right">{$row.monthly_value|crmMoney:$row.currency}</td>
                <td>{$row.processor_name}</td>
                <td><a target="_blank" href="{$row.recur_url}">Series #{$row.recur_id}</a></td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      {else}
        <p class="rhm-empty"><i class="crm-i fa-check-circle"></i> No expired cards linked to active recurring series.</p>
      {/if}
    </div>
  </div>

  {* ── Bucket 2: Expiring in 0–30 days ── *}
  <div class="civiledger-section">
    <h2 class="rhm-section-head">
      <button class="rhm-toggle button small" data-target="cet-soon-body" style="margin-right:8px">
        <span class="rhm-caret">▼</span>
      </button>
      <span class="issue-count {if $summary.expiring_soon > 0}count-warn{else}count-ok{/if}">{$summary.expiring_soon}</span>
      Expiring in 0–30 Days
      {if $summary.expiring_soon > 0}
        <span class="rhm-badge rhm-badge-orange" style="margin-left:8px">~{$mrrAtRisk.expiring_soon|crmMoney}/mo</span>
      {/if}
      <span class="help-tip" title="Cards expiring within the next 30 days. Send a payment update reminder now — before the next charge attempt fails.">?</span>
    </h2>
    <div id="cet-soon-body">
      {if $results.expiring_soon}
        <p class="rhm-chart-note" style="margin-bottom:10px">
          These cards expire within 30 days. Send a payment update reminder now to avoid a failed charge.
          Many processors support an automatic card updater service — check your processor settings to enable it.
        </p>
        <table class="civiledger-table">
          <thead>
            <tr>
              <th>Contact</th>
              <th>Email</th>
              <th>Card</th>
              <th>Expires</th>
              <th>Time Left</th>
              <th class="text-right">Amount</th>
              <th>Frequency</th>
              <th class="text-right">MRR</th>
              <th>Processor</th>
              <th>Series</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$results.expiring_soon item=row}
              <tr class="{if $row.days_remaining <= 7}row-critical{else}row-mismatch{/if}">
                <td><a target="_blank" href="{$row.contact_url}">{$row.contact_name}</a></td>
                <td>{$row.contact_email|default:'—'}</td>
                <td class="card-masked">{$row.masked_account_number|default:'—'}</td>
                <td><strong>{$row.expiry_display}</strong></td>
                <td><span class="{$row.days_class}">{$row.days_label}</span></td>
                <td class="text-right">{$row.amount|crmMoney:$row.currency}</td>
                <td>{$row.frequency_display}</td>
                <td class="text-right">{$row.monthly_value|crmMoney:$row.currency}</td>
                <td>{$row.processor_name}</td>
                <td><a target="_blank" href="{$row.recur_url}">Series #{$row.recur_id}</a></td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      {else}
        <p class="rhm-empty"><i class="crm-i fa-check-circle"></i> No cards expiring within the next 30 days.</p>
      {/if}
    </div>
  </div>

  {* ── Bucket 3: Expiring in 31–N days ── *}
  <div class="civiledger-section">
    <h2 class="rhm-section-head">
      <button class="rhm-toggle button small" data-target="cet-later-body" style="margin-right:8px">
        <span class="rhm-caret">▼</span>
      </button>
      <span class="issue-count {if $summary.expiring_later > 0}count-warn{else}count-ok{/if}">{$summary.expiring_later}</span>
      Expiring in 31–{$filters.days_window} Days
      {if $summary.expiring_later > 0}
        <span class="rhm-badge rhm-badge-yellow" style="margin-left:8px">~{$mrrAtRisk.expiring_later|crmMoney}/mo</span>
      {/if}
      <span class="help-tip" title="Cards expiring within your selected window but more than 30 days away. Queue outreach 2–3 weeks before each card's expiry month.">?</span>
    </h2>
    <div id="cet-later-body">
      {if $results.expiring_later}
        <p class="rhm-chart-note" style="margin-bottom:10px">
          These cards expire later within your selected window.
          Queue a pre-expiry reminder email 2–3 weeks before each donor's card expiry month.
        </p>
        <table class="civiledger-table">
          <thead>
            <tr>
              <th>Contact</th>
              <th>Email</th>
              <th>Card</th>
              <th>Expires</th>
              <th>Time Left</th>
              <th class="text-right">Amount</th>
              <th>Frequency</th>
              <th class="text-right">MRR</th>
              <th>Processor</th>
              <th>Series</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$results.expiring_later item=row}
              <tr>
                <td><a target="_blank" href="{$row.contact_url}">{$row.contact_name}</a></td>
                <td>{$row.contact_email|default:'—'}</td>
                <td class="card-masked">{$row.masked_account_number|default:'—'}</td>
                <td><strong>{$row.expiry_display}</strong></td>
                <td><span class="{$row.days_class}">{$row.days_label}</span></td>
                <td class="text-right">{$row.amount|crmMoney:$row.currency}</td>
                <td>{$row.frequency_display}</td>
                <td class="text-right">{$row.monthly_value|crmMoney:$row.currency}</td>
                <td>{$row.processor_name}</td>
                <td><a target="_blank" href="{$row.recur_url}">Series #{$row.recur_id}</a></td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      {else}
        <p class="rhm-empty"><i class="crm-i fa-check-circle"></i> No cards expiring in this range.</p>
      {/if}
    </div>
  </div>

</div>

{literal}
<style>
.expiry-expired { background:#721c24;color:#fff;padding:2px 7px;border-radius:10px;font-size:12px;font-weight:700 }
.expiry-today   { background:#491217;color:#fff;padding:2px 7px;border-radius:10px;font-size:12px;font-weight:700 }
.expiry-urgent  { background:#dc3545;color:#fff;padding:2px 7px;border-radius:10px;font-size:12px;font-weight:700 }
.expiry-soon    { background:#fd7e14;color:#fff;padding:2px 7px;border-radius:10px;font-size:12px;font-weight:700 }
.expiry-later   { background:#ffc107;color:#212529;padding:2px 7px;border-radius:10px;font-size:12px;font-weight:700 }
.card-masked    { font-family:monospace;letter-spacing:1px;color:#495057 }
</style>
<script>
document.querySelectorAll('.rhm-toggle').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var target = document.getElementById(btn.dataset.target);
    if (!target) return;
    var hidden = target.style.display === 'none' || target.style.display === '';
    target.style.display = hidden ? 'block' : 'none';
    btn.querySelector('.rhm-caret').textContent = hidden ? '▲' : '▼';
  });
});
</script>
{/literal}
