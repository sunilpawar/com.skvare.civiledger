{* CiviLedger — Duplicate Payment Detector *}
<div class="civiledger-wrap">

  <div class="civiledger-header">
    <h1><i class="crm-i fa-copy"></i> {ts}Duplicate Payment Detector{/ts}</h1>
    <p>{ts}Finds contributions where the same contact paid the same amount with the same payment instrument within a short time window — the signature of an IPN double-fire, network retry, or browser resubmit.{/ts}</p>
  </div>

  {* ── Filters ──────────────────────────────────────────────────────────── *}
  <div class="civiledger-section civiledger-filters">
    <form method="get">
      {if $cms_type eq 'WordPress'}
        <input type="hidden" name="page" value="CiviCRM" />
      {/if}
      <input type="hidden" name="q" value="civicrm/civiledger/duplicate-payments" />
      <div class="filter-row">
        <label>{ts}Date From:{/ts} <input type="date" name="date_from" value="{$dateFrom}"></label>
        <label>{ts}Date To:{/ts} <input type="date" name="date_to" value="{$dateTo}"></label>
        <label>{ts}Time window (minutes):{/ts}
          <input type="number" name="window" value="{$window}" min="1" max="1440"
                 style="width:74px;border:1px solid #ced4da;border-radius:4px;padding:5px 8px;font-size:13px">
        </label>
        <button type="submit" class="button">{ts}Scan{/ts}</button>
        <a href="{$settingsUrl}" class="button" style="margin-left:4px"
           title="{ts}Change the default time window in Settings{/ts}">
          <i class="crm-i fa-cog"></i> {ts}Settings{/ts}
        </a>
      </div>
    </form>
  </div>

  {* ── Summary banner ───────────────────────────────────────────────────── *}
  <div class="integrity-summary {if $totalSets > 0}summary-bad{else}summary-good{/if}">
    {if $totalSets == 0}
      <i class="crm-i fa-check-circle"></i>
      <strong>{ts}No duplicate payments found{/ts}</strong>
      {ts 1=$dateFrom 2=$dateTo 3=$window}in the period %1 – %2 with a %3-minute window.{/ts}
    {else}
      <i class="crm-i fa-exclamation-triangle"></i>
      <strong>
        {ts 1=$totalSets}%1 potential duplicate set(s){/ts}
      </strong>
      — {ts 1=$totalContributions}%1 contributions involved.{/ts}
      {ts}Review each set below and cancel any confirmed duplicates. The earliest contribution in each set is marked as the original.{/ts}
    {/if}
  </div>

  {* ── Duplicate sets ───────────────────────────────────────────────────── *}
  {foreach from=$sets item=set key=setIdx}
    <div class="civiledger-section dup-set" id="dup-set-{$setIdx}">

      <div class="dup-set-header">
        <div class="dup-set-meta">
          <a href="{$set.contact_url}" class="dup-contact-name">{$set.contact_name}</a>
          <span class="amount-badge">{$set.total_amount|crmMoney}</span>
          <span class="dup-pill dup-pill-instrument">{$set.payment_instrument_name}</span>
          <span class="dup-pill dup-pill-type">{$set.financial_type_name}</span>
        </div>
        <div>
          <span class="issue-count count-bad">{$set.contributions|@count}</span>
          <span style="font-size:12px;color:#721c24;margin-left:4px">{ts}contributions{/ts}</span>
        </div>
      </div>

      <table class="civiledger-table dup-table">
        <thead>
          <tr>
            <th>{ts}ID{/ts}</th>
            <th>{ts}Date / Time{/ts}</th>
            <th>{ts}&Delta; from first{/ts}</th>
            <th>{ts}Trxn ID{/ts}</th>
            <th>{ts}Status{/ts}</th>
            <th>{ts}Actions{/ts}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$set.contributions item=c}
            <tr class="{if $c.is_original}dup-original{elseif $c.status_id eq 3}dup-cancelled{else}dup-candidate{/if}"
                id="dup-row-{$c.id}">
              <td><strong>#{$c.id}</strong></td>
              <td style="white-space:nowrap;font-family:monospace;font-size:12px">{$c.receive_date}</td>
              <td>
                {if $c.is_original}
                  <span class="delta-badge delta-zero">{ts}original{/ts}</span>
                {else}
                  <span class="delta-badge delta-dup">+{$c.delta_seconds}s</span>
                {/if}
              </td>
              <td style="font-family:monospace;font-size:12px;max-width:180px;overflow:hidden;text-overflow:ellipsis"
                  title="{$c.trxn_id}">{$c.trxn_id|default:'—'}</td>
              <td>
                <span class="trxn-status-badge trxn-status-{$c.status_id}">{$c.status_label}</span>
              </td>
              <td class="dup-actions" style="white-space:nowrap">
                <a href="{$c.view_url}" target="_blank" class="button small"
                   title="{ts}View contribution{/ts}">
                  <i class="crm-i fa-eye"></i> {ts}View{/ts}
                </a>
                <a href="{$c.audit_url}" target="_blank" class="button small"
                   title="{ts}Audit Trail{/ts}">
                  <i class="crm-i fa-sitemap"></i> {ts}Audit{/ts}
                </a>
                {if $c.status_id eq 1}
                  <button class="button small dup-cancel-btn"
                          data-cid="{$c.id}"
                          data-ajax="{$ajaxUrl}"
                          style="background:#dc3545;color:#fff;border-color:#dc3545">
                    <i class="crm-i fa-ban"></i> {ts}Cancel{/ts}
                  </button>
                {elseif $c.status_id eq 3}
                  <span class="dup-already-cancelled">{ts}Cancelled{/ts}</span>
                {/if}
              </td>
            </tr>
          {/foreach}
        </tbody>
      </table>

    </div>
  {/foreach}

  {if !$sets && $totalSets == 0}{* already shown in banner *}{/if}

</div>{* .civiledger-wrap *}

{* ── Cancel confirmation modal ────────────────────────────────────────────── *}
<div id="dup-cancel-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:8px;padding:28px 32px;max-width:440px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.25)">
    <h3 style="margin:0 0 12px;color:#721c24">
      <i class="crm-i fa-exclamation-triangle"></i> {ts}Cancel Contribution{/ts}
    </h3>
    <p style="margin:0 0 18px;font-size:14px">
      {ts}Are you sure you want to cancel contribution{/ts}
      <strong id="dup-modal-cid"></strong>?
      {ts}This marks it as Cancelled using the CiviCRM API. The record is not deleted and can be reviewed in the Audit Trail.{/ts}
    </p>
    <div id="dup-modal-status"
         style="display:none;margin-bottom:12px;padding:8px 12px;border-radius:4px;font-size:13px"></div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button id="dup-modal-confirm"
              style="background:#dc3545;color:#fff;border:1px solid #dc3545;padding:7px 18px;border-radius:4px;cursor:pointer;font-size:13px">
        {ts}Yes, Cancel It{/ts}
      </button>
      <button id="dup-modal-close"
              style="background:#6c757d;color:#fff;border:1px solid #6c757d;padding:7px 18px;border-radius:4px;cursor:pointer;font-size:13px">
        {ts}No, Keep It{/ts}
      </button>
    </div>
  </div>
</div>

<script>
(function ($) {
  var pendingCid  = null;
  var pendingAjax = '{$ajaxUrl}';

  // Open modal on Cancel button click
  $(document).on('click', '.dup-cancel-btn', function () {
    pendingCid = $(this).data('cid');
    $('#dup-modal-cid').text('#' + pendingCid);
    $('#dup-modal-status').hide().text('');
    $('#dup-modal-confirm').prop('disabled', false).text('{ts}Yes, Cancel It{/ts}');
    $('#dup-cancel-modal').css('display', 'flex');
  });

  // Close modal
  $('#dup-modal-close').on('click', function () {
    $('#dup-cancel-modal').hide();
    pendingCid = null;
  });

  // Confirm cancel — call AJAX, update row on success
  $('#dup-modal-confirm').on('click', function () {
    if (!pendingCid) { return; }
    var $btn = $(this);
    $btn.prop('disabled', true).text('{ts}Cancelling…{/ts}');

    CRM.$.ajax({
      url: pendingAjax,
      method: 'POST',
      data: { op: 'cancel_duplicate_payment', cid: pendingCid },
      dataType: 'json',
      success: function (resp) {
        if (resp.success) {
          var $row = $('#dup-row-' + pendingCid);
          $row.removeClass('dup-original dup-candidate').addClass('dup-cancelled');
          $row.find('.trxn-status-badge')
              .attr('class', 'trxn-status-badge trxn-status-3')
              .text('Cancelled');
          $row.find('.dup-cancel-btn')
              .replaceWith('<span class="dup-already-cancelled">{ts}Cancelled{/ts}</span>');
          $('#dup-modal-status')
              .css({ display: 'block', background: '#d4edda', color: '#155724' })
              .text(resp.message || '{ts}Cancelled successfully.{/ts}');
          setTimeout(function () { $('#dup-cancel-modal').hide(); }, 1400);
        } else {
          $('#dup-modal-status')
              .css({ display: 'block', background: '#f8d7da', color: '#721c24' })
              .text(resp.message || '{ts}An error occurred.{/ts}');
          $btn.prop('disabled', false).text('{ts}Yes, Cancel It{/ts}');
        }
      },
      error: function () {
        $('#dup-modal-status')
            .css({ display: 'block', background: '#f8d7da', color: '#721c24' })
            .text('{ts}Request failed. Please try again.{/ts}');
        $btn.prop('disabled', false).text('{ts}Yes, Cancel It{/ts}');
      }
    });
  });
}(CRM.$));
</script>
