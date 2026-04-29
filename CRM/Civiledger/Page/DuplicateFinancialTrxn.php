<?php
/**
 * CiviLedger — Duplicate Financial Transaction Detector Page
 * URL: /civicrm/civiledger/duplicate-trxn
 */
class CRM_Civiledger_Page_DuplicateFinancialTrxn extends CRM_Core_Page {

  public function run(): void {
    CRM_Utils_System::setTitle(ts('CiviLedger — Duplicate Financial Transaction Detector'));
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');

    $dateFrom = CRM_Utils_Request::retrieve('date_from', 'String')
      ?: date('Y-m-d', strtotime('-90 days'));
    $dateTo   = CRM_Utils_Request::retrieve('date_to', 'String')
      ?: date('Y-m-d');

    $sets        = CRM_Civiledger_BAO_DuplicateFinancialTrxn::findDuplicates($dateFrom, $dateTo);
    $totalSets   = count($sets);
    $totalTrxns  = array_sum(array_map(fn($s) => count($s['trxns']), $sets));

    $this->assign('sets',         $sets);
    $this->assign('totalSets',    $totalSets);
    $this->assign('totalTrxns',   $totalTrxns);
    $this->assign('dateFrom',     $dateFrom);
    $this->assign('dateTo',       $dateTo);
    $this->assign('ajaxUrl',      CRM_Utils_System::url('civicrm/civiledger/ajax'));
    $this->assign('settingsUrl',  CRM_Utils_System::url('civicrm/admin/civiledger/settings'));
    $this->assign('cms_type',     CIVICRM_UF);

    parent::run();
  }

}
