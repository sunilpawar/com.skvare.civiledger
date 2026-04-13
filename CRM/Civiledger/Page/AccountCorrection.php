<?php
/**
 * Page: Account Correction Tool
 * Allows admins to correct FROM/TO accounts on financial transactions
 * using proper double-entry reversal.
 */
class CRM_Civiledger_Page_AccountCorrection extends CRM_Core_Page {

  public function run() {
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');
    CRM_Utils_System::setTitle(ts('CiviLedger — Account Correction Tool'));

    $action = CRM_Utils_Request::retrieve('action', 'String') ?? '';
    $contributionId = (int) CRM_Utils_Request::retrieve('cid', 'Integer');
    $trxnId = (int) CRM_Utils_Request::retrieve('trxn_id', 'Integer');

    // Handle form submission
    if ($action === 'correct' && $trxnId) {
      $this->handleCorrection($trxnId);
      return;
    }

    // Get all financial accounts for dropdowns
    $accounts = CRM_Civiledger_BAO_AccountCorrection::getFinancialAccounts();
    $this->assign('accounts', $accounts);

    // If a contribution is specified, show its transactions
    if ($contributionId) {
      $trxns = CRM_Civiledger_BAO_AccountCorrection::getContributionTrxns($contributionId);
      $contribution = CRM_Core_DAO::executeQuery("
        SELECT c.id, c.total_amount, c.receive_date, c.currency,
               CONCAT(ct.first_name, ' ', ct.last_name) AS contact_name,
               ft.name AS financial_type
        FROM civicrm_contribution c
        LEFT JOIN civicrm_contact ct ON ct.id = c.contact_id
        LEFT JOIN civicrm_financial_type ft ON ft.id = c.financial_type_id
        WHERE c.id = %1
      ", [1 => [$contributionId, 'Integer']])->fetchRow(DB_FETCHMODE_ASSOC);

      $this->assign('trxns', $trxns);
      $this->assign('contribution', $contribution);
      $this->assign('contributionId', $contributionId);
      $this->assign('selectedTrxnId', $trxnId);
    }
    else {
      // Show search form
      $this->assign('showSearch', TRUE);
    }

    $this->assign('auditUrl', CRM_Utils_System::url('civicrm/civiledger/audit-trail'));
    parent::run();
  }

  private function handleCorrection(int $trxnId): void {
    $newFromId = (int) CRM_Utils_Request::retrieve('from_account_id', 'Integer');
    $newToId = (int) CRM_Utils_Request::retrieve('to_account_id', 'Integer');
    $notes = CRM_Utils_Request::retrieve('notes', 'String') ?? '';
    $cid = (int) CRM_Utils_Request::retrieve('cid', 'Integer');

    $result = CRM_Civiledger_BAO_AccountCorrection::correctAccounts(
      $trxnId,
      $newFromId ?: NULL,
      $newToId ?: NULL,
      $notes
    );

    if ($result['success']) {
      CRM_Core_Session::setStatus(
        ts('Account correction applied successfully. Reversal trxn #%1, new trxn #%2.',
          [1 => $result['reversal_trxn_id'], 2 => $result['new_trxn_id']]),
        ts('Success'),
        'success'
      );
    }
    else {
      CRM_Core_Session::setStatus(
        ts('Correction failed: %1', [1 => $result['error']]),
        ts('Error'),
        'error'
      );
    }

    $redirectUrl = CRM_Utils_System::url('civicrm/civiledger/account-correction',
      "cid={$cid}"
    );
    CRM_Utils_System::redirect($redirectUrl);
  }

}
