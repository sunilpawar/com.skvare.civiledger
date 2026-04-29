{* CiviLedger — Settings *}
<div class="civiledger-wrap">
  <div class="civiledger-header">
    <h1><i class="crm-i fa-cog"></i> {ts}CiviLedger Settings{/ts}</h1>
    <p>{ts}Configure health monitoring alerts, repair behaviour, and audit trail options.{/ts}</p>
  </div>

  <form action="{$action}" id="Settings" method="post">
    {* ── Health Monitor ─────────────────────────────────────────────────── *}
    <div class="civiledger-section">
      <h2><i class="crm-i fa-bell"></i> {ts}Health Monitor &amp; Email Alerts{/ts}</h2>
      <p style="font-size:13px;color:#666;margin:0 0 16px">
        {ts 1="{crmURL p='civicrm/admin/job' q='reset=1'}"}
          The <strong>CiviLedger: Integrity Monitor</strong> scheduled job runs nightly checks.
          Configure frequency and enable it at <a href="%1">Administer &rsaquo; Scheduled Jobs</a>.
        {/ts}
      </p>
      <table class="form-layout-compressed" style="width:100%">
        <tr>
          <td class="label" style="width:260px">{$form.civiledger_alert_enabled.label}</td>
          <td>
            {$form.civiledger_alert_enabled.html}
            <span class="description">{ts}Send an alert email when broken chains or amount mismatches are detected.{/ts}</span>
          </td>
        </tr>
        <tr>
          <td class="label">{$form.civiledger_alert_emails.label}</td>
          <td>
            {$form.civiledger_alert_emails.html}
            <br><span class="description">{ts}Comma-separated list of email addresses. Leave blank to use the domain From address.{/ts}</span>
          </td>
        </tr>
      </table>

      {* Last run info *}
      {if $lastRun}
        <div style="margin-top:14px;padding:10px 14px;background:#f8f9fa;border-radius:4px;font-size:13px">
          <strong>{ts}Last health check:{/ts}</strong> {$lastRun}
          &nbsp;|&nbsp;
          {if $lastTotal > 0}
            <span style="color:#dc3545;font-weight:700">{ts 1=$lastTotal}%1 issue(s) found{/ts}</span>
          {else}
            <span style="color:#28a745;font-weight:700">{ts}No issues found{/ts}</span>
          {/if}
          &nbsp;|&nbsp;
          <a href="{crmURL p='civicrm/civiledger/dashboard' q='reset=1'}">{ts}View Dashboard{/ts}</a>
        </div>
      {/if}
    </div>

    {* ── Chain Repair ───────────────────────────────────────────────────── *}
    <div class="civiledger-section">
      <h2><i class="crm-i fa-wrench"></i> {ts}Chain Repair{/ts}</h2>
      <table class="form-layout-compressed" style="width:100%">
        <tr>
          <td class="label" style="width:260px">{$form.civiledger_batch_size.label}</td>
          <td>
            {$form.civiledger_batch_size.html}
            <span class="description">{ts}Maximum number of broken contributions listed on the Repair Tool page per load. Default: 50.{/ts}</span>
          </td>
        </tr>
      </table>
    </div>

    {* ── Audit Trail ────────────────────────────────────────────────────── *}
    <div class="civiledger-section">
      <h2><i class="crm-i fa-search"></i> {ts}Audit Trail{/ts}</h2>
      <table class="form-layout-compressed" style="width:100%">
        <tr>
          <td class="label" style="width:260px">{$form.civiledger_dup_fi_detection.label}</td>
          <td>
            {$form.civiledger_dup_fi_detection.html}
            <span class="description">{ts}Detect and flag duplicate Paid / Partially-paid financial items on the Audit Trail page. Disabling speeds up Audit Trail rendering on high-volume sites.{/ts}</span>
          </td>
        </tr>
      </table>
    </div>

    {* ── Duplicate Payment Detector ────────────────────────────────────── *}
    <div class="civiledger-section">
      <h2><i class="crm-i fa-copy"></i> {ts}Duplicate Payment Detector{/ts}</h2>
      <table class="form-layout-compressed" style="width:100%">
        <tr>
          <td class="label" style="width:260px">{$form.civiledger_dup_payment_window.label}</td>
          <td>
            {$form.civiledger_dup_payment_window.html}
            <span class="description">{ts}Two Completed contributions from the same contact for the same amount with the same payment instrument within this many minutes are flagged as potential duplicates. Default: 10.{/ts}</span>
          </td>
        </tr>
      </table>
      <div style="margin-top:12px">
        <a href="{crmURL p='civicrm/civiledger/duplicate-payments' q='reset=1'}" class="button small">
          <i class="crm-i fa-search"></i> {ts}Run Duplicate Payment Scan{/ts}
        </a>
      </div>
    </div>

    {* ── Buttons ────────────────────────────────────────────────────────── *}
    <div class="civiledger-section" style="text-align:right;padding:14px 24px">
      {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>

  </form>
</div>
