<?php
/**
 * CiviLedger - Refund / Reversal Integrity Checker Page
 *
 * @package com.skvare.civiledger
 */
class CRM_Civiledger_Page_RefundIntegrityChecker extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(ts('Refund / Reversal Integrity Check'));

    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');

    $filters = [
      'date_from'         => CRM_Utils_Request::retrieve('date_from', 'String') ?? date('Y-m-d', strtotime('-30 days')),
      'date_to'           => CRM_Utils_Request::retrieve('date_to', 'String') ?? date('Y-m-d'),
      'financial_type_id' => CRM_Utils_Request::retrieve('financial_type_id', 'Integer') ?: '',
    ];

    $results = CRM_Civiledger_BAO_RefundIntegrityChecker::runCheck($filters);

    // Pre-compute abs gap for template (Smarty arithmetic is unreliable)
    foreach ($results['amount_mismatch'] as &$row) {
      $row['abs_gap_amount'] = abs((float) $row['gap_amount']);
    }
    unset($row);

    $summary = [
      'no_reversal'       => count($results['no_reversal']),
      'amount_mismatch'   => count($results['amount_mismatch']),
      'orphaned_reversal' => count($results['orphaned_reversal']),
    ];
    $summary['total'] = array_sum($summary);

    // Dollar totals per category for the summary banner
    $totals = [
      'no_reversal'       => array_sum(array_column($results['no_reversal'],       'total_amount')),
      'amount_mismatch'   => array_sum(array_column($results['amount_mismatch'],   'gap_amount')),
      'orphaned_reversal' => array_sum(array_column($results['orphaned_reversal'], 'total_reversed')),
    ];

    $financialTypes = CRM_Contribute_BAO_Contribution::buildOptions('financial_type_id');

    $this->assign('filters',       $filters);
    $this->assign('results',       $results);
    $this->assign('summary',       $summary);
    $this->assign('totals',        $totals);
    $this->assign('financialTypes', $financialTypes);
    $this->assign('cms_type',      CIVICRM_UF);

    parent::run();
  }

}
