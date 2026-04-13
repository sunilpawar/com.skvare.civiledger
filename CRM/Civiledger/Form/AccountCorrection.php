<?php
/**
 * CiviLedger - Feature 6: Account Correction Tool Form
 */
class CRM_Civiledger_Form_AccountCorrection extends CRM_Core_Form {

  protected $_trxnId = NULL;
  protected $_trxn = NULL;

  public function preProcess() {
    $this->_trxnId = CRM_Utils_Request::retrieve('trxn_id', 'Positive');
    if ($this->_trxnId) {
      $this->_trxn = CRM_Civiledger_BAO_AccountCorrection::getTransaction((int) $this->_trxnId);
    }
    $this->assign('trxn', $this->_trxn);
    $this->assign('trxnId', $this->_trxnId);

    if ($this->_trxnId && $this->_trxn) {
      $history = CRM_Civiledger_BAO_AccountCorrection::getCorrectionHistory((int) $this->_trxnId);
      $this->assign('history', $history);
    }
  }

  public function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('CiviLedger — Account Correction Tool'));

    $accountOptions = CRM_Civiledger_BAO_AccountBalance::getAccountOptions();

    // Search fields
    $this->add('text', 'search_contribution_id', ts('Contribution ID'));
    $this->add('datepicker', 'search_date_from', ts('Date From'), [], FALSE, ['time' => FALSE]);
    $this->add('datepicker', 'search_date_to', ts('Date To'), [], FALSE, ['time' => FALSE]);
    $this->addButtons([['type' => 'submit', 'name' => ts('Search Transactions'), 'isDefault' => TRUE]]);

    // Correction form (only shown when a trxn is selected)
    if ($this->_trxnId && $this->_trxn) {
      $this->add('select', 'new_from_account_id', ts('New FROM Account'), $accountOptions);
      $this->add('select', 'new_to_account_id', ts('New TO Account'), $accountOptions);
      $this->add('textarea', 'reason', ts('Reason for Correction'), ['rows' => 3, 'cols' => 60], TRUE);
      $this->addRule('reason', ts('Reason is required'), 'required');
      $this->addButtons([
        ['type' => 'submit', 'name' => ts('Apply Correction'), 'isDefault' => TRUE],
        ['type' => 'cancel', 'name' => ts('Cancel')],
      ]);
    }

    // Search results
    $searchParams = [];
    if (CRM_Utils_Request::retrieve('search_contribution_id', 'Positive')) {
      $searchParams['contribution_id'] = CRM_Utils_Request::retrieve('search_contribution_id', 'Positive');
    }
    if (!empty($_GET['search_date_from'])) {
      $searchParams['date_from'] = $_GET['search_date_from'];
    }
    if (!empty($_GET['search_date_to'])) {
      $searchParams['date_to'] = $_GET['search_date_to'];
    }

    if (!empty($searchParams) || !$this->_trxnId) {
      $transactions = CRM_Civiledger_BAO_AccountCorrection::searchTransactions($searchParams);
      $this->assign('transactions', $transactions);
    }
  }

  public function postProcess() {
    if (!$this->_trxnId) {
      // Search submit — redirect with search params
      $values = $this->exportValues();
      $url = CRM_Utils_System::url('civicrm/civiledger/account-correction', http_build_query([
        'reset' => 1,
        'search_contribution_id' => $values['search_contribution_id'] ?? '',
        'search_date_from' => $values['search_date_from'] ?? '',
        'search_date_to' => $values['search_date_to'] ?? '',
      ]));
      CRM_Utils_System::redirect($url);
      return;
    }

    // Apply correction
    $values = $this->exportValues();
    $result = CRM_Civiledger_BAO_AccountCorrection::correctAccounts(
      (int) $this->_trxnId,
      (int) ($values['new_from_account_id'] ?? 0),
      (int) ($values['new_to_account_id'] ?? 0),
      $values['reason'] ?? ''
    );

    $type = $result['success'] ? 'success' : 'error';
    CRM_Core_Session::setStatus($result['message'], ts('Account Correction'), $type);
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/civiledger/account-correction', 'reset=1'));
  }
}
