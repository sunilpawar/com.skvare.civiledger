{* CiviLedger - Repair Tool *}
<div class="civiledger-wrap">
  <div class="civiledger-header">
    <h1><i class="crm-i fa-wrench"></i> Financial Chain Repair Tool</h1>
    <p>Automatically rebuild missing financial chain entries for broken contributions.</p>
  </div>

  {* Period lock banner *}
  {if $activeLock}
    <div class="crm-container period-lock-banner">
      <i class="crm-i fa-lock"></i>
      <strong>Financial Period Locked</strong> — Transactions before
      <strong>{$activeLock.lock_date}</strong> are locked and cannot be repaired.
      Locked by {$activeLock.locked_by_name|default:'System'} on {$activeLock.locked_at|crmDate}.
      {if $activeLock.lock_reason}<em>Reason: {$activeLock.lock_reason}</em>{/if}
      <a href="{$periodCloseUrl}" class="button small">Manage Lock</a>
    </div>
  {/if}

  {if $lockedError}
    <div class="crm-container">
      <div class="status error">
        <i class="crm-i fa-lock"></i> {$lockedError}
      </div>
    </div>
  {/if}

  {if $lockedSkipped}
    <div class="crm-container">
      <div class="status messages warning">
        <i class="crm-i fa-lock"></i>
        {$lockedSkipped} contribution(s) were skipped — they fall within the locked period.
      </div>
    </div>
  {/if}

  {if $batchResult}
    <div class="crm-container">
      <div class="status {if $batchResult.failed == 0}ok{else}error{/if}">
        Batch repair: {$batchResult.repaired}/{$batchResult.total} repaired.
          {if $batchResult.failed > 0}{$batchResult.failed} failed.{/if}
          {if $batchResult.locked_skipped > 0}{$batchResult.locked_skipped} skipped (locked period).{/if}
      </div>
    </div>
  {/if}

  {if $repairResult}
    <div class="crm-container">
      <div class="status {if $repairResult.success}ok{else}error{/if}">
          {if $repairResult.success}
            <strong>Repair successful!</strong>
            <ul>{foreach from=$repairResult.actions item=a}<li>{$a}</li>{/foreach}</ul>
          {else}
            <strong>Repair failed:</strong> {$repairResult.errors|implode:', '}
          {/if}
      </div>
    </div>
  {/if}

  <div class="civiledger-section">
    <div class="repair-info-box">
      <h3>What does the repair tool do?</h3>
      <ol>
        <li>Creates missing <strong>line items</strong> from contribution data</li>
        <li>Creates missing <strong>financial_item</strong> records mapped to the correct income account</li>
        <li>Creates missing <strong>financial_trxn</strong> (payment record) if none exists</li>
        <li>Links contribution → trxn via <strong>entity_financial_trxn</strong></li>
        <li>Links financial_items → trxn via <strong>entity_financial_trxn</strong></li>
        <li>Logs every action to the CiviLedger audit log</li>
      </ol>
      <p class="repair-warning"><i class="crm-i fa-warning"></i>
        Always back up your database before running batch repairs on production.
      </p>
    </div>
  </div>

  {if $totalBroken == 0}
    <div class="integrity-summary summary-good">
      <i class="crm-i fa-check-circle"></i> <strong>No broken chains found!</strong> Your financial data is intact.
    </div>
  {else}

    {* Batch Repair All *}
    <div class="civiledger-section">
      <h2>Broken Contributions ({$brokenContributions|@count})</h2>
        {if $brokenContributions}
          <form method="post" id="batchRepairForm">
            {if $cms_type eq 'WordPress'}
              <input type="hidden" name="page" value="CiviCRM" />
              <input type="hidden" name="q" value="civicrm/civiledger/chain-repair" />
            {elseif $cms_type eq 'Joomla'}
              <input type="hidden" name="option" value="com_civicrm" />
              <input type="hidden" name="task" value="civicrm/civiledger/chain-repair" />
            {/if}
            <input type="hidden" name="action" value="repair_batch">
            <table class="civiledger-table">
              <thead>
              <tr>
                <th><input type="checkbox" id="selectAll"> All</th>
                <th>ID</th><th>Contact</th><th>Amount</th><th>Date</th><th>Type</th><th>Status</th><th>Action</th>
              </tr>
              </thead>
              <tbody>
              {foreach from=$brokenContributions item=row}
                <tr class="{if $row.is_locked}row-locked{/if}">
                  <td>
                    {if $row.is_locked}
                      <span title="Locked — cannot repair contributions within a locked period">
                        <i class="crm-i fa-lock" style="color:#856404"></i>
                      </span>
                    {else}
                      <input type="checkbox" name="selected[]" value="{$row.contribution_id}" class="row-check">
                    {/if}
                  </td>
                  <td>#{$row.contribution_id}</td>
                  <td>{$row.contact_name}</td>
                  <td class="text-right">{$row.total_amount|crmMoney}</td>
                  <td>{$row.receive_date|crmDate}</td>
                  <td>{$row.financial_type}</td>
                  <td>
                    {if $row.is_locked}
                      <span class="period-lock-badge"><i class="crm-i fa-lock"></i> Locked</span>
                    {else}
                      &mdash;
                    {/if}
                  </td>
                  <td>
                    {if $row.is_locked}
                      <span class="text-muted" title="Locked — cannot repair contributions within a locked period">
                        <i class="crm-i fa-lock"></i> Period locked
                      </span>
                    {else}
                      <a target="_blank" href="{crmURL p='civicrm/civiledger/repair-detail' q="operation=repair_one&cid=`$row.contribution_id`"}" onclick="return confirm('Repair contribution #{$row.contribution_id}?')">Repair This</a>
                    {/if}
                  </td>
                </tr>
              {/foreach}
              </tbody>
            </table>
            <div class="repair-actions">
              <button type="submit" class="button crm-button-type-delete" id="batchRepairBtn" disabled>
                <i class="crm-i fa-wrench"></i> Repair Selected
              </button>
              <a href="{$integrityUrl}" class="button">← Back to Integrity Checker</a>
            </div>
          </form>
        {/if}
    </div>
  {/if}

</div>

{literal}
<style>
.period-lock-banner {
  background: #fff3cd;
  border-left: 4px solid #ffc107;
  color: #856404;
  padding: 10px 14px;
  border-radius: 0 4px 4px 0;
  font-size: 13px;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.period-lock-banner strong { font-weight: 700; }
.period-lock-banner .button.small {
  margin-left: auto;
  padding: 2px 10px;
  font-size: 11px;
}
.row-locked td { opacity: 0.65; }
.period-lock-badge {
  display: inline-block;
  background: #ffc107;
  color: #212529;
  font-size: 10px;
  font-weight: 700;
  padding: 1px 7px;
  border-radius: 3px;
  letter-spacing: .04em;
}
</style>
{/literal}

<script>
  document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    updateBatchBtn();
  });
  document.querySelectorAll('.row-check').forEach(cb => cb.addEventListener('change', updateBatchBtn));
  function updateBatchBtn() {
    var checked = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('batchRepairBtn').disabled = checked === 0;
    document.getElementById('batchRepairBtn').textContent = 'Repair Selected (' + checked + ')';
  }
  document.getElementById('batchRepairForm').addEventListener('submit', function(e) {
    var ids = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
    if (!ids.length) { e.preventDefault(); return; }
    var hiddenIds = document.createElement('input');
    hiddenIds.type = 'hidden';
    hiddenIds.name = 'ids';
    hiddenIds.value = ids.join(',');
    this.appendChild(hiddenIds);
  });
</script>
