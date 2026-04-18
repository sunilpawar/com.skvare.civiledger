<?php
/**
 * CiviLedger - Feature 4: Account Balance Dashboard Page
 *
 * Displays live credit/debit/balance for every financial account,
 * and drills into per-account movement detail.
 */
class CRM_Civiledger_Page_AccountBalance extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(ts('CiviLedger — Account Balance Dashboard'));

    // Add CSS/JS
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');

    $dateFrom = CRM_Utils_Request::retrieve('date_from', 'String') ?: date('Y-m-01');
    $dateTo = CRM_Utils_Request::retrieve('date_to', 'String') ?: date('Y-m-d');

    // Summary balances for all accounts
    $balances = CRM_Civiledger_BAO_AccountBalance::getBalances($dateFrom, $dateTo);
    $stats = CRM_Civiledger_BAO_AccountBalance::getSummaryStats($dateFrom, $dateTo);

    // Group balances by account type for the template
    $grouped = [];
    foreach ($balances as $row) {
      $type = $row['account_type'] ?: 'Other';
      $grouped[$type][] = $row;
    }

    $this->assign('dateFrom', $dateFrom);
    $this->assign('dateTo', $dateTo);
    $this->assign('balances', $balances);
    $this->assign('grouped', $grouped);
    $this->assign('stats', $stats);
    $this->assign('cms_type', CIVICRM_UF);

    parent::run();
  }

}
