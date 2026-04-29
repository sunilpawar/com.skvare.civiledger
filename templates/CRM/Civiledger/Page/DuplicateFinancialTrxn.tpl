{* CiviLedger — Duplicate Financial Transaction Detector *}
<div class="civiledger-wrap">

  <div class="civiledger-header">
    <h1><i class="crm-i fa-files-o"></i> {ts}Duplicate Financial Transaction Detector{/ts}</h1>
    <p>{ts}Finds contributions that have two or more civicrm_financial_trxn rows sharing the same trxn_id and status — the footprint of a double IPN callback that created two payment records for a single event.{/ts}</p>
  </div>

  {* ── Filters ──────────────────────────────────────────────────────────── *}
  <div class="civiledger-section civiledger-filters">
    <form method="get">
      {if $cms_type eq 'WordPress'}
        <input type="hidden" name="page" value="CiviCRM" />
      {/if}
      <input type="hidden" name="q" value="civicrm/civiledger/duplicate-trxn" />
      <div class="filter-row">
        <label>{ts}Date From:{/ts} <input type="date" name="date_from" value="{$dateFrom}"></label>
        <label>{ts}Date To:{/ts}   <input type="date" name="date_to"   value="{$dateTo}"></label>
        <button type="submit" class="button">{ts}Scan{/ts}</button>
      </div>
    </form>
  </div>

  {* ── Summary banner ───────────────────────────────────────────────────── *}
  <div class="integrity-summary {if $totalSets > 0}summary-bad{else}summary-good{/if}">
    {if $totalSets == 0}
      <i class="crm-i fa-check-circle"></i>
      <strong>{ts}No duplicate financial transactions found{/ts}</strong>
      {ts 1=$dateFrom 2=$dateTo}in the period %1 – %2.{/ts}
    {else}
      <i class="crm-i fa-exclamation-triangle"></i>
      <strong>{ts 1=$totalSets}%1 contribution(s) affected{/ts}</strong>
      — {ts 1=$totalTrxns}%1 financial transaction rows involved.{/ts}
      {ts}The earliest transaction in each group is the original; all others can be deleted.{/ts}
    {/if}
  </div>

  {* ── Duplicate sets ───────────────────────────────────────────────────── *}
  {foreach from=$sets item=set key=setIdx}
    <div class="civiledger-section dft-set" id="dft-set-{$setIdx}">

      {* Set header *}
      <div class="dft-set-header">
        <div class="dft-set-meta">
          <a href="{$set.contact_url}" class="dup-contact-name">{$set.contact_name}</a>
          <span class="amount-badge">{$set.contribution_amount|crmMoney}</span>
          <span class="dup-pill dup-pill-type">{$set.financial_type_name}</span>
          <span class="trxn-status-badge">{$set.status_label}</span>
        </div>
        <div class="dft-set-links">
          <a href="{$set.contribution_url}" target="_blank" class="button small">
            <i class="crm-i fa-external-link"></i> {ts}Contribution #{$set.contribution_id}{/ts}
          </a>
          <a href="{$set.audit_url}" target="_blank" class="button small">
            <i class="crm-i fa-sitemap"></i> {ts}Audit Trail{/ts}
          </a>
        </div>
      </div>

      {* trxn_id context *}
      <div class="dft-trxn-context">
        <span class="dft-label">{ts}Gateway trxn ID:{/ts}</span>
        <code class="dft-trxn-id">{$set.trxn_id|default:'—'}</code>
        <span class="dft-label" style="margin-left:14px">{ts}Contribution date:{/ts}</span>
        <span style="font-family:monospace;font-size:12px">{$set.contribution_date}</span>
      </div>

      {* Transaction rows *}
      <table class="civiledger-table dft-table">
        <thead>
          <tr>
            <th>{ts}FT ID{/ts}</th>
            <th>{ts}Trxn Date{/ts}</th>
            <th>{ts}Amount{/ts}</th>
            <th>{ts}Fee{/ts}</th>
            <th>{ts}Net{/ts}</th>
            <th>{ts}From → To{/ts}</th>
            <th>{ts}Instrument{/ts}</th>
            <th>{ts}Role{/ts}</th>
            <th>{ts}Action{/ts}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$set.trxns item=t}
            <tr class="{if $t.is_original}dft-row-original{else}dft-row-duplicate{/if}"
                id="dft-row-{$t.id}">
              <td><strong>#{$t.id}</strong></td>
              <td style="white-space:nowrap;font-family:monospace;font-size:12px">{$t.trxn_date}</td>
              <td style="text-align:right">{$t.total_amount|crmMoney}</td>
              <td style="text-align:right;color:#6c757d">{$t.fee_amount|crmMoney}</td>
              <td style="text-align:right">{$t.net_amount|crmMoney}</td>
              <td class="dft-account-flow">
                <span class="dft-account-from">{$t.from_account_name}</span>
                <i class="crm-i fa-arrow-right dft-arrow"></i>
                <span class="dft-account-to">{$t.to_account_name}</span>
              </td>
              <td>
                {if $t.payment_instrument_name neq 'Unknown'}
                  <span class="dup-pill dup-pill-instrument">{$t.payment_instrument_name}</span>
                {else}
                  <span style="color:#aaa">—</span>
                {/if}
              </td>
              <td>
                {if $t.is_original}
                  <span class="dft-badge-original">{ts}Original{/ts}</span>
                {else}
                  <span class="dft-badge-duplicate">{ts}Duplicate{/ts}</span>
                {/if}
              </td>
              <td class="dft-actions">
                {if $t.is_original}
                  <span class="dft-protected" title="{ts}The earliest transaction is protected and cannot be deleted.{/ts}">
                    <i class="crm-i fa-lock"></i> {ts}Protected{/ts}
                  </span>
                {else}
                  <button class="button small dft-delete-btn"
                          style="background:#dc3545;color:#fff;border-color:#dc3545"
                          data-ft-id="{$t.id}"
                          data-contribution-id="{$set.contribution_id}"
                          data-ajax="{$ajaxUrl}">
                    <i class="crm-i fa-trash"></i> {ts}Delete{/ts}
                  </button>
                {/if}
              </td>
            </tr>
          {/foreach}
        </tbody>
      </table>

    </div>
  {/foreach}

</div>{* .civiledger-wrap *}

{* ── Delete confirmation modal ────────────────────────────────────────────── *}
<div id="dft-delete-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:8px;padding:28px 32px;max-width:460px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.25)">
    <h3 style="margin:0 0 12px;color:#721c24">
      <i class="crm-i fa-exclamation-triangle"></i> {ts}Delete Duplicate Transaction{/ts}
    </h3>
    <p style="margin:0 0 6px;font-size:14px">
      {ts}You are about to permanently delete financial transaction{/ts}
      <strong id="dft-modal-ftid"></strong>.
    </p>
    <p style="margin:0 0 18px;font-size:13px;color:#6c757d">
      {ts}This removes the civicrm_financial_trxn row and all its entity links. This action is logged to the audit trail but cannot be undone.{/ts}
    </p>
    <div id="dft-modal-status"
         style="display:none;margin-bottom:12px;padding:8px 12px;border-radius:4px;font-size:13px"></div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button id="dft-modal-confirm"
              style="background:#dc3545;color:#fff;border:1px solid #dc3545;padding:7px 18px;border-radius:4px;cursor:pointer;font-size:13px">
        {ts}Yes, Delete It{/ts}
      </button>
      <button id="dft-modal-close"
              style="background:#6c757d;color:#fff;border:1px solid #6c757d;padding:7px 18px;border-radius:4px;cursor:pointer;font-size:13px">
        {ts}Cancel{/ts}
      </button>
    </div>
  </div>
</div>

<script>
(function ($) {
  var pendingFtId          = null;
  var pendingContributionId = null;
  var pendingAjax          = '{$ajaxUrl}';

  // Open modal on Delete button click
  $(document).on('click', '.dft-delete-btn', function () {
    pendingFtId           = $(this).data('ft-id');
    pendingContributionId = $(this).data('contribution-id');
    $('#dft-modal-ftid').text('#' + pendingFtId);
    $('#dft-modal-status').hide().text('');
    $('#dft-modal-confirm').prop('disabled', false).text('{ts}Yes, Delete It{/ts}');
    $('#dft-delete-modal').css('display', 'flex');
  });

  // Close modal
  $('#dft-modal-close').on('click', function () {
    $('#dft-delete-modal').hide();
    pendingFtId = null;
    pendingContributionId = null;
  });

  // Confirm delete — call AJAX, remove row on success
  $('#dft-modal-confirm').on('click', function () {
    if (!pendingFtId) { return; }
    var $btn = $(this);
    $btn.prop('disabled', true).text('{ts}Deleting…{/ts}');

    CRM.$.ajax({
      url: pendingAjax,
      method: 'POST',
      data: {
        op:              'delete_duplicate_trxn',
        ft_id:           pendingFtId,
        contribution_id: pendingContributionId
      },
      dataType: 'json',
      success: function (resp) {
        if (resp.success) {
          var $row = $('#dft-row-' + pendingFtId);
          $row.find('td').css({ opacity: '0.4', textDecoration: 'line-through' });
          $row.find('.dft-actions').html('<span style="color:#28a745;font-size:12px"><i class="crm-i fa-check"></i> {ts}Deleted{/ts}</span>');
          $('#dft-modal-status')
              .css({ display: 'block', background: '#d4edda', color: '#155724' })
              .text(resp.message || '{ts}Transaction deleted successfully.{/ts}');
          setTimeout(function () {
            $('#dft-delete-modal').hide();
            $row.fadeOut(400, function () { $(this).remove(); });
          }, 1200);
        } else {
          $('#dft-modal-status')
              .css({ display: 'block', background: '#f8d7da', color: '#721c24' })
              .text(resp.message || '{ts}An error occurred.{/ts}');
          $btn.prop('disabled', false).text('{ts}Yes, Delete It{/ts}');
        }
      },
      error: function () {
        $('#dft-modal-status')
            .css({ display: 'block', background: '#f8d7da', color: '#721c24' })
            .text('{ts}Request failed. Please try again.{/ts}');
        $btn.prop('disabled', false).text('{ts}Yes, Delete It{/ts}');
      }
    });
  });
}(CRM.$));
</script>
