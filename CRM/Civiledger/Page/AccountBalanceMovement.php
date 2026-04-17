<?php
use CRM_Civiledger_ExtensionUtil as E;

class CRM_Civiledger_Page_AccountBalanceMovement extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(ts('CiviLedger — Account Balance Dashboard'));

    // Add CSS/JS
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');
    $dateFrom = CRM_Utils_Request::retrieve('date_from', 'String') ?: date('Y-m-01');
    $dateTo = CRM_Utils_Request::retrieve('date_to', 'String') ?: date('Y-m-d');
    $accountId = (int) CRM_Utils_Request::retrieve('account_id', 'Integer');
    // Drill-down: movements for a specific account
    $movements = [];
    $accountName = '';
    if ($accountId) {
      $movements = CRM_Civiledger_BAO_AccountBalance::getAccountMovements($accountId, $dateFrom, $dateTo);
      $accountName = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialAccount', $accountId, 'name');
    }

    $this->assign('dateFrom', $dateFrom);
    $this->assign('dateTo', $dateTo);
    $this->assign('movements', $movements);
    $this->assign('accountId', $accountId);
    $this->assign('accountName', $accountName);

    parent::run();
  }

}
