<?php
/**
 * CiviLedger - Donor Cohort Retention Analysis
 *
 * Groups donors by first-gift month (cohort) and tracks what percentage of
 * each cohort gave again in each subsequent month.  The resulting matrix is
 * suitable for rendering as a colour-coded heatmap.
 *
 * @package com.skvare.civiledger
 */
class CRM_Civiledger_BAO_CohortRetention {

  /**
   * Build the full retention heatmap matrix plus summary KPIs.
   *
   * @param array $filters  Keys: cohort_from (Y-m), cohort_to (Y-m),
   *                        max_months (int), financial_type_id (int|'')
   */
  public static function buildMatrix(array $filters): array {
    $cohortFrom = !empty($filters['cohort_from']) ? $filters['cohort_from'] : date('Y-m', strtotime('-18 months'));
    $cohortTo   = !empty($filters['cohort_to'])   ? $filters['cohort_to']   : date('Y-m', strtotime('-2 months'));
    $maxMonths  = min((int) ($filters['max_months'] ?? 12), 24);

    // Safe integer injection for optional financial type (not user-typed string)
    $ftInner = '';
    $ftOuter = '';
    if (!empty($filters['financial_type_id'])) {
      $ftid    = (int) $filters['financial_type_id'];
      $ftInner = " AND financial_type_id = {$ftid}";
      $ftOuter = " AND c.financial_type_id = {$ftid}";
    }

    // ── Step 1: cohort sizes ───────────────────────────────────────────────
    $cohortRows = CRM_Core_DAO::executeQuery("
      SELECT fg.cohort_month, COUNT(*) AS cohort_size
      FROM (
        SELECT contact_id, DATE_FORMAT(MIN(receive_date), '%Y-%m') AS cohort_month
        FROM   civicrm_contribution
        WHERE  contribution_status_id = 1
          AND  is_test = 0
          AND  contact_id IS NOT NULL
          {$ftInner}
        GROUP  BY contact_id
      ) fg
      WHERE fg.cohort_month >= %1
        AND fg.cohort_month <= %2
      GROUP  BY fg.cohort_month
      ORDER  BY fg.cohort_month ASC
    ", [
      1 => [$cohortFrom, 'String'],
      2 => [$cohortTo,   'String'],
    ])->fetchAll();

    $cohortSizeMap = [];
    foreach ($cohortRows as $r) {
      $cohortSizeMap[$r['cohort_month']] = (int) $r['cohort_size'];
    }

    if (empty($cohortSizeMap)) {
      return [
        'matrix'           => [],
        'max_months'       => $maxMonths,
        'avg_by_month'     => [],
        'total_donors'     => 0,
        'second_gift_rate' => null,
        'third_month_rate' => null,
        'year_rate'        => null,
        'best_cohort'      => '—',
        'best_cohort_rate' => null,
      ];
    }

    // ── Step 2: retention data per cohort × month-offset ──────────────────
    $retentionRows = CRM_Core_DAO::executeQuery("
      SELECT
        fg.cohort_month,
        TIMESTAMPDIFF(MONTH,
          STR_TO_DATE(CONCAT(fg.cohort_month, '-01'), '%Y-%m-%d'),
          STR_TO_DATE(CONCAT(DATE_FORMAT(c.receive_date, '%Y-%m'), '-01'), '%Y-%m-%d')
        ) AS months_since_first,
        COUNT(DISTINCT c.contact_id) AS retained_count
      FROM (
        SELECT contact_id, DATE_FORMAT(MIN(receive_date), '%Y-%m') AS cohort_month
        FROM   civicrm_contribution
        WHERE  contribution_status_id = 1
          AND  is_test = 0
          AND  contact_id IS NOT NULL
          {$ftInner}
        GROUP  BY contact_id
      ) fg
      INNER JOIN civicrm_contribution c ON c.contact_id = fg.contact_id
      WHERE  c.contribution_status_id = 1
        AND  c.is_test = 0
        {$ftOuter}
        AND  fg.cohort_month >= %1
        AND  fg.cohort_month <= %2
        AND  TIMESTAMPDIFF(MONTH,
               STR_TO_DATE(CONCAT(fg.cohort_month, '-01'), '%Y-%m-%d'),
               c.receive_date
             ) BETWEEN 0 AND %3
      GROUP  BY fg.cohort_month, months_since_first
      ORDER  BY fg.cohort_month ASC, months_since_first ASC
    ", [
      1 => [$cohortFrom,  'String'],
      2 => [$cohortTo,    'String'],
      3 => [$maxMonths,   'Integer'],
    ])->fetchAll();

    // Index: retentionMap[cohort_month][offset] = retained_count
    $retentionMap = [];
    foreach ($retentionRows as $r) {
      $retentionMap[$r['cohort_month']][(int) $r['months_since_first']] = (int) $r['retained_count'];
    }

    // ── Step 3: build matrix ───────────────────────────────────────────────
    $now        = strtotime(date('Y-m-01'));
    $matrix     = [];
    $sumByMonth = array_fill(0, $maxMonths + 1, 0.0);
    $cntByMonth = array_fill(0, $maxMonths + 1, 0);

    foreach ($cohortSizeMap as $cohortMonth => $cohortSize) {
      $cohortTs = strtotime($cohortMonth . '-01');
      $cells    = [];

      for ($m = 0; $m <= $maxMonths; $m++) {
        $targetTs = strtotime("+{$m} months", $cohortTs);
        $isFuture = $targetTs > $now;
        $retained = isset($retentionMap[$cohortMonth][$m]) ? $retentionMap[$cohortMonth][$m] : 0;
        $pct      = (!$isFuture && $cohortSize > 0)
                      ? round($retained / $cohortSize * 100, 1)
                      : null;

        if (!$isFuture && $pct !== null) {
          $sumByMonth[$m] += $pct;
          $cntByMonth[$m]++;
        }

        $cells[] = [
          'retained'    => $retained,
          'pct'         => $pct,
          'pct_display' => $isFuture ? '' : ($pct !== null ? $pct . '%' : '—'),
          'tooltip'     => $isFuture
                            ? 'Not yet reached'
                            : ($pct !== null
                                ? "{$retained} of {$cohortSize} donors ({$pct}%)"
                                : 'No data'),
          'is_future'   => $isFuture,
          'color_class' => self::pctToClass($pct, $isFuture, $m),
        ];
      }

      $matrix[] = [
        'cohort_month' => $cohortMonth,
        'cohort_size'  => $cohortSize,
        'cells'        => $cells,
      ];
    }

    // ── Step 4: average row ────────────────────────────────────────────────
    $avgByMonth = [];
    for ($m = 0; $m <= $maxMonths; $m++) {
      $avg = $cntByMonth[$m] > 0
               ? round($sumByMonth[$m] / $cntByMonth[$m], 1)
               : null;
      $avgByMonth[] = [
        'pct'         => $avg,
        'pct_display' => $avg !== null ? $avg . '%' : '—',
        'color_class' => self::pctToClass($avg, FALSE, $m),
      ];
    }

    // ── KPIs ──────────────────────────────────────────────────────────────
    $totalDonors = array_sum($cohortSizeMap);

    $bestCohort     = '—';
    $bestCohortRate = null;
    foreach ($matrix as $row) {
      $m1pct = isset($row['cells'][1]) ? $row['cells'][1]['pct'] : null;
      if ($m1pct !== null && ($bestCohortRate === null || $m1pct > $bestCohortRate)) {
        $bestCohortRate = $m1pct;
        $bestCohort     = $row['cohort_month'];
      }
    }

    return [
      'matrix'           => $matrix,
      'max_months'       => $maxMonths,
      'avg_by_month'     => $avgByMonth,
      'total_donors'     => $totalDonors,
      'second_gift_rate' => isset($avgByMonth[1])  ? $avgByMonth[1]['pct']  : null,
      'third_month_rate' => isset($avgByMonth[3])  ? $avgByMonth[3]['pct']  : null,
      'year_rate'        => isset($avgByMonth[12]) ? $avgByMonth[12]['pct'] : null,
      'best_cohort'      => $bestCohort,
      'best_cohort_rate' => $bestCohortRate,
    ];
  }

  // -----------------------------------------------------------------------
  // Helpers
  // -----------------------------------------------------------------------

  private static function pctToClass(?float $pct, bool $isFuture, int $mOffset): string {
    if ($isFuture)            return 'cohort-future';
    if ($mOffset === 0)       return 'cohort-m0';
    if ($pct === null || $pct <= 0) return 'cohort-zero';
    if ($pct <= 10)           return 'cohort-p10';
    if ($pct <= 20)           return 'cohort-p20';
    if ($pct <= 35)           return 'cohort-p35';
    if ($pct <= 50)           return 'cohort-p50';
    if ($pct <= 70)           return 'cohort-p70';
    return 'cohort-p100';
  }

}
