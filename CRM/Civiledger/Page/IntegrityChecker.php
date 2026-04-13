<?php
/**
 * Page: Financial Integrity Checker
 */
class CRM_Civiledger_Page_IntegrityChecker extends CRM_Core_Page {

  public function run() {
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');
    CRM_Utils_System::setTitle(ts('CiviLedger — Financial Integrity Checker'));

    $filters = $this->getFilters();
    $results = CRM_Civiledger_BAO_IntegrityChecker::runAll($filters);

    $this->assign('filters', $filters);
    $this->assign('results', $results);
    $this->assign('repairUrl', CRM_Utils_System::url('civicrm/civiledger/chain-repair'));
    $this->assign('auditUrl', CRM_Utils_System::url('civicrm/civiledger/audit-trail'));
    $this->assign('totalIssues', array_sum($results['summary']));
    $this->assign('statusOptions', $this->getStatusOptions());

    parent::run();
  }

  private function getFilters(): array {
    return [
      'date_from'              => CRM_Utils_Request::retrieve('date_from', 'String') ?? '',
      'date_to'                => CRM_Utils_Request::retrieve('date_to', 'String') ?? '',
      'contribution_status_id' => CRM_Utils_Request::retrieve('status_id', 'Integer') ?? '',
    ];
  }

  private function getStatusOptions(): array {
    return CRM_Contribute_BAO_Contribution::buildOptions('contribution_status_id');
  }

}
