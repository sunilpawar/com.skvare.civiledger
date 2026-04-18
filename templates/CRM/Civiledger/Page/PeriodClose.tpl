{* CiviLedger - Financial Period Close / Lock *}
{crmStyle ext="com.skvare.civiledger" file="css/civiledger.css"}

<div class="civiledger-wrap">

  <div class="civiledger-header">
    <h1><i class="crm-i fa-lock"></i> {ts}Financial Period Close{/ts}</h1>
    <p>{ts}Lock a financial period to prevent corrections on transactions before the lock date. An unlock reason is required to reopen a closed period — this action is fully audited.{/ts}</p>
  </div>

  {* ── Current Status ── *}
  {if $activeLock}
    <div class="civiledger-section" style="border-left:4px solid #dc3545;background:#fff5f5">
      <h2 style="color:#dc3545"><i class="crm-i fa-lock"></i> {ts}Period Currently Locked{/ts}</h2>
      <table class="civiledger-table" style="max-width:600px">
        <tr><th>{ts}Lock Date (before){/ts}</th><td><strong>{$activeLock.lock_date}</strong></td></tr>
        <tr><th>{ts}Locked By{/ts}</th>         <td>{$activeLock.locked_by_name|default:'—'}</td></tr>
        <tr><th>{ts}Locked At{/ts}</th>         <td>{$activeLock.locked_at|crmDate}</td></tr>
        <tr><th>{ts}Reason{/ts}</th>            <td>{$activeLock.lock_reason}</td></tr>
      </table>

      <h3 style="margin-top:20px">{ts}Unlock This Period{/ts}</h3>
      <form method="post">
        {if $cms_type eq 'WordPress'}
          <input type="hidden" name="page" value="CiviCRM" />
        {/if}
        <input type="hidden" name="q" value="civicrm/civiledger/period-close" />
        <input type="hidden" name="operation"  value="unlock">
        <input type="hidden" name="lock_id" value="{$activeLock.id}">
        <input type="hidden" name="reset"   value="1">
        <div style="margin-bottom:10px">
          <label><strong>{ts}Unlock Reason{/ts} *</strong><br>
            <textarea name="unlock_reason" rows="3" style="width:400px;margin-top:4px" required
              placeholder="{ts}Explain why this period is being reopened…{/ts}"></textarea>
          </label>
        </div>
        <button type="submit" class="button" onclick="return confirm('{ts}Unlock this period? This will allow corrections on previously locked transactions.{/ts}')">
          <i class="crm-i fa-unlock"></i> {ts}Unlock Period{/ts}
        </button>
      </form>
    </div>
  {else}
    <div class="civiledger-section" style="border-left:4px solid #28a745;background:#f0fff4">
      <h2 style="color:#28a745"><i class="crm-i fa-unlock"></i> {ts}No Active Lock{/ts}</h2>
      <p>{ts}No financial period is currently locked. Use the form below to close a period.{/ts}</p>
    </div>

    {* ── Lock Form ── *}
    <div class="civiledger-section">
      <h2><i class="crm-i fa-lock"></i> {ts}Close a Period{/ts}</h2>
      <p style="color:#666;font-size:13px">
        {ts}Transactions with a date <strong>before</strong> the lock date will be protected. The lock date day itself remains editable.{/ts}
      </p>
      <form method="post" style="max-width:480px">
        {if $cms_type eq 'WordPress'}
          <input type="hidden" name="page" value="CiviCRM" />
        {/if}
        <input type="hidden" name="q" value="civicrm/civiledger/period-close" />
        <input type="hidden" name="operation" value="lock">
        <input type="hidden" name="reset"  value="1">

        <div style="margin-bottom:14px">
          <label><strong>{ts}Lock Date{/ts} *</strong>
            <br><small style="color:#888">{ts}Transactions before this date will be locked.{/ts}</small>
            <br><input type="date" name="lock_date" value="" max="{$todayDate}" required style="margin-top:4px">
          </label>
        </div>

        <div style="margin-bottom:14px">
          <label><strong>{ts}Reason{/ts} *</strong>
            <br><textarea name="lock_reason" rows="3" style="width:100%;margin-top:4px" required
              placeholder="{ts}e.g. Month-end close for December 2024 — approved by Finance Director{/ts}"></textarea>
          </label>
        </div>

        <button type="submit" class="button"
          onclick="return confirm('{ts}Lock this period? Account corrections on transactions before the lock date will be blocked.{/ts}')">
          <i class="crm-i fa-lock"></i> {ts}Lock Period{/ts}
        </button>
      </form>
    </div>
  {/if}

  {* ── Lock/Unlock Audit History ── *}
  {if $lockHistory}
    <div class="civiledger-section">
      <h2><i class="crm-i fa-history"></i> {ts}Lock / Unlock History{/ts}</h2>
      <table class="civiledger-table">
        <thead>
        <tr>
          <th>{ts}Lock Date (before){/ts}</th>
          <th>{ts}Locked By{/ts}</th>
          <th>{ts}Locked At{/ts}</th>
          <th>{ts}Lock Reason{/ts}</th>
          <th>{ts}Unlocked By{/ts}</th>
          <th>{ts}Unlocked At{/ts}</th>
          <th>{ts}Unlock Reason{/ts}</th>
          <th>{ts}Status{/ts}</th>
        </tr>
        </thead>
        <tbody>
        {foreach from=$lockHistory item=h}
          <tr>
            <td><strong>{$h.lock_date}</strong></td>
            <td>{$h.locked_by_name|default:'—'}</td>
            <td>{$h.locked_at|crmDate}</td>
            <td>{$h.lock_reason}</td>
            <td>{$h.unlocked_by_name|default:'—'}</td>
            <td>{if $h.unlocked_at}{$h.unlocked_at|crmDate}{else}—{/if}</td>
            <td>{$h.unlock_reason|default:'—'}</td>
            <td>
              {if $h.is_active}
                <span class="badge" style="background:#f8d7da;color:#721c24">{ts}Locked{/ts}</span>
              {else}
                <span class="badge" style="background:#d4edda;color:#155724">{ts}Unlocked{/ts}</span>
              {/if}
            </td>
          </tr>
        {/foreach}
        </tbody>
      </table>
    </div>
  {/if}

</div>
