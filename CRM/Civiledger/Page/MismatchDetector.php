<?php
/**
 * Page: Amount Mismatch Detector
 */
class CRM_Civiledger_Page_MismatchDetector extends CRM_Core_Page {

  public function run() {
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');
    CRM_Utils_System::setTitle(ts('CiviLedger — Amount Mismatch Detector'));

    $filters = [
      'date_from' => CRM_Utils_Request::retrieve('date_from', 'String') ?? '',
      'date_to'   => CRM_Utils_Request::retrieve('date_to', 'String') ?? '',
    ];

    $mismatches = CRM_Civiledger_BAO_MismatchDetector::detect($filters);
    $summary    = CRM_Civiledger_BAO_MismatchDetector::getSummary($filters);

    $this->assign('mismatches', $mismatches);
    $this->assign('summary', $summary);
    $this->assign('filters', $filters);
    $this->assign('auditUrl', CRM_Utils_System::url('civicrm/civiledger/audit-trail'));

    parent::run();
  }

}
