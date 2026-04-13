{* CiviLedger - Account Correction Tool *}
<div class="civiledger-wrap">
  <div class="civiledger-header">
    <h1><i class="crm-i fa-exchange"></i> Account Correction Tool</h1>
    <p>Correct FROM/TO financial accounts on transactions using proper double-entry reversal.</p>
  </div>

  <div class="civiledger-section correction-explainer">
    <i class="crm-i fa-info-circle"></i>
    <strong>How corrections work:</strong> This tool creates a <em>reversal transaction</em>
    (negative amount) against the old accounts, then a new <em>corrected transaction</em>
    (positive amount) against the correct accounts. The original transaction is not deleted.
    All changes are logged to the CiviLedger audit trail.
  </div>

    {* Search form if no contribution selected *}
    {if $showSearch}
      <div class="civiledger-section">
        <h2>Find a Contribution</h2>
        <form method="get">
          <div class="filter-row">
            <label>Contribution ID: <input type="number" name="cid" placeholder="e.g. 1234" min="1"></label>
            <button type="submit" class="button">Load Transactions</button>
          </div>
        </form>
      </div>
    {/if}

    {* Transactions for selected contribution *}
    {if $contribution}
      <div class="civiledger-section">
        <h2>Contribution #{$contributionId} — {$contribution.contact_name}</h2>
        <p class="text-muted">{$contribution.financial_type} | {$contribution.total_amount|crmMoney} | {$contribution.receive_date|crmDate}</p>
      </div>

        {foreach from=$trxns item=trxn}
          <div class="civiledger-section trxn-correction-block {if $trxn.total_amount < 0}trxn-reversal-block{/if}">
            <div class="trxn-correction-header">
              <span>Trxn #{$trxn.id}</span>
                {if $trxn.total_amount < 0}<span class="badge-reversal">REVERSAL</span>{/if}
                {if $trxn.is_payment}<span class="badge-payment">PAYMENT</span>{/if}
              <span class="amount-badge">{$trxn.total_amount|crmMoney:$trxn.currency}</span>
              <span class="text-muted">{$trxn.trxn_date|crmDate}</span>
            </div>

              {* Current flow *}
            <div class="trxn-flow-display">
              <div class="flow-from">
                <span class="flow-label">FROM</span>
                <span class="flow-value">{$trxn.from_account_name|default:'(none)'}</span>
                <span class="flow-id text-muted">[ID: {$trxn.from_account_name} ({$trxn.from_financial_account_id})]</span>
              </div>
              <div class="flow-arrow">→</div>
              <div class="flow-to">
                <span class="flow-label">TO</span>
                <span class="flow-value">{$trxn.to_account_name|default:'(none)'}</span>
                <span class="flow-id text-muted">[ID: {$trxn.to_account_name} ({$trxn.to_financial_account_id})]</span>
              </div>
            </div>

              {* Only allow correction on positive transactions *}
              {if $trxn.total_amount >= 0}
                <div class="correction-form-wrap {if $selectedTrxnId == $trxn.id}correction-form-open{/if}">
                  <button class="button small toggle-correction-form" data-trxn="{$trxn.id}">
                    <i class="crm-i fa-pencil"></i> Correct Accounts
                  </button>

                  <form method="post" class="correction-form" id="form-{$trxn.id}" style="display:none">
                    <input type="hidden" name="action" value="correct">
                    <input type="hidden" name="trxn_id" value="{$trxn.id}">
                    <input type="hidden" name="cid" value="{$contributionId}">

                    <div class="correction-fields">
                      <div class="correction-field">
                        <label>New FROM Account:
                          <select name="from_financial_account_id">
                            <option value="">— Keep current ({$trxn.from_account_name|default:'none'}) —</option>
                              {foreach from=$accounts item=acct}
                                <option value="{$acct.id}" {if $acct.id == $trxn.from_financial_account_id}selected{/if}>
                                  [{$acct.account_type}] {$acct.name}
                                </option>
                              {/foreach}
                          </select>
                        </label>
                      </div>
                      <div class="correction-field">
                        <label>New TO Account:
                          <select name="to_financial_account_id">
                            <option value="">— Keep current ({$trxn.to_account_name|default:'none'}) —</option>
                              {foreach from=$accounts item=acct}
                                <option value="{$acct.id}" {if $acct.id == $trxn.to_financial_account_id}selected{/if}>
                                  [{$acct.account_type}] {$acct.name}
                                </option>
                              {/foreach}
                          </select>
                        </label>
                      </div>
                      <div class="correction-field full-width">
                        <label>Reason for correction (required):
                          <input type="text" name="notes" required placeholder="e.g. Wrong payment processor account assigned">
                        </label>
                      </div>
                    </div>

                    <div class="correction-actions">
                      <button type="submit" class="button crm-button-type-delete"
                              onclick="return confirm('Apply double-entry reversal and create corrected transaction?')">
                        <i class="crm-i fa-exchange"></i> Apply Correction
                      </button>
                      <button type="button" class="button cancel-correction" data-trxn="{$trxn.id}">Cancel</button>
                    </div>
                  </form>
                </div>
              {else}
                <p class="text-muted small">Reversal transactions cannot be corrected directly.</p>
              {/if}
          </div>
        {/foreach}

      <div class="correction-nav">
        <a href="{$auditUrl}?cid={$contributionId}" class="button">View Full Audit Trail</a>
        <a href="?" class="button">Search Another Contribution</a>
      </div>
    {/if}

</div>

<script>
  document.querySelectorAll('.toggle-correction-form').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var form = document.getElementById('form-' + this.dataset.trxn);
      form.style.display = form.style.display === 'none' ? 'block' : 'none';
    });
  });
  document.querySelectorAll('.cancel-correction').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.getElementById('form-' + this.dataset.trxn).style.display = 'none';
    });
  });
</script>
