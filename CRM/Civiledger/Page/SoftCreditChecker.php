<?php
/**
 * CiviLedger - Soft Credit Integrity Checker Page
 *
 * @package com.skvare.civiledger
 */
class CRM_Civiledger_Page_SoftCreditChecker extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(ts('Soft Credit Integrity Check'));

    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js')
      ->addScriptFile('com.skvare.civiledger', 'js/chart.umd.min.js');

    $filters = [
      'date_from' => CRM_Utils_Request::retrieve('date_from', 'String') ?: date('Y-m-d', strtotime('-90 days')),
      'date_to'   => CRM_Utils_Request::retrieve('date_to', 'String') ?: date('Y-m-d'),
      'status_id' => CRM_Utils_Request::retrieve('status_id', 'Integer') ?: '',
    ];

    $rows    = CRM_Civiledger_BAO_SoftCreditChecker::detect($filters);
    $summary = CRM_Civiledger_BAO_SoftCreditChecker::getSummary($filters);

    // Attach per-contribution soft credit detail
    foreach ($rows as &$row) {
      $row['soft_credits'] = CRM_Civiledger_BAO_SoftCreditChecker::getSoftCredits(
        (int) $row['contribution_id']
      );
      $row['contribution_url'] = CRM_Civiledger_BAO_Utils::getContributionUrl(
        (int) $row['contribution_id']
      );
    }
    unset($row);

    $chartData = CRM_Civiledger_BAO_SoftCreditChecker::getChartData($filters);

    $statusOptions = CRM_Contribute_BAO_Contribution::buildOptions('contribution_status_id');

    $this->assign('filters', $filters);
    $this->assign('rows', $rows);
    $this->assign('summary', $summary);
    $this->assign('statusOptions', $statusOptions);
    $this->assign('cms_type', CIVICRM_UF);
    $this->assign('chartGranularity', $chartData['granularity']);
    $this->assign('chartLabels', json_encode($chartData['labels']));
    $this->assign('chartHard', json_encode($chartData['hard']));
    $this->assign('chartSoft', json_encode($chartData['soft']));
    $this->assign('chartOver', json_encode($chartData['over']));

    parent::run();
  }

}
