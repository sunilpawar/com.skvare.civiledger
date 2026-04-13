<?php
/**
 * CiviLedger Dashboard Page
 * Feature 4: Account Balance Dashboard + health summary of all features.
 */
class CRM_Civiledger_Page_Dashboard extends CRM_Core_Page {

  public function run() {
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');
    CRM_Utils_System::setTitle(ts('CiviLedger — Financial Dashboard'));

    $dateFrom = CRM_Utils_Request::retrieve('date_from', 'String') ?: date('Y-m-01');
    $dateTo = CRM_Utils_Request::retrieve('date_to', 'String') ?: date('Y-m-d');
    $accountId = (int) CRM_Utils_Request::retrieve('account_id', 'Positive');

    $balances = CRM_Civiledger_BAO_AccountBalance::getBalances($dateFrom, $dateTo);
    $stats = CRM_Civiledger_BAO_AccountBalance::getSummaryStats($dateFrom, $dateTo);
    $integritySummary = CRM_Civiledger_BAO_IntegrityChecker::getSummary();
    $mismatchCounts = CRM_Civiledger_BAO_MismatchDetector::getMismatchCounts();

    $totalIssues = array_sum($integritySummary) + array_sum($mismatchCounts);
    $healthScore = $totalIssues === 0 ? 'good' : ($totalIssues < 10 ? 'warning' : 'critical');

    $accountMovements = [];
    $selectedAccount = NULL;
    if ($accountId) {
      $accountMovements = CRM_Civiledger_BAO_AccountBalance::getAccountMovements($accountId, $dateFrom, $dateTo);
      foreach ($balances as $row) {
        if ((int) $row['id'] === $accountId) {
          $selectedAccount = $row;
          break;
        }
      }
    }

    $this->assign('dateFrom', $dateFrom);
    $this->assign('dateTo', $dateTo);
    $this->assign('accountId', $accountId);
    $this->assign('selectedAccount', $selectedAccount);
    $this->assign('accountMovements', $accountMovements);
    $this->assign('balances', $balances);
    $this->assign('stats', $stats);
    $this->assign('integritySummary', $integritySummary);
    $this->assign('mismatchCounts', $mismatchCounts);
    $this->assign('totalIssues', $totalIssues);
    $this->assign('healthScore', $healthScore);

    parent::run();
  }
}
