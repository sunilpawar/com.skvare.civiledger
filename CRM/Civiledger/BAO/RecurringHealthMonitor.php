<?php
/**
 * CiviLedger - Recurring Contribution Health Monitor
 *
 * Scans civicrm_contribution_recur series for five categories of problems:
 *   1. Overdue Series          — no payment received within expected interval
 *   2. Stuck "In Progress"     — all installments paid but status not Completed
 *   3. Unresolved Failures     — failed payment with no subsequent success
 *   4. Orphaned Recurring      — active series with zero linked contributions
 *   5. Amount Drift            — installment amount differs from recur template
 *
 * @package com.skvare.civiledger
 */
class CRM_Civiledger_BAO_RecurringHealthMonitor {

  /**
   * Run all five checks and return keyed results.
   */
  public static function runCheck(array $filters = []): array {
    return [
      'overdue_series' => self::getOverdueSeries($filters),
      'stuck_in_progress' => self::getStuckInProgress($filters),
      'unresolved_failures' => self::getUnresolvedFailures($filters),
      'orphaned' => self::getOrphanedRecurring($filters),
      'amount_drift' => self::getAmountDrift($filters),
    ];
  }

  // -----------------------------------------------------------------------
  // Issue 1: Overdue Series
  // -----------------------------------------------------------------------

  public static function getOverdueSeries(array $filters = []): array {
    [$where, $params] = self::buildWhere('cr', $filters);
    $sql = "
      SELECT
        cr.id                  AS recur_id,
        cr.amount,
        cr.currency,
        cr.frequency_interval,
        cr.frequency_unit,
        cr.start_date,
        cr.installments,
        cr.contribution_status_id,
        ANY_VALUE(cs.label)    AS status_label,
        ct.id                  AS contact_id,
        ct.display_name        AS contact_name,
        ANY_VALUE(pp.name)     AS processor_name,
        ANY_VALUE(pi.label)    AS payment_instrument,
        MAX(c.receive_date)    AS last_payment_date,
        DATEDIFF(NOW(), MAX(c.receive_date)) AS days_since_last,
        CASE cr.frequency_unit
          WHEN 'day'   THEN DATE_ADD(MAX(c.receive_date), INTERVAL cr.frequency_interval DAY)
          WHEN 'week'  THEN DATE_ADD(MAX(c.receive_date), INTERVAL cr.frequency_interval WEEK)
          WHEN 'month' THEN DATE_ADD(MAX(c.receive_date), INTERVAL cr.frequency_interval MONTH)
          WHEN 'year'  THEN DATE_ADD(MAX(c.receive_date), INTERVAL cr.frequency_interval YEAR)
        END AS expected_next_date,
        DATEDIFF(NOW(),
          CASE cr.frequency_unit
            WHEN 'day'   THEN DATE_ADD(MAX(c.receive_date), INTERVAL cr.frequency_interval DAY)
            WHEN 'week'  THEN DATE_ADD(MAX(c.receive_date), INTERVAL cr.frequency_interval WEEK)
            WHEN 'month' THEN DATE_ADD(MAX(c.receive_date), INTERVAL cr.frequency_interval MONTH)
            WHEN 'year'  THEN DATE_ADD(MAX(c.receive_date), INTERVAL cr.frequency_interval YEAR)
          END
        ) AS days_overdue
      FROM civicrm_contribution_recur cr
      INNER JOIN civicrm_contact ct ON ct.id = cr.contact_id
      LEFT  JOIN civicrm_payment_processor pp ON pp.id = cr.payment_processor_id
      LEFT  JOIN civicrm_option_value pi
               ON pi.value = cr.payment_instrument_id
              AND pi.option_group_id = (
                    SELECT id FROM civicrm_option_group WHERE name = 'payment_instrument'
                  )
      LEFT  JOIN civicrm_option_value cs
               ON cs.value = cr.contribution_status_id
              AND cs.option_group_id = (
                    SELECT id FROM civicrm_option_group WHERE name = 'contribution_recur_status'
                  )
      INNER JOIN civicrm_contribution c
               ON c.contribution_recur_id = cr.id
              AND c.contribution_status_id = 1
      WHERE cr.contribution_status_id IN (2, 5)
        AND c.is_test <> 1
        AND cr.cancel_date IS NULL
        AND (cr.end_date IS NULL OR cr.end_date > NOW())
        {$where}
      GROUP BY cr.id, cr.amount, cr.currency, cr.frequency_interval, cr.frequency_unit,
               cr.start_date, cr.installments, cr.contribution_status_id,
               ct.id, ct.display_name
      HAVING CASE cr.frequency_unit
               WHEN 'day'   THEN DATE_ADD(MAX(c.receive_date), INTERVAL cr.frequency_interval DAY)
               WHEN 'week'  THEN DATE_ADD(MAX(c.receive_date), INTERVAL cr.frequency_interval WEEK)
               WHEN 'month' THEN DATE_ADD(MAX(c.receive_date), INTERVAL cr.frequency_interval MONTH)
               WHEN 'year'  THEN DATE_ADD(MAX(c.receive_date), INTERVAL cr.frequency_interval YEAR)
             END < NOW()
      ORDER BY days_overdue DESC
      LIMIT 500
    ";
    return self::addRecurUrl(CRM_Core_DAO::executeQuery($sql, $params)->fetchAll());
  }

  // -----------------------------------------------------------------------
  // Issue 2: Stuck "In Progress"
  // -----------------------------------------------------------------------

  public static function getStuckInProgress(array $filters = []): array {
    [$where, $params] = self::buildWhere('cr', $filters);
    $sql = "
      SELECT
        cr.id               AS recur_id,
        cr.amount,
        cr.currency,
        cr.installments,
        cr.start_date,
        cr.contribution_status_id,
        ANY_VALUE(cs.label) AS status_label,
        ct.id               AS contact_id,
        ct.display_name     AS contact_name,
        ANY_VALUE(pp.name)  AS processor_name,
        COUNT(c.id)         AS completed_count
      FROM civicrm_contribution_recur cr
      INNER JOIN civicrm_contact ct ON ct.id = cr.contact_id
      LEFT  JOIN civicrm_payment_processor pp ON pp.id = cr.payment_processor_id
      LEFT  JOIN civicrm_option_value cs
               ON cs.value = cr.contribution_status_id
              AND cs.option_group_id = (
                    SELECT id FROM civicrm_option_group WHERE name = 'contribution_recur_status'
                  )
      LEFT  JOIN civicrm_contribution c
               ON c.contribution_recur_id = cr.id
              AND c.contribution_status_id = 1
      WHERE cr.installments > 0
        AND cr.contribution_status_id = 5
        {$where}
      GROUP BY cr.id, cr.amount, cr.currency, cr.installments, cr.start_date,
               ct.id, ct.display_name
      HAVING completed_count >= cr.installments
      ORDER BY cr.start_date DESC
      LIMIT 500
    ";
    return self::addRecurUrl(CRM_Core_DAO::executeQuery($sql, $params)->fetchAll());
  }

  // -----------------------------------------------------------------------
  // Issue 3: Unresolved Failures
  // -----------------------------------------------------------------------

  public static function getUnresolvedFailures(array $filters = []): array {
    [$where, $params] = self::buildWhere('cr', $filters);
    $sql = "
      SELECT
        cr.id               AS recur_id,
        cr.amount,
        cr.currency,
        cr.contribution_status_id,
        ANY_VALUE(cs.label) AS status_label,
        ct.id               AS contact_id,
        ct.display_name     AS contact_name,
        ANY_VALUE(pp.name)  AS processor_name,
        SUM(CASE WHEN c.contribution_status_id = 4 THEN 1 ELSE 0 END) AS failure_count,
        MAX(CASE WHEN c.contribution_status_id = 4 THEN c.receive_date ELSE NULL END) AS last_failure_date,
        MAX(CASE WHEN c.contribution_status_id = 1 THEN c.receive_date ELSE NULL END) AS last_success_date
      FROM civicrm_contribution_recur cr
      INNER JOIN civicrm_contact ct ON ct.id = cr.contact_id
      LEFT  JOIN civicrm_payment_processor pp ON pp.id = cr.payment_processor_id
      LEFT  JOIN civicrm_option_value cs
               ON cs.value = cr.contribution_status_id
              AND cs.option_group_id = (
                    SELECT id FROM civicrm_option_group WHERE name = 'contribution_recur_status'
                  )
      INNER JOIN civicrm_contribution c ON c.contribution_recur_id = cr.id
      WHERE cr.contribution_status_id NOT IN (3)
        {$where}
      GROUP BY cr.id, cr.amount, cr.currency, cr.contribution_status_id,
               ct.id, ct.display_name
      HAVING last_failure_date IS NOT NULL
         AND (last_success_date IS NULL OR last_failure_date > last_success_date)
      ORDER BY last_failure_date DESC
      LIMIT 500
    ";
    return self::addRecurUrl(CRM_Core_DAO::executeQuery($sql, $params)->fetchAll());
  }

  // -----------------------------------------------------------------------
  // Issue 4: Orphaned Recurring (no contributions at all)
  // -----------------------------------------------------------------------

  public static function getOrphanedRecurring(array $filters = []): array {
    [$where, $params] = self::buildWhere('cr', $filters);
    $sql = "
      SELECT
        cr.id               AS recur_id,
        cr.amount,
        cr.currency,
        cr.frequency_interval,
        cr.frequency_unit,
        cr.start_date,
        cr.contribution_status_id,
        cs.label            AS status_label,
        ct.id               AS contact_id,
        ct.display_name     AS contact_name,
        pp.name             AS processor_name
      FROM civicrm_contribution_recur cr
      INNER JOIN civicrm_contact ct ON ct.id = cr.contact_id
      LEFT  JOIN civicrm_payment_processor pp ON pp.id = cr.payment_processor_id
      LEFT  JOIN civicrm_option_value cs
               ON cs.value = cr.contribution_status_id
              AND cs.option_group_id = (
                    SELECT id FROM civicrm_option_group WHERE name = 'contribution_recur_status'
                  )
      LEFT  JOIN civicrm_contribution c ON c.contribution_recur_id = cr.id
      WHERE c.id IS NULL
        AND cr.contribution_status_id NOT IN (3, 4)
        {$where}
      ORDER BY cr.start_date DESC
      LIMIT 500
    ";
    return self::addRecurUrl(CRM_Core_DAO::executeQuery($sql, $params)->fetchAll());
  }

  // -----------------------------------------------------------------------
  // Issue 5: Amount Drift
  // -----------------------------------------------------------------------

  public static function getAmountDrift(array $filters = []): array {
    [$where, $params] = self::buildWhere('cr', $filters);
    $sql = "
      SELECT
        cr.id                                AS recur_id,
        cr.amount                            AS scheduled_amount,
        cr.currency,
        cr.frequency_interval,
        cr.frequency_unit,
        cr.contribution_status_id,
        cs.label                             AS status_label,
        ct.id                                AS contact_id,
        ct.display_name                      AS contact_name,
        c.id                                 AS contribution_id,
        c.total_amount                       AS actual_amount,
        c.receive_date,
        ABS(c.total_amount - cr.amount)      AS drift_amount
      FROM civicrm_contribution c
      INNER JOIN civicrm_contribution_recur cr ON cr.id = c.contribution_recur_id
      INNER JOIN civicrm_contact ct ON ct.id = c.contact_id
      LEFT  JOIN civicrm_option_value cs
               ON cs.value = cr.contribution_status_id
              AND cs.option_group_id = (
                    SELECT id FROM civicrm_option_group WHERE name = 'contribution_status'
                  )
      WHERE c.contribution_status_id = 1
        AND ABS(c.total_amount - cr.amount) > 0.01
        {$where}
      ORDER BY drift_amount DESC, c.receive_date DESC
      LIMIT 500
    ";
    $rows = CRM_Core_DAO::executeQuery($sql, $params)->fetchAll();
    foreach ($rows as &$row) {
      $row['recur_url'] = self::buildRecurUrl((int) $row['recur_id'], (int) $row['contact_id']);
      $row['contribution_url'] = CRM_Civiledger_BAO_Utils::getContributionUrl((int) $row['contribution_id']);
    }
    return $rows;
  }

  // -----------------------------------------------------------------------
  // Chart data methods
  // -----------------------------------------------------------------------

  /**
   * Bucket overdue series by how many days overdue they are.
   * Accepts the already-fetched overdue rows to avoid re-querying.
   */
  public static function getOverdueBuckets(array $overdueRows): array {
    $buckets = ['1–7d' => 0, '8–30d' => 0, '31–90d' => 0, '90d+' => 0];
    foreach ($overdueRows as $r) {
      $d = (int) $r['days_overdue'];
      if ($d <= 7) {
        $buckets['1–7d']++;
      }
      elseif ($d <= 30) {
        $buckets['8–30d']++;
      }
      elseif ($d <= 90) {
        $buckets['31–90d']++;
      }
      else {
        $buckets['90d+']++;
      }
    }
    return $buckets;
  }

  /**
   * Monthly expected MRR vs. actual collected from recurring series — last N months.
   * Expected = SUM(cr.amount) for all active recurring series in that month.
   * Actual   = SUM(contribution.total_amount) for completed recurring contributions.
   */
  public static function getMonthlyExpectedVsActual(int $months = 12): array {
    $dateFrom = date('Y-m-01', strtotime("-{$months} months"));

    // Actual in one query
    $actualRows = CRM_Core_DAO::executeQuery("
      SELECT DATE_FORMAT(c.receive_date, '%Y-%m') AS month,
             SUM(c.total_amount)                  AS actual_amount
      FROM   civicrm_contribution c
      WHERE  c.contribution_recur_id IS NOT NULL
        AND  c.contribution_status_id = 1
        AND  c.receive_date >= %1
      GROUP  BY DATE_FORMAT(c.receive_date, '%Y-%m')
    ", [1 => [$dateFrom . ' 00:00:00', 'String']])->fetchAll();

    $actualMap = [];
    foreach ($actualRows as $r) {
      $actualMap[$r['month']] = (float) $r['actual_amount'];
    }

    $labels = $expected = $actual = [];
    for ($i = $months - 1; $i >= 0; $i--) {
      $monthStart = date('Y-m-01', strtotime("-{$i} months"));
      $monthEnd = date('Y-m-t', strtotime("-{$i} months"));
      $label = date('Y-m', strtotime("-{$i} months"));

      $exp = (float) CRM_Core_DAO::singleValueQuery("
        SELECT COALESCE(SUM(cr.amount), 0)
        FROM   civicrm_contribution_recur cr
        WHERE  cr.start_date <= %1
          AND  (cr.end_date    IS NULL OR cr.end_date    >= %2)
          AND  (cr.cancel_date IS NULL OR cr.cancel_date >= %2)
          AND  cr.contribution_status_id NOT IN (3, 4)
      ", [1 => [$monthEnd, 'String'], 2 => [$monthStart, 'String']]);

      $labels[] = $label;
      $expected[] = round($exp, 2);
      $actual[] = $actualMap[$label] ?? 0.0;
    }

    return compact('labels', 'expected', 'actual');
  }

  /**
   * Failure rate per payment processor over the last 12 months.
   * Only returns processors with >= 5 total recurring contributions.
   */
  public static function getFailureRateByProcessor(array $filters = []): array {
    $dateFrom = date('Y-m-01', strtotime('-12 months'));
    $rows = CRM_Core_DAO::executeQuery("
      SELECT
        COALESCE(pp.name, 'Unknown / Direct') AS processor_name,
        COUNT(c.id) AS total_count,
        SUM(CASE WHEN c.contribution_status_id = 4 THEN 1 ELSE 0 END) AS failed_count,
        ROUND(
          SUM(CASE WHEN c.contribution_status_id = 4 THEN 1 ELSE 0 END) * 100.0 / COUNT(c.id),
          1
        ) AS failure_rate
      FROM   civicrm_contribution c
      INNER  JOIN civicrm_contribution_recur cr ON cr.id = c.contribution_recur_id
      LEFT   JOIN civicrm_payment_processor pp  ON pp.id = cr.payment_processor_id
      WHERE  c.receive_date >= %1
      GROUP  BY COALESCE(pp.name, 'Unknown / Direct')
      HAVING total_count >= 5
      ORDER  BY failure_rate DESC
    ", [1 => [$dateFrom . ' 00:00:00', 'String']])->fetchAll();
    return $rows;
  }

  /**
   * Monthly success/failed counts per payment processor — last N months.
   * Returns labels + Chart.js-ready datasets (solid line = success, dashed = failed).
   */
  public static function getMonthlyByProcessor(int $months = 12): array {
    $dateFrom = date('Y-m-01', strtotime("-{$months} months"));

    $rows = CRM_Core_DAO::executeQuery("
      SELECT
        DATE_FORMAT(c.receive_date, '%Y-%m')                               AS month,
        COALESCE(pp.name, 'Unknown / Direct')                              AS processor_name,
        SUM(CASE WHEN (c.contribution_status_id = 1 OR c.contribution_status_id = 5 ) THEN 1 ELSE 0 END)     AS success_count,
        SUM(CASE WHEN c.contribution_status_id = 4 THEN 1 ELSE 0 END)     AS failed_count
      FROM  civicrm_contribution c
      INNER JOIN civicrm_contribution_recur cr ON cr.id = c.contribution_recur_id
      LEFT  JOIN civicrm_payment_processor pp  ON pp.id = cr.payment_processor_id
      WHERE c.is_test <> 1 AND c.receive_date >= %1
      GROUP BY DATE_FORMAT(c.receive_date, '%Y-%m'), COALESCE(pp.name, 'Unknown / Direct')
      ORDER BY month ASC, processor_name ASC
    ", [1 => [$dateFrom . ' 00:00:00', 'String']])->fetchAll();

    // Build ordered month label list
    $labels = [];
    for ($i = $months - 1; $i >= 0; $i--) {
      $labels[] = date('Y-m', strtotime("-{$i} months"));
    }

    // Index rows by processor → month
    $byProcessor = [];
    foreach ($rows as $r) {
      $byProcessor[$r['processor_name']][$r['month']] = $r;
    }

    // One color pair per processor (solid success, dashed failed)
    $palette = [
      ['rgba(0,123,255', 'rgba(220,53,69'],
      ['rgba(40,167,69', 'rgba(255,193,7'],
      ['rgba(102,16,242', 'rgba(253,126,20'],
      ['rgba(32,201,151', 'rgba(108,117,125'],
      ['rgba(23,162,184', 'rgba(255,87,34'],
    ];

    $datasets = [];
    $ci = 0;
    foreach ($byProcessor as $procName => $monthData) {
      [$sc, $fc] = $palette[$ci % count($palette)];
      $successData = $failedData = [];
      foreach ($labels as $label) {
        $successData[] = isset($monthData[$label]) ? (int) $monthData[$label]['success_count'] : 0;
        $failedData[] = isset($monthData[$label]) ? (int) $monthData[$label]['failed_count'] : 0;
      }
      $datasets[] = [
        'label' => $procName . ' – Success',
        'data' => $successData,
        'borderColor' => $sc . ',1)',
        'backgroundColor' => $sc . ',0.05)',
        'borderWidth' => 2,
        'pointRadius' => 3,
        'tension' => 0.3,
        'fill' => FALSE,
      ];
      $datasets[] = [
        'label' => $procName . ' – Failed',
        'data' => $failedData,
        'borderColor' => $fc . ',1)',
        'backgroundColor' => $fc . ',0.05)',
        'borderWidth' => 2,
        'borderDash' => [5, 5],
        'pointRadius' => 3,
        'tension' => 0.3,
        'fill' => FALSE,
      ];
      $ci++;
    }

    return compact('labels', 'datasets');
  }

  // -----------------------------------------------------------------------
  // Helpers
  // -----------------------------------------------------------------------

  private static function buildWhere(string $alias, array $filters): array {
    $where = '';
    $params = [];
    $i = 1;
    if (!empty($filters['date_from'])) {
      $where .= " AND {$alias}.start_date >= %{$i}";
      $params[$i++] = [$filters['date_from'] . ' 00:00:00', 'String'];
    }
    if (!empty($filters['date_to'])) {
      $where .= " AND {$alias}.start_date <= %{$i}";
      $params[$i++] = [$filters['date_to'] . ' 23:59:59', 'String'];
    }
    if (!empty($filters['frequency_unit'])) {
      $where .= " AND {$alias}.frequency_unit = %{$i}";
      $params[$i++] = [$filters['frequency_unit'], 'String'];
    }
    if (!empty($filters['payment_instrument_id'])) {
      $where .= " AND {$alias}.payment_instrument_id = %{$i}";
      $params[$i++] = [(int) $filters['payment_instrument_id'], 'Integer'];
    }
    if (!empty($filters['status_id'])) {
      $where .= " AND {$alias}.contribution_status_id = %{$i}";
      $params[$i++] = [(int) $filters['status_id'], 'Integer'];
    }
    return [$where, $params];
  }

  private static function addRecurUrl(array $rows): array {
    foreach ($rows as &$row) {
      $row['recur_url'] = self::buildRecurUrl((int) $row['recur_id'], (int) $row['contact_id']);
    }
    return $rows;
  }

  private static function buildRecurUrl(int $recurId, int $contactId): string {
    return CRM_Utils_System::url(
      'civicrm/contact/view/contributionrecur',
      "reset=1&action=view&crid={$recurId}&cid={$contactId}&context=contribution"
    );
  }

}
