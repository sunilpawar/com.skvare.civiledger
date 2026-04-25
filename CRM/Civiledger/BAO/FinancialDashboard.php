<?php
/**
 * CiviLedger — Financial Dashboard BAO
 *
 * Provides pre-aggregated data sets for Chart.js visualizations.
 * All heavy SQL lives here; Page class just serialises to JSON for the template.
 */
class CRM_Civiledger_BAO_FinancialDashboard {

  /**
   * Credits and debits grouped by account type — drives the grouped bar chart.
   *
   * @return array  keyed by account-type label, each with credits/debits/balance
   */
  public static function getAccountTypeChart($dateFrom = NULL, $dateTo = NULL): array {
    $balances = CRM_Civiledger_BAO_AccountBalance::getBalances($dateFrom, $dateTo);
    $byType   = [];
    foreach ($balances as $row) {
      $type = $row['account_type'] ?: 'Other';
      if (!isset($byType[$type])) {
        $byType[$type] = ['credits' => 0.0, 'debits' => 0.0, 'balance' => 0.0];
      }
      $byType[$type]['credits'] += (float) $row['total_credits'];
      $byType[$type]['debits']  += (float) $row['total_debits'];
      $byType[$type]['balance'] += (float) $row['balance'];
    }
    return $byType;
  }

  /**
   * Monthly payment totals for the last N months — drives the line chart.
   *
   * @return array  [{label, payments, refunds, net}, …]
   */
  public static function getMonthlyTrend(int $months = 12): array {
    $rows = [];
    for ($i = $months - 1; $i >= 0; $i--) {
      $start = date('Y-m-01', strtotime("-{$i} months"));
      $end   = date('Y-m-t',  strtotime("-{$i} months"));
      $label = date('M Y',    strtotime($start));

      $payments = (float) CRM_Core_DAO::singleValueQuery(
        "SELECT COALESCE(SUM(total_amount), 0)
         FROM civicrm_financial_trxn
         WHERE is_payment = 1 AND total_amount > 0
           AND trxn_date BETWEEN %1 AND %2",
        [1 => [$start . ' 00:00:00', 'String'], 2 => [$end . ' 23:59:59', 'String']]
      );
      $refunds = (float) CRM_Core_DAO::singleValueQuery(
        "SELECT COALESCE(SUM(ABS(total_amount)), 0)
         FROM civicrm_financial_trxn
         WHERE is_payment = 1 AND total_amount < 0
           AND trxn_date BETWEEN %1 AND %2",
        [1 => [$start . ' 00:00:00', 'String'], 2 => [$end . ' 23:59:59', 'String']]
      );

      $rows[] = [
        'label'    => $label,
        'payments' => $payments,
        'refunds'  => $refunds,
        'net'      => $payments - $refunds,
      ];
    }
    return $rows;
  }

  /**
   * Asset-account breakdown: Cash-on-Hand vs Accounts Receivable — drives the doughnut chart.
   *
   * Heuristic: any Asset account whose name contains 'receivable' / 'ar ' / ' ar'
   * is treated as AR; remaining Asset accounts are Cash/Bank.
   *
   * @return array {cash, ar, revenue, expenses, liability}
   */
  public static function getCashAndAR($dateFrom = NULL, $dateTo = NULL): array {
    $balances = CRM_Civiledger_BAO_AccountBalance::getBalances($dateFrom, $dateTo);
    $totals   = ['cash' => 0.0, 'ar' => 0.0, 'revenue' => 0.0, 'expenses' => 0.0, 'liability' => 0.0];

    foreach ($balances as $row) {
      $type = $row['account_type'] ?? '';
      $bal  = (float) $row['balance'];
      $name = strtolower($row['name']);

      if ($type === 'Asset') {
        if (preg_match('/receivable|\bar\b/', $name)) {
          $totals['ar'] += $bal;
        }
        else {
          $totals['cash'] += $bal;
        }
      }
      elseif ($type === 'Revenue') {
        $totals['revenue'] += $bal;
      }
      elseif ($type === 'Expenses' || $type === 'Cost of Sales') {
        $totals['expenses'] += $bal;
      }
      elseif ($type === 'Liability') {
        $totals['liability'] += $bal;
      }
    }
    return $totals;
  }

  /**
   * Key performance numbers shown in the summary stat cards.
   */
  public static function getKPIs($dateFrom = NULL, $dateTo = NULL): array {
    $dateCondition = '';
    $params = [];
    $i = 1;
    if ($dateFrom) {
      $dateCondition .= " AND ft.trxn_date >= %{$i}";
      $params[$i++] = [$dateFrom . ' 00:00:00', 'String'];
    }
    if ($dateTo) {
      $dateCondition .= " AND ft.trxn_date <= %{$i}";
      $params[$i++] = [$dateTo . ' 23:59:59', 'String'];
    }

    $sql = "
      SELECT
        COUNT(DISTINCT ft.id)                                                  AS total_trxns,
        COALESCE(SUM(CASE WHEN ft.is_payment = 1 AND ft.total_amount > 0
                          THEN ft.total_amount END), 0)                        AS total_income,
        COALESCE(SUM(CASE WHEN ft.is_payment = 1 AND ft.total_amount < 0
                          THEN ABS(ft.total_amount) END), 0)                   AS total_refunds,
        COUNT(DISTINCT CASE WHEN ft.total_amount < 0 THEN ft.id END)           AS refund_count
      FROM civicrm_financial_trxn ft
      WHERE 1=1 {$dateCondition}
    ";
    $rows = CRM_Core_DAO::executeQuery($sql, $params)->fetchAll();
    return $rows[0] ?? [];
  }

}
