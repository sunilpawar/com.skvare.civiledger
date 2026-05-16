<?php
/**
 * CiviLedger - Soft Credit Integrity Check
 *
 * Verifies that the sum of civicrm_contribution_soft.amount for each
 * contribution does not exceed the contribution's total_amount.
 * Flags over-credited records.
 *
 * @package com.skvare.civiledger
 */
class CRM_Civiledger_BAO_SoftCreditChecker {

  /**
   * Detect contributions where soft credit total > hard contribution amount.
   *
   * @param array $filters  date_from, date_to, status_id
   * @return array
   */
  public static function detect(array $filters = []): array {
    $where  = ' c.is_test = 0 ';
    $params = [];
    $i = 1;
    if (!empty($filters['date_from'])) {
      $where .= " AND c.receive_date >= %{$i}";
      $params[$i++] = [$filters['date_from'] . ' 00:00:00', 'String'];
    }
    if (!empty($filters['date_to'])) {
      $where .= " AND c.receive_date <= %{$i}";
      $params[$i++] = [$filters['date_to'] . ' 23:59:59', 'String'];
    }
    if (!empty($filters['status_id'])) {
      $where .= " AND c.contribution_status_id = %{$i}";
      $params[$i++] = [(int) $filters['status_id'], 'Integer'];
    }

    $sql = "
      SELECT
        c.id                                            AS contribution_id,
        c.total_amount                                  AS contribution_amount,
        c.receive_date,
        c.contribution_status_id,
        ANY_VALUE(cs.label)                             AS status_label,
        ct.display_name                                 AS contact_name,
        ct.id                                           AS contact_id,
        COUNT(sc.id)                                    AS soft_credit_count,
        SUM(sc.amount)                                  AS soft_credit_total,
        SUM(sc.amount) - c.total_amount                 AS over_credit_amount
      FROM   civicrm_contribution c
      INNER  JOIN civicrm_contribution_soft sc
               ON sc.contribution_id = c.id
      INNER  JOIN civicrm_contact ct ON ct.id = c.contact_id
      LEFT   JOIN civicrm_option_value cs
               ON cs.value = c.contribution_status_id
              AND cs.option_group_id = (
                    SELECT id FROM civicrm_option_group WHERE name = 'contribution_status'
                  )
      WHERE  {$where}
      GROUP  BY c.id, c.total_amount, c.receive_date, c.contribution_status_id,
                ct.display_name, ct.id
      HAVING SUM(sc.amount) > c.total_amount + 0.01
      ORDER  BY over_credit_amount DESC, c.receive_date DESC
      LIMIT  500
    ";

    return CRM_Core_DAO::executeQuery($sql, $params)->fetchAll();
  }

  /**
   * Return time-series data for the line chart.
   * Groups by day when the date range is ≤ 90 days, otherwise by month.
   * Each bucket: label, hard_amount, soft_total, over_credit.
   *
   * @param array $filters  date_from, date_to, status_id
   * @return array  ['granularity'=>'day|month', 'labels'=>[], 'hard'=>[], 'soft'=>[], 'over'=>[]]
   */
  public static function getChartData(array $filters = []): array {
    $dateFrom = !empty($filters['date_from']) ? $filters['date_from'] : date('Y-m-d', strtotime('-90 days'));
    $dateTo   = !empty($filters['date_to'])   ? $filters['date_to']   : date('Y-m-d');

    $diff = (int) ((strtotime($dateTo) - strtotime($dateFrom)) / 86400);
    $granularity = $diff <= 90 ? 'day' : 'month';
    $dateFmt     = $granularity === 'day' ? '%Y-%m-%d' : '%Y-%m';

    $where  = ' c.is_test = 0 ';
    $params = [
      1 => [$dateFrom . ' 00:00:00', 'String'],
      2 => [$dateTo   . ' 23:59:59', 'String'],
    ];
    $i = 3;
    $where .= ' AND c.receive_date BETWEEN %1 AND %2 ';
    if (!empty($filters['status_id'])) {
      $where .= " AND c.contribution_status_id = %{$i}";
      $params[$i++] = [(int) $filters['status_id'], 'Integer'];
    }

    $rows = CRM_Core_DAO::executeQuery("
      SELECT
        DATE_FORMAT(c.receive_date, '{$dateFmt}') AS bucket,
        SUM(c.total_amount)                        AS hard_amount,
        SUM(sc_agg.sc_total)                       AS soft_total,
        SUM(sc_agg.sc_total) - SUM(c.total_amount) AS over_credit
      FROM civicrm_contribution c
      INNER JOIN (
        SELECT contribution_id, SUM(amount) AS sc_total
        FROM   civicrm_contribution_soft
        GROUP  BY contribution_id
        HAVING SUM(amount) > 0
      ) sc_agg ON sc_agg.contribution_id = c.id
      WHERE {$where}
        AND sc_agg.sc_total > c.total_amount + 0.01
      GROUP BY bucket
      ORDER BY bucket ASC
    ", $params)->fetchAll();

    $labels = $hard = $soft = $over = [];
    foreach ($rows as $r) {
      $labels[] = $r['bucket'];
      $hard[]   = (float) $r['hard_amount'];
      $soft[]   = (float) $r['soft_total'];
      $over[]   = (float) $r['over_credit'];
    }

    return compact('granularity', 'labels', 'hard', 'soft', 'over');
  }

  /**
   * Get the individual soft credit rows for a single contribution.
   */
  public static function getSoftCredits(int $contributionId): array {
    return CRM_Core_DAO::executeQuery("
      SELECT sc.id, sc.amount, sc.soft_credit_type_id,
             ov.label                AS soft_credit_type,
             ct.display_name         AS contact_name,
             ct.id                   AS contact_id
      FROM   civicrm_contribution_soft sc
      INNER  JOIN civicrm_contact ct ON ct.id = sc.contact_id
      LEFT   JOIN civicrm_option_value ov
               ON ov.value = sc.soft_credit_type_id
              AND ov.option_group_id = (
                    SELECT id FROM civicrm_option_group WHERE name = 'soft_credit_type'
                  )
      WHERE  sc.contribution_id = %1
      ORDER  BY sc.id ASC
    ", [1 => [$contributionId, 'Integer']])->fetchAll();
  }

  /**
   * Summary: total over-credited contributions and total excess amount.
   */
  public static function getSummary(array $filters = []): array {
    $rows = self::detect($filters);
    $totalExcess = 0.0;
    foreach ($rows as $r) {
      $totalExcess += (float) $r['over_credit_amount'];
    }
    return [
      'total'        => count($rows),
      'total_excess' => $totalExcess,
    ];
  }

}
