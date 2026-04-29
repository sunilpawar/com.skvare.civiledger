<?php
/**
 * CiviLedger — Duplicate Payment Detector Page
 * URL: /civicrm/civiledger/duplicate-payments
 */
class CRM_Civiledger_Page_DuplicatePaymentDetector extends CRM_Core_Page {

  public function run(): void {
    CRM_Utils_System::setTitle(ts('CiviLedger — Duplicate Payment Detector'));
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');

    $dateFrom = CRM_Utils_Request::retrieve('date_from', 'String')
      ?: date('Y-m-d', strtotime('-90 days'));
    $dateTo   = CRM_Utils_Request::retrieve('date_to', 'String')
      ?: date('Y-m-d');
    $window   = (int) (CRM_Utils_Request::retrieve('window', 'Integer')
      ?: (Civi::settings()->get('civiledger_dup_payment_window') ?? 10));

    $sets               = CRM_Civiledger_BAO_DuplicatePaymentDetector::findDuplicates($window, $dateFrom, $dateTo);
    $totalSets          = count($sets);
    $totalContributions = array_sum(array_map(fn($s) => count($s['contributions']), $sets));

    $this->assign('sets',               $sets);
    $this->assign('totalSets',          $totalSets);
    $this->assign('totalContributions', $totalContributions);
    $this->assign('dateFrom',           $dateFrom);
    $this->assign('dateTo',             $dateTo);
    $this->assign('window',             $window);
    $this->assign('ajaxUrl',            CRM_Utils_System::url('civicrm/civiledger/ajax'));
    $this->assign('settingsUrl',        CRM_Utils_System::url('civicrm/admin/civiledger/settings'));
    $this->assign('cms_type',           CIVICRM_UF);

    parent::run();
  }

}
