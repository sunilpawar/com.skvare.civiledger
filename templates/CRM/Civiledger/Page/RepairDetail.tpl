{* CiviLedger — Repair Detail Template
   URL: /civicrm/civiledger/repair-detail?cid=XXXX
   Run: /civicrm/civiledger/repair-detail?cid=XXXX&action=run
*}

{* ── Macro: chain checklist row ─────────────────────────────────────────── *}
{capture assign="iconOk"}<span class="rd-check-icon rd-ok">✔</span>{/capture}
{capture assign="iconFail"}<span class="rd-check-icon rd-fail">✘</span>{/capture}

<div class="civiledger-wrap rd-wrap">

    {* ── Navigation ──────────────────────────────────────────────────────── *}
  <div class="rd-nav">
    <a href="{$backUrl}" class="button small">← {ts}Back to Repair Tool{/ts}</a>
    <a href="{$auditUrl}" class="button small">📊 {ts}Audit Trail{/ts}</a>
    <a href="{$contribUrl}" class="button small" target="_blank">🔗 {ts}View Contribution{/ts}</a>
  </div>

    {* ── Page header ─────────────────────────────────────────────────────── *}
  <div class="civiledger-header">
    <h1>🛠 {ts}Financial Chain Repair{/ts} — {ts}Contribution{/ts} #{$contributionId}</h1>
    <p>{ts}Step-by-step analysis, execution log, and post-repair validation.{/ts}</p>
  </div>

    {* ── Contribution summary card ───────────────────────────────────────── *}
  <div class="civiledger-section rd-contrib-card">
    <h2>{ts}Contribution Details{/ts}</h2>
    <div class="rd-meta-grid">
      <div class="rd-meta-item"><span class="rd-meta-label">{ts}Contact{/ts}</span><span class="rd-meta-value"><strong>{$contribution.contact_name}</strong></span></div>
      <div class="rd-meta-item"><span class="rd-meta-label">{ts}Amount{/ts}</span><span class="rd-meta-value rd-amount">{$contribution.total_amount|crmMoney:$contribution.currency}</span></div>
      <div class="rd-meta-item"><span class="rd-meta-label">{ts}Date{/ts}</span><span class="rd-meta-value">{$contribution.receive_date|crmDate}</span></div>
      <div class="rd-meta-item"><span class="rd-meta-label">{ts}Status{/ts}</span><span class="rd-meta-value"><span class="status-badge status-{$contribution.contribution_status_id|lower}">{$contribution.status_label}</span></span></div>
      <div class="rd-meta-item"><span class="rd-meta-label">{ts}Financial Type{/ts}</span><span class="rd-meta-value">{$contribution.financial_type_name}</span></div>
      <div class="rd-meta-item"><span class="rd-meta-label">{ts}Payment Method{/ts}</span><span class="rd-meta-value">{$contribution.payment_instrument|default:'—'}</span></div>
        {if $contribution.trxn_id}<div class="rd-meta-item"><span class="rd-meta-label">{ts}Processor Ref{/ts}</span><span class="rd-meta-value rd-mono">{$contribution.trxn_id}</span></div>{/if}
        {if $contribution.source}<div class="rd-meta-item"><span class="rd-meta-label">{ts}Source{/ts}</span><span class="rd-meta-value">{$contribution.source}</span></div>{/if}
    </div>
  </div>

    {* ── Pre-repair chain analysis ───────────────────────────────────────── *}
  <div class="civiledger-section">
    <h2>
        {if $repairRan}{ts}Chain State — Before Repair{/ts}{else}{ts}Chain Analysis — Current State{/ts}{/if}
      &nbsp;
        {if $preChain.checks.is_complete}
          <span class="rd-badge rd-badge-ok">✔ {ts}Complete{/ts}</span>
        {else}
          <span class="rd-badge rd-badge-fail">✘ {ts}Broken{/ts}</span>
        {/if}
    </h2>

    <div class="rd-chain-grid">

        {* Layer: Line Items *}
      <div class="rd-chain-layer {if $preChain.checks.has_line_items}rd-layer-ok{else}rd-layer-fail{/if}">
        <div class="rd-layer-header">
            {if $preChain.checks.has_line_items}{$iconOk}{else}{$iconFail}{/if}
          <strong>{ts}Line Items{/ts}</strong>
          <span class="rd-count">{$preChain.counts.line_items}</span>
        </div>
          {if $preChain.line_items}
            <ul class="rd-detail-list">
                {foreach from=$preChain.line_items item=li}
                  <li><code>#{$li.id}</code> {$li.label|default:$li.financial_type_name} — {$li.line_total|crmMoney}</li>
                {/foreach}
            </ul>
          {else}
            <p class="rd-missing-msg">⚠ {ts}No line items found — repair will create a default line item from contribution data.{/ts}</p>
          {/if}
      </div>

        {* Layer: Financial Items *}
      <div class="rd-chain-layer {if $preChain.checks.has_financial_items}rd-layer-ok{else}rd-layer-fail{/if}">
        <div class="rd-layer-header">
            {if $preChain.checks.has_financial_items}{$iconOk}{else}{$iconFail}{/if}
          <strong>{ts}Financial Items{/ts}</strong>
          <span class="rd-count">{$preChain.counts.financial_items}</span>
        </div>
          {if $preChain.financial_items}
            <ul class="rd-detail-list">
                {foreach from=$preChain.financial_items item=fi}
                  <li><code>#{$fi.id}</code> {$fi.account_name} — {$fi.amount|crmMoney}</li>
                {/foreach}
            </ul>
          {else}
            <p class="rd-missing-msg">⚠ {ts}No financial items — income account records are missing.{/ts}</p>
          {/if}
      </div>

        {* Layer: Financial Transactions *}
      <div class="rd-chain-layer {if $preChain.checks.has_financial_trxns}rd-layer-ok{else}rd-layer-fail{/if}">
        <div class="rd-layer-header">
            {if $preChain.checks.has_financial_trxns}{$iconOk}{else}{$iconFail}{/if}
          <strong>{ts}Financial Transactions{/ts}</strong>
          <span class="rd-count">{$preChain.counts.financial_trxns}</span>
        </div>
          {if $preChain.financial_trxns}
            <ul class="rd-detail-list">
                {foreach from=$preChain.financial_trxns item=ft}
                  <li><code>#{$ft.id}</code> {$ft.from_account} → {$ft.to_account} &nbsp; {$ft.total_amount|crmMoney}</li>
                {/foreach}
            </ul>
          {else}
            <p class="rd-missing-msg">⚠ {ts}No payment transaction linked — one will be created from contribution data.{/ts}</p>
          {/if}
      </div>

        {* EFT: Contribution link *}
      <div class="rd-chain-layer {if $preChain.checks.has_eft_contribution}rd-layer-ok{else}rd-layer-fail{/if}">
        <div class="rd-layer-header">
            {if $preChain.checks.has_eft_contribution}{$iconOk}{else}{$iconFail}{/if}
          <strong>{ts}EFT → Contribution Link{/ts}</strong>
        </div>
          {if !$preChain.checks.has_eft_contribution}
            <p class="rd-missing-msg">⚠ {ts}entity_financial_trxn row linking this contribution to its transaction is missing.{/ts}</p>
          {/if}
      </div>

        {* EFT: Financial Item links *}
      <div class="rd-chain-layer {if $preChain.checks.has_eft_fi_all}rd-layer-ok{else}rd-layer-fail{/if}">
        <div class="rd-layer-header">
            {if $preChain.checks.has_eft_fi_all}{$iconOk}{else}{$iconFail}{/if}
          <strong>{ts}EFT → Financial Item Links{/ts}</strong>
          <span class="rd-count">{$preChain.counts.fi_with_eft}/{$preChain.counts.fi_total}</span>
        </div>
          {if $preChain.financial_items}
            <ul class="rd-detail-list">
                {foreach from=$preChain.financial_items item=fi}
                    {assign var=fiId value=$fi.id}
                  <li>
                      {if $preChain.eft_by_fi[$fiId]}<span class="rd-check-icon rd-ok">✔</span>{else}<span class="rd-check-icon rd-fail">✘</span>{/if}
                      {ts}FI{/ts} <code>#{$fi.id}</code> ({$fi.account_name})
                      {if !$preChain.eft_by_fi[$fiId]}<span class="rd-missing-inline">{ts}missing link{/ts}</span>{/if}
                  </li>
                {/foreach}
            </ul>
          {/if}
      </div>

        {* Amounts *}
      <div class="rd-chain-layer {if $preChain.checks.amounts_match}rd-layer-ok{else}rd-layer-fail{/if}">
        <div class="rd-layer-header">
            {if $preChain.checks.amounts_match}{$iconOk}{else}{$iconFail}{/if}
          <strong>{ts}Amount Reconciliation{/ts}</strong>
        </div>
          {if !$preChain.checks.amounts_match}
            <p class="rd-missing-msg">⚠ {ts}Transaction totals do not match contribution.total_amount.{/ts}</p>
          {/if}
      </div>

    </div>{* .rd-chain-grid *}
  </div>

    {* ── Confirm / run button (shown ONLY before repair) ─────────────────── *}
    {if !$repairRan}
        {if $preChain.checks.is_complete}
          <div class="civiledger-section rd-already-ok">
            <span class="rd-badge rd-badge-ok" style="font-size:15px">✔</span>
            <strong>{ts}This contribution's financial chain is already complete.{/ts}</strong>
              {ts}No repair is necessary.{/ts}
          </div>
        {else}
          <div class="civiledger-section rd-confirm-box">
            <p>⚠ <strong>{ts}Backup your database before running a repair on production.{/ts}</strong>
                {ts}The repair tool will create only missing records — it will not overwrite or delete existing rows.{/ts}
            </p>
            <a href="{$runUrl}" class="button crm-button-type-delete rd-run-btn"
               onclick="return confirm('{ts 1=$contributionId}Run repair for contribution #%1? Missing financial chain records will be created.{/ts}')">
              🛠 {ts}Run Repair Now{/ts}
            </a>
          </div>
        {/if}
    {/if}

    {* ── Repair execution log ────────────────────────────────────────────── *}
    {if $repairRan}

        {* Summary bar *}
      <div class="civiledger-section">
        <h2>{ts}Repair Execution Log{/ts}</h2>

        <div class="rd-summary-bar">
          <div class="rd-summary-item rd-sum-fixed">
            <span class="rd-sum-num">{$logSummary.fixed}</span>
            <span class="rd-sum-label">{ts}Created{/ts}</span>
          </div>
          <div class="rd-summary-item rd-sum-skip">
            <span class="rd-sum-num">{$logSummary.skipped}</span>
            <span class="rd-sum-label">{ts}Already Existed{/ts}</span>
          </div>
          <div class="rd-summary-item rd-sum-warn">
            <span class="rd-sum-num">{$logSummary.warning}</span>
            <span class="rd-sum-label">{ts}Warnings{/ts}</span>
          </div>
          <div class="rd-summary-item rd-sum-error">
            <span class="rd-sum-num">{$logSummary.error}</span>
            <span class="rd-sum-label">{ts}Errors{/ts}</span>
          </div>
        </div>

          {* Step-by-step log entries *}
        <div class="rd-log">
            {foreach from=$repairLog item=entry}
                {if isset($entry.info)}
                  <div class="rd-log-row rd-log-info">
                    <span class="rd-log-icon">ℹ</span>
                    <span class="rd-log-text">{$entry.info}</span>
                  </div>
                {elseif isset($entry.fixed)}
                  <div class="rd-log-row rd-log-fixed">
                    <span class="rd-log-icon">✔</span>
                    <span class="rd-log-label">{ts}CREATED{/ts}</span>
                    <span class="rd-log-text">{$entry.fixed}</span>
                  </div>
                {elseif isset($entry.skip)}
                  <div class="rd-log-row rd-log-skip">
                    <span class="rd-log-icon">—</span>
                    <span class="rd-log-label">{ts}EXISTS{/ts}</span>
                    <span class="rd-log-text">{$entry.skip}</span>
                  </div>
                {elseif isset($entry.warning)}
                  <div class="rd-log-row rd-log-warning">
                    <span class="rd-log-icon">⚠</span>
                    <span class="rd-log-label">{ts}WARNING{/ts}</span>
                    <span class="rd-log-text">{$entry.warning}</span>
                  </div>
                {elseif isset($entry.error)}
                  <div class="rd-log-row rd-log-error">
                    <span class="rd-log-icon">✘</span>
                    <span class="rd-log-label">{ts}ERROR{/ts}</span>
                    <span class="rd-log-text">{$entry.error}</span>
                  </div>
                {/if}
            {/foreach}
        </div>
      </div>

        {* ── Post-repair chain analysis ──────────────────────────────────────── *}
      <div class="civiledger-section">
        <h2>
            {ts}Chain State — After Repair{/ts}
          &nbsp;
            {if $postChain.checks.is_complete}
              <span class="rd-badge rd-badge-ok">✔ {ts}Complete{/ts}</span>
            {else}
              <span class="rd-badge rd-badge-fail">✘ {ts}Still Broken{/ts}</span>
            {/if}
        </h2>

        <div class="rd-chain-grid">

            {* Line Items *}
          <div class="rd-chain-layer {if $postChain.checks.has_line_items}rd-layer-ok{else}rd-layer-fail{/if}">
            <div class="rd-layer-header">
                {if $postChain.checks.has_line_items}{$iconOk}{else}{$iconFail}{/if}
              <strong>{ts}Line Items{/ts}</strong>
              <span class="rd-count">{$postChain.counts.line_items}</span>
            </div>
              {if $postChain.line_items}
                <ul class="rd-detail-list">
                    {foreach from=$postChain.line_items item=li}
                      <li><code>#{$li.id}</code> {$li.label|default:$li.financial_type_name} — {$li.line_total|crmMoney}</li>
                    {/foreach}
                </ul>
              {/if}
          </div>

            {* Financial Items *}
          <div class="rd-chain-layer {if $postChain.checks.has_financial_items}rd-layer-ok{else}rd-layer-fail{/if}">
            <div class="rd-layer-header">
                {if $postChain.checks.has_financial_items}{$iconOk}{else}{$iconFail}{/if}
              <strong>{ts}Financial Items{/ts}</strong>
              <span class="rd-count">{$postChain.counts.financial_items}</span>
            </div>
              {if $postChain.financial_items}
                <ul class="rd-detail-list">
                    {foreach from=$postChain.financial_items item=fi}
                      <li><code>#{$fi.id}</code> {$fi.account_name} — {$fi.amount|crmMoney}</li>
                    {/foreach}
                </ul>
              {/if}
          </div>

            {* Financial Transactions *}
          <div class="rd-chain-layer {if $postChain.checks.has_financial_trxns}rd-layer-ok{else}rd-layer-fail{/if}">
            <div class="rd-layer-header">
                {if $postChain.checks.has_financial_trxns}{$iconOk}{else}{$iconFail}{/if}
              <strong>{ts}Financial Transactions{/ts}</strong>
              <span class="rd-count">{$postChain.counts.financial_trxns}</span>
            </div>
              {if $postChain.financial_trxns}
                <ul class="rd-detail-list">
                    {foreach from=$postChain.financial_trxns item=ft}
                      <li><code>#{$ft.id}</code> {$ft.from_account} → {$ft.to_account} &nbsp; {$ft.total_amount|crmMoney}</li>
                    {/foreach}
                </ul>
              {/if}
          </div>

            {* EFT: Contribution *}
          <div class="rd-chain-layer {if $postChain.checks.has_eft_contribution}rd-layer-ok{else}rd-layer-fail{/if}">
            <div class="rd-layer-header">
                {if $postChain.checks.has_eft_contribution}{$iconOk}{else}{$iconFail}{/if}
              <strong>{ts}EFT → Contribution Link{/ts}</strong>
            </div>
          </div>

            {* EFT: Financial Items *}
          <div class="rd-chain-layer {if $postChain.checks.has_eft_fi_all}rd-layer-ok{else}rd-layer-fail{/if}">
            <div class="rd-layer-header">
                {if $postChain.checks.has_eft_fi_all}{$iconOk}{else}{$iconFail}{/if}
              <strong>{ts}EFT → Financial Item Links{/ts}</strong>
              <span class="rd-count">{$postChain.counts.fi_with_eft}/{$postChain.counts.fi_total}</span>
            </div>
              {if $postChain.financial_items}
                <ul class="rd-detail-list">
                    {foreach from=$postChain.financial_items item=fi}
                        {assign var=fiId value=$fi.id}
                      <li>
                          {if $postChain.eft_by_fi[$fiId]}<span class="rd-check-icon rd-ok">✔</span>{else}<span class="rd-check-icon rd-fail">✘</span>{/if}
                          {ts}FI{/ts} <code>#{$fi.id}</code>
                          {if !$postChain.eft_by_fi[$fiId]}<span class="rd-missing-inline">{ts}still missing{/ts}</span>{/if}
                      </li>
                    {/foreach}
                </ul>
              {/if}
          </div>

            {* Amounts *}
          <div class="rd-chain-layer {if $postChain.checks.amounts_match}rd-layer-ok{else}rd-layer-fail{/if}">
            <div class="rd-layer-header">
                {if $postChain.checks.amounts_match}{$iconOk}{else}{$iconFail}{/if}
              <strong>{ts}Amount Reconciliation{/ts}</strong>
            </div>
              {if !$postChain.checks.amounts_match}
                <p class="rd-missing-msg">⚠ {ts}Amounts still do not reconcile — manual review needed.{/ts}</p>
              {/if}
          </div>

        </div>{* .rd-chain-grid *}
      </div>

        {* ── Final status + actions ───────────────────────────────────────── *}
      <div class="civiledger-section rd-final-status">
          {if $postChain.checks.is_complete}
            <div class="rd-final-ok">
              <span style="font-size:24px">✔</span>
              <div>
                <strong>{ts}Repair Complete — Chain is now fully intact.{/ts}</strong><br>
                <span>{ts 1=$logSummary.fixed}%1 record(s) created.{/ts}
                    {if $logSummary.skipped} {ts 1=$logSummary.skipped}%1 already existed.{/ts}{/if}
            </span>
              </div>
            </div>
          {else}
            <div class="rd-final-fail">
              <span style="font-size:24px">✘</span>
              <div>
                <strong>{ts}Repair finished but the chain still has issues.{/ts}</strong><br>
                <span>{ts}Review the warnings and errors above. Manual intervention may be required.{/ts}</span>
              </div>
            </div>
          {/if}

        <div class="rd-final-actions">
          <a href="{$auditUrl}"  class="button">📊 {ts}View Audit Trail{/ts}</a>
          <a href="{$runUrl}"    class="button">{ts}↺ Run Repair Again{/ts}</a>
          <a href="{$backUrl}"   class="button">{ts}← Back to Repair Tool{/ts}</a>
        </div>
      </div>

    {/if}{* /repairRan *}

</div>{* .rd-wrap *}

{* ── Page-specific styles ──────────────────────────────────────────────── *}
<style>
  /* Navigation */
  .rd-nav { display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap; }

  /* Contribution meta grid */
  .rd-meta-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:10px 20px; }
  .rd-meta-item { display:flex; flex-direction:column; gap:2px; }
  .rd-meta-label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#6c757d; }
  .rd-meta-value { font-size:14px; color:#2c3e50; }
  .rd-amount { font-size:18px; font-weight:700; color:#1a3a5c; }
  .rd-mono { font-family:monospace; font-size:13px; }

  /* Chain grid */
  .rd-chain-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:12px; margin-top:12px; }
  .rd-chain-layer { border:1px solid #dde3ea; border-radius:6px; padding:12px 14px; }
  .rd-layer-ok   { border-left:4px solid #28a745; background:#f6fff8; }
  .rd-layer-fail { border-left:4px solid #dc3545; background:#fff5f5; }
  .rd-layer-header { display:flex; align-items:center; gap:8px; margin-bottom:8px; font-size:13px; }
  .rd-count { margin-left:auto; background:#eef2f8; border-radius:10px; padding:1px 8px; font-size:12px; font-weight:600; }

  /* Check icons */
  .rd-check-icon { display:inline-block; width:18px; height:18px; line-height:18px; text-align:center; border-radius:50%; font-size:11px; font-weight:700; }
  .rd-ok   { background:#d4edda; color:#155724; }
  .rd-fail { background:#f8d7da; color:#721c24; }

  /* Badges */
  .rd-badge { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:700; }
  .rd-badge-ok   { background:#d4edda; color:#155724; }
  .rd-badge-fail { background:#f8d7da; color:#721c24; }

  /* Detail lists inside chain layers */
  .rd-detail-list { margin:4px 0 0 4px; padding:0; list-style:none; font-size:12px; color:#444; }
  .rd-detail-list li { padding:2px 0; border-bottom:1px dotted #eee; }
  .rd-detail-list code { background:#eef2f8; border-radius:3px; padding:0 4px; font-size:11px; }
  .rd-missing-msg { font-size:12px; color:#856404; background:#fff3cd; padding:5px 8px; border-radius:4px; margin:4px 0 0; }
  .rd-missing-inline { font-size:11px; color:#dc3545; font-style:italic; margin-left:4px; }

  /* Confirm box */
  .rd-confirm-box { background:#fff8e1; border:1px solid #ffe082; }
  .rd-run-btn { font-size:15px; padding:10px 22px; margin-top:10px; }
  .rd-already-ok { background:#f0fff4; border:1px solid #b2dfdb; display:flex; align-items:center; gap:12px; font-size:15px; }

  /* Summary bar */
  .rd-summary-bar { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
  .rd-summary-item { flex:1; min-width:100px; border-radius:6px; padding:12px 16px; text-align:center; }
  .rd-sum-fixed { background:#d4edda; }
  .rd-sum-skip  { background:#e9ecef; }
  .rd-sum-warn  { background:#fff3cd; }
  .rd-sum-error { background:#f8d7da; }
  .rd-sum-num   { display:block; font-size:26px; font-weight:700; line-height:1.1; }
  .rd-sum-label { display:block; font-size:11px; text-transform:uppercase; letter-spacing:.04em; margin-top:4px; color:#555; }

  /* Log entries */
  .rd-log { font-family:monospace; font-size:13px; background:#1e1e2e; border-radius:6px; padding:14px; max-height:420px; overflow-y:auto; }
  .rd-log-row { display:flex; align-items:baseline; gap:8px; padding:4px 0; border-bottom:1px solid rgba(255,255,255,.05); }
  .rd-log-icon  { width:16px; text-align:center; flex-shrink:0; }
  .rd-log-label { font-size:10px; font-weight:700; letter-spacing:.06em; width:62px; flex-shrink:0; text-transform:uppercase; }
  .rd-log-text  { color:#cdd6f4; word-break:break-word; }
  .rd-log-info    .rd-log-icon { color:#89b4fa; } .rd-log-info    .rd-log-label { color:#89b4fa; }
  .rd-log-fixed   .rd-log-icon { color:#a6e3a1; } .rd-log-fixed   .rd-log-label { color:#a6e3a1; }
  .rd-log-skip    .rd-log-icon { color:#6c7086; } .rd-log-skip    .rd-log-label { color:#6c7086; } .rd-log-skip .rd-log-text { color:#6c7086; }
  .rd-log-warning .rd-log-icon { color:#f9e2af; } .rd-log-warning .rd-log-label { color:#f9e2af; } .rd-log-warning .rd-log-text { color:#f9e2af; }
  .rd-log-error   .rd-log-icon { color:#f38ba8; } .rd-log-error   .rd-log-label { color:#f38ba8; } .rd-log-error   .rd-log-text { color:#f38ba8; font-weight:bold; }

  /* Final status */
  .rd-final-status { display:flex; flex-direction:column; gap:16px; }
  .rd-final-ok  { display:flex; align-items:center; gap:16px; background:#d4edda; border:1px solid #c3e6cb; border-radius:6px; padding:16px 20px; color:#155724; }
  .rd-final-fail{ display:flex; align-items:center; gap:16px; background:#f8d7da; border:1px solid #f5c6cb; border-radius:6px; padding:16px 20px; color:#721c24; }
  .rd-final-actions { display:flex; gap:10px; flex-wrap:wrap; }

  @media(max-width:600px){
    .rd-chain-grid { grid-template-columns:1fr; }
    .rd-meta-grid  { grid-template-columns:1fr; }
    .rd-summary-bar{ flex-direction:column; }
  }
</style>
