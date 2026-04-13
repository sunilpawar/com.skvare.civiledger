{* CiviLedger - Repair Tool *}
<div class="civiledger-wrap">
  <div class="civiledger-header">
    <h1><i class="crm-i fa-wrench"></i> Financial Chain Repair Tool</h1>
    <p>Automatically rebuild missing financial chain entries for broken contributions.</p>
  </div>

  {if $batchResult}
  <div class="crm-container">
    <div class="status {if $batchResult.failed == 0}ok{else}error{/if}">
      Batch repair: {$batchResult.repaired}/{$batchResult.total} repaired.
      {if $batchResult.failed > 0}{$batchResult.failed} failed.{/if}
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
      <input type="hidden" name="action" value="repair_batch">
      <table class="civiledger-table">
        <thead>
          <tr>
            <th><input type="checkbox" id="selectAll"> All</th>
            <th>ID</th><th>Contact</th><th>Amount</th><th>Date</th><th>Type</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$brokenContributions item=row}
          <tr>
            <td><input type="checkbox" name="selected[]" value="{$row.contribution_id}" class="row-check"></td>
            <td>#{$row.contribution_id}</td>
            <td>{$row.contact_name}</td>
            <td class="text-right">{$row.total_amount|crmMoney}</td>
            <td>{$row.receive_date|crmDate}</td>
            <td>{$row.financial_type}</td>
            <td>
              <a href="?action=repair_one&cid={$row.contribution_id}" class="button small"
                 onclick="return confirm('Repair contribution #{$row.contribution_id}?')">
                Repair This
              </a>
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
