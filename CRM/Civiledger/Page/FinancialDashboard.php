<?php
/**
 * CiviLedger — Financial Dashboard (Charts)
 *
 * Renders three Chart.js visualisations:
 *   1. Monthly payment trend (line chart, last 12 months)
 *   2. Credits vs. Debits by account type (grouped bar chart)
 *   3. Cash on Hand vs. AR vs. Revenue vs. Expenses (doughnut)
 *
 * URL: /civicrm/civiledger/financial-dashboard
 */
class CRM_Civiledger_Page_FinancialDashboard extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(ts('CiviLedger — Financial Dashboard'));
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      // Chart.js 4 — loaded from jsDelivr (swap for a local file if offline operation is required)
      ->addScriptUrl('https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js');

    $dateFrom = CRM_Utils_Request::retrieve('date_from', 'String') ?: date('Y-01-01');
    $dateTo   = CRM_Utils_Request::retrieve('date_to',   'String') ?: date('Y-m-d');

    // --- Monthly trend (line chart) ---
    $trend  = CRM_Civiledger_BAO_FinancialDashboard::getMonthlyTrend(12);
    $trendLabels   = json_encode(array_column($trend, 'label'));
    $trendPayments = json_encode(array_column($trend, 'payments'));
    $trendRefunds  = json_encode(array_column($trend, 'refunds'));
    $trendNet      = json_encode(array_column($trend, 'net'));

    // --- Account type bar chart ---
    $typeData = CRM_Civiledger_BAO_FinancialDashboard::getAccountTypeChart($dateFrom, $dateTo);
    $typeLabels  = json_encode(array_keys($typeData));
    $typeCredits = json_encode(array_column($typeData, 'credits'));
    $typeDebits  = json_encode(array_column($typeData, 'debits'));

    // --- Cash / AR / Revenue / Expenses doughnut ---
    $cashAR = CRM_Civiledger_BAO_FinancialDashboard::getCashAndAR($dateFrom, $dateTo);
    $doughnutData   = json_encode(array_values($cashAR));
    $doughnutLabels = json_encode(['Cash / Bank', 'Accounts Receivable', 'Revenue', 'Expenses', 'Liability']);

    // --- KPI stat cards ---
    $kpis = CRM_Civiledger_BAO_FinancialDashboard::getKPIs($dateFrom, $dateTo);

    $this->assign('dateFrom',       $dateFrom);
    $this->assign('dateTo',         $dateTo);
    $this->assign('kpis',           $kpis);
    $this->assign('trendLabels',    $trendLabels);
    $this->assign('trendPayments',  $trendPayments);
    $this->assign('trendRefunds',   $trendRefunds);
    $this->assign('trendNet',       $trendNet);
    $this->assign('typeLabels',     $typeLabels);
    $this->assign('typeCredits',    $typeCredits);
    $this->assign('typeDebits',     $typeDebits);
    $this->assign('doughnutData',   $doughnutData);
    $this->assign('doughnutLabels', $doughnutLabels);
    $this->assign('cms_type',       CIVICRM_UF);

    parent::run();
  }

}
