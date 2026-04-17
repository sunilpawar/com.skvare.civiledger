<?php
use CRM_Civiledger_ExtensionUtil as E;

class CRM_Civiledger_Page_AccountBalanceMovement extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(ts('CiviLedger — Account Balance Movement'));

    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');

    $dateFrom  = CRM_Utils_Request::retrieve('date_from', 'String') ?: date('Y-m-01');
    $dateTo    = CRM_Utils_Request::retrieve('date_to', 'String') ?: date('Y-m-d');
    $accountId = (int) CRM_Utils_Request::retrieve('account_id', 'Integer');

    $movements    = [];
    $accountName  = '';
    $accountStats = [];

    if ($accountId) {
      $movements    = CRM_Civiledger_BAO_AccountBalance::getAccountMovements($accountId, $dateFrom, $dateTo);
      $accountName  = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialAccount', $accountId, 'name');
      $accountStats = CRM_Civiledger_BAO_AccountBalance::getAccountSummaryStats($accountId, $dateFrom, $dateTo);
    }

    $accountOptions = CRM_Civiledger_BAO_AccountBalance::getAccountOptions();

    $this->assign('dateFrom', $dateFrom);
    $this->assign('dateTo', $dateTo);
    $this->assign('movements', $movements);
    $this->assign('accountId', $accountId);
    $this->assign('accountName', $accountName);
    $this->assign('accountStats', $accountStats);
    $this->assign('accountOptions', $accountOptions);

    parent::run();
  }

}
