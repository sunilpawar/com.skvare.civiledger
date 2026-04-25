<?php
/**
 * CiviLedger — Tax Mapping Page
 *
 * Surfaces CiviCRM's non_deductible_amount data in three panels:
 *   1. Summary totals (deductible vs. non-deductible)
 *   2. Breakdown by financial type
 *   3. Data issues requiring attention
 *
 * URL: /civicrm/civiledger/tax-mapping
 */
class CRM_Civiledger_Page_TaxMapping extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(ts('CiviLedger — Tax Mapping'));
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js')
      ->addScriptUrl('https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js');

    $dateFrom = CRM_Utils_Request::retrieve('date_from', 'String') ?: date('Y-01-01');
    $dateTo   = CRM_Utils_Request::retrieve('date_to',   'String') ?: date('Y-m-d');

    $summary       = CRM_Civiledger_BAO_TaxMapping::getSummary($dateFrom, $dateTo);
    $byType        = CRM_Civiledger_BAO_TaxMapping::getByFinancialType($dateFrom, $dateTo);
    $issues        = CRM_Civiledger_BAO_TaxMapping::getIssues($dateFrom, $dateTo);
    $monthlyData   = CRM_Civiledger_BAO_TaxMapping::getMonthlyBreakdown(12);

    // Prepare chart data for the template (JSON for Chart.js)
    $chartLabels      = array_column($monthlyData, 'label');
    $chartDeductible  = array_column($monthlyData, 'deductible');
    $chartNonDeduct   = array_column($monthlyData, 'non_deductible');

    $issueLabels = [
      'non_deductible_exceeds_total' => ts('Non-deductible amount exceeds contribution total'),
      'li_sum_mismatch'              => ts('Line-item sum differs from contribution rollup (> $0.01)'),
      'non_deductible_type_not_set'  => ts('Financial type is non-deductible but non_deductible_amount is blank/zero'),
      'pfv_mismatch'                 => ts('Line-item non-deductible differs from price field value template'),
    ];

    $this->assign('dateFrom',        $dateFrom);
    $this->assign('dateTo',          $dateTo);
    $this->assign('summary',         $summary);
    $this->assign('byType',          $byType);
    $this->assign('issues',          $issues);
    $this->assign('issueLabels',     $issueLabels);
    $this->assign('chartLabels',     json_encode($chartLabels));
    $this->assign('chartDeductible', json_encode($chartDeductible));
    $this->assign('chartNonDeduct',  json_encode($chartNonDeduct));
    $this->assign('cms_type',        CIVICRM_UF);

    parent::run();
  }

}
