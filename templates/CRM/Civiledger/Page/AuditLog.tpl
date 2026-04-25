{* CiviLedger — Audit Log Template *}
<div class="civiledger-wrap">

  <div class="civiledger-header">
    <h1><i class="crm-i fa-shield"></i> {ts}CiviLedger Audit Log{/ts}</h1>
    <p>{ts}Hash-chained record of every repair, correction, and period lock. Use the Verify Chain button to confirm no entries have been tampered with.{/ts}</p>
  </div>

  {* ── Chain verification result ── *}
  {if $chainResult}
    {if $chainResult.valid}
      <div class="al-chain-ok">
        <i class="crm-i fa-check-circle"></i>
        <strong>{ts}Chain Integrity: VALID{/ts}</strong> — {$chainResult.message}
      </div>
    {else}
      <div class="al-chain-broken">
        <i class="crm-i fa-exclamation-triangle"></i>
        <strong>{ts}Chain Integrity: BROKEN{/ts}</strong> — {$chainResult.message}
        <br><small>{ts}One or more audit entries may have been altered or deleted. Contact your database administrator immediately.{/ts}</small>
      </div>
    {/if}
  {/if}

  {* ── Filter bar ── *}
  <div class="civiledger-filter-bar">
    <form method="get">
      {if $cms_type eq 'WordPress'}
        <input type="hidden" name="page"  value="CiviCRM" />
      {/if}
      <input type="hidden" name="q"     value="civicrm/civiledger/audit-log" />
      <input type="hidden" name="reset" value="1" />

      <label>{ts}Type{/ts}:
        <select name="event_type">
          {foreach from=$eventTypes key=val item=label}
            <option value="{$val}"{if $val == $eventType} selected="selected"{/if}>{$label}</option>
          {/foreach}
        </select>
      </label>

      <label>{ts}From{/ts}: <input type="date" name="date_from" value="{$dateFrom}"></label>
      <label>{ts}To{/ts}:   <input type="date" name="date_to"   value="{$dateTo}"></label>

      <button type="submit" class="button">{ts}Filter{/ts}</button>
      <a href="{$verifyUrl}" class="button al-verify-btn">
        <i class="crm-i fa-link"></i> {ts}Verify Chain{/ts}
      </a>
    </form>
  </div>

  {* ── Entry count ── *}
  <p style="margin:0 0 10px;font-size:13px;color:#666">
    {ts 1=$total}%1 total entries{/ts}
    {if $entries|@count < $total}
      &nbsp;|&nbsp; {ts 1=$page}Page %1{/ts}
    {/if}
  </p>

  {* ── Log table ── *}
  {if $entries}
    <div class="civiledger-section" style="padding:0">
      <table class="civiledger-table al-table">
        <thead>
        <tr>
          <th style="width:50px">{ts}#ID{/ts}</th>
          <th style="width:130px">{ts}Date / Time{/ts}</th>
          <th style="width:120px">{ts}Event{/ts}</th>
          <th style="width:120px">{ts}Entity{/ts}</th>
          <th style="width:80px">{ts}Entity ID{/ts}</th>
          <th>{ts}Actor{/ts}</th>
          <th style="width:90px">{ts}Detail{/ts}</th>
          <th style="width:80px;font-family:monospace;font-size:11px">{ts}Hash (8){/ts}</th>
        </tr>
        </thead>
        <tbody>
        {foreach from=$entries item=entry}
          <tr class="al-row" id="al-row-{$entry.id}">
            <td><code>{$entry.id}</code></td>
            <td style="white-space:nowrap;font-size:12px">{$entry.logged_at|crmDate:'%Y-%m-%d %H:%M:%S'}</td>
            <td>
              <span class="al-badge al-badge-{$entry.event_type|lower}">{$entry.event_type}</span>
            </td>
            <td style="font-size:12px">{$entry.entity_type|default:'—'}</td>
            <td style="text-align:center">
              {if $entry.entity_id}
                {if $entry.entity_type == 'contribution'}
                  <a href="{crmURL p='civicrm/civiledger/audit-trail' q="reset=1&contribution_id=`$entry.entity_id`"}">{$entry.entity_id}</a>
                {else}
                  {$entry.entity_id}
                {/if}
              {else}—{/if}
            </td>
            <td>{$entry.actor_name|default:'—'}</td>
            <td>
              {if $entry.detail_decoded}
                <button type="button" class="button small al-detail-btn"
                        onclick="alToggleDetail({$entry.id})">
                  {ts}View{/ts}
                </button>
              {else}—{/if}
            </td>
            <td style="font-family:monospace;font-size:11px;color:#888">
              {$entry.entry_hash|truncate:8:'':true}
            </td>
          </tr>
          {if $entry.detail_decoded}
            <tr class="al-detail-row" id="al-detail-{$entry.id}" style="display:none">
              <td colspan="8" style="padding:10px 16px;background:#f8f9fa">
                <pre class="al-detail-pre">{$entry.detail}</pre>
              </td>
            </tr>
          {/if}
        {/foreach}
        </tbody>
      </table>
    </div>

    {* ── Pagination ── *}
    <div class="civiledger-pagination">
      {if $hasPrev}
        <a href="{crmURL p='civicrm/civiledger/audit-log' q="reset=1&page=`$page-1`&date_from=`$dateFrom`&date_to=`$dateTo`&event_type=`$eventType`"}"
           class="button small">{ts}← Previous{/ts}</a>
      {/if}
      &nbsp;{ts 1=$page}Page %1{/ts}&nbsp;
      {if $hasMore}
        <a href="{crmURL p='civicrm/civiledger/audit-log' q="reset=1&page=`$page+1`&date_from=`$dateFrom`&date_to=`$dateTo`&event_type=`$eventType`"}"
           class="button small">{ts}Next →{/ts}</a>
      {/if}
    </div>

  {else}
    <div class="civiledger-section">
      <p class="civiledger-empty">
        <i class="crm-i fa-info-circle"></i>
        {ts}No audit log entries found for the selected filters.{/ts}
      </p>
    </div>
  {/if}

</div>

<style>
.al-chain-ok    { margin:0 0 16px; padding:12px 16px; background:#d4edda; color:#155724; border-radius:4px; border-left:4px solid #28a745; }
.al-chain-broken{ margin:0 0 16px; padding:12px 16px; background:#f8d7da; color:#721c24; border-radius:4px; border-left:4px solid #dc3545; }
.al-table td    { vertical-align:middle; }
.al-badge       { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700; text-transform:uppercase; }
.al-badge-repair       { background:#cce5ff; color:#004085; }
.al-badge-correction   { background:#fff3cd; color:#856404; }
.al-badge-period_lock  { background:#d4edda; color:#155724; }
.al-badge-period_unlock{ background:#ffeeba; color:#6d4c00; }
.al-detail-pre  { margin:0; white-space:pre-wrap; word-break:break-all; font-size:12px; color:#333; background:transparent; border:none; }
.al-verify-btn  { background:#17a2b8 !important; color:#fff !important; border-color:#17a2b8 !important; }
</style>
<script>
function alToggleDetail(id) {
  var row = document.getElementById('al-detail-' + id);
  if (row) { row.style.display = row.style.display === 'none' ? 'table-row' : 'none'; }
}
</script>
