{* CiviLedger Chain Repair Template - Feature 2 *}
<div class="crm-container civiledger-repair">
  <h3>{ts}CiviLedger — Financial Chain Repair{/ts}</h3>

  <div class="crm-section">
    <p>
      {ts 1=$brokenCount}Found <strong>%1</strong> contribution(s) with broken financial chains.{/ts}
      {ts}The repair tool will reconstruct missing financial_item and entity_financial_trxn rows based on existing data.{/ts}
    </p>
    {if $brokenIds}
    <p><b>{ts}Sample broken contribution IDs:{/ts}</b> {$brokenIds|@implode:', '}{if $brokenCount > 10}...{/if}</p>
    {/if}
  </div>

  {if $brokenCount > 0}
  <div class="crm-section crm-warning">
    ⚠️ {ts}Always backup your database before running a repair.{/ts}
  </div>
  {/if}

  <div class="crm-block">
    <div class="crm-section">
      <div class="label">{$form.contribution_ids.label}</div>
      <div class="content">{$form.contribution_ids.html}
        <div class="description">{ts}Leave blank to repair all broken contributions up to the limit below.{/ts}</div>
      </div>
    </div>
    <div class="crm-section">
      <div class="label">{$form.limit.label}</div>
      <div class="content">{$form.limit.html}</div>
    </div>
  </div>

  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</div>
