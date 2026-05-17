<?php
/**
 * CiviLedger - Recurring Contribution Health Monitor Page
 *
 * @package com.skvare.civiledger
 */
class CRM_Civiledger_Page_RecurringHealthMonitor extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(ts('Recurring Contribution Health Monitor'));

    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js')
      ->addScriptFile('com.skvare.civiledger', 'js/chart.umd.min.js');

    $filters = [
      'date_from'             => CRM_Utils_Request::retrieve('date_from', 'String') ?: '',
      'date_to'               => CRM_Utils_Request::retrieve('date_to', 'String') ?: '',
      'frequency_unit'        => CRM_Utils_Request::retrieve('frequency_unit', 'String') ?: '',
      'payment_instrument_id' => CRM_Utils_Request::retrieve('payment_instrument_id', 'Integer') ?: '',
      'status_id'             => CRM_Utils_Request::retrieve('status_id', 'Integer') ?: '',
      'gap_month'             => CRM_Utils_Request::retrieve('gap_month', 'String') ?: date('Y-m', strtotime('-1 month')),
    ];

    $results = CRM_Civiledger_BAO_RecurringHealthMonitor::runCheck($filters);

    $summary = [
      'overdue_series' => count($results['overdue_series']),
      'stuck_in_progress' => count($results['stuck_in_progress']),
      'unresolved_failures' => count($results['unresolved_failures']),
      'orphaned' => count($results['orphaned']),
      'amount_drift' => count($results['amount_drift']),
    ];
    $summary['total'] = array_sum($summary);

    // Chart 1 — Issue Distribution Doughnut
    $doughnutLabels = ['Overdue Series', 'Stuck In Progress', 'Unresolved Failures', 'Orphaned', 'Amount Drift'];
    $doughnutData = array_values(array_diff_key($summary, ['total' => 0]));

    // Chart 2 — Expected vs Actual MRR (12 months)
    $monthly = CRM_Civiledger_BAO_RecurringHealthMonitor::getMonthlyExpectedVsActual(12);

    // Chart 3 — Days overdue buckets
    $buckets = CRM_Civiledger_BAO_RecurringHealthMonitor::getOverdueBuckets($results['overdue_series']);
    $bucketLabels = array_keys($buckets);
    $bucketData = array_values($buckets);

    // Chart 4 — Processor failure rate (bar summary)
    $processorRows = CRM_Civiledger_BAO_RecurringHealthMonitor::getFailureRateByProcessor($filters);
    $procLabels = $procRates = [];
    foreach ($processorRows as $p) {
      $procLabels[] = $p['processor_name'];
      $procRates[] = (float) $p['failure_rate'];
    }

    // Chart 5 — Monthly success/failed per processor (line)
    $procMonthly = CRM_Civiledger_BAO_RecurringHealthMonitor::getMonthlyByProcessor(12);

    // Gap analysis — active series with no payment in gap_month
    $gapRows        = CRM_Civiledger_BAO_RecurringHealthMonitor::getMissedExpectedSeries($filters['gap_month']);
    $gapMissedTotal = array_sum(array_column($gapRows, 'monthly_value'));

    $paymentInstruments = CRM_Contribute_BAO_Contribution::buildOptions('payment_instrument_id');
    $statusOptions = CRM_Contribute_BAO_Contribution::buildOptions('contribution_status_id');

    // Core data
    $this->assign('filters', $filters);
    $this->assign('results', $results);
    $this->assign('summary', $summary);
    $this->assign('paymentInstruments', $paymentInstruments);
    $this->assign('statusOptions', $statusOptions);
    $this->assign('cms_type', CIVICRM_UF);
    $this->assign('hasProcessorChart',     count($processorRows) >= 2);
    $this->assign('hasProcessorLineChart', !empty($procMonthly['datasets']));
    $this->assign('gapRows',        $gapRows);
    $this->assign('gapRowsCount',   count($gapRows));
    $this->assign('gapMissedTotal', $gapMissedTotal);

    // Chart data (JSON for template interpolation)
    $this->assign('chartDoughnutLabels', json_encode($doughnutLabels));
    $this->assign('chartDoughnutData', json_encode($doughnutData));
    $this->assign('chartMonthLabels', json_encode($monthly['labels']));
    $this->assign('chartExpected', json_encode($monthly['expected']));
    $this->assign('chartActual', json_encode($monthly['actual']));
    $this->assign('chartBucketLabels', json_encode($bucketLabels));
    $this->assign('chartBucketData', json_encode($bucketData));
    $this->assign('chartProcLabels', json_encode($procLabels));
    $this->assign('chartProcRates', json_encode($procRates));
    $this->assign('chartProcLineLabels', json_encode($procMonthly['labels']));
    $this->assign('chartProcLineDatasets', json_encode($procMonthly['datasets']));

    parent::run();
  }

}
