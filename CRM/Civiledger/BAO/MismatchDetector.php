<?php
/**
 * BAO: Amount Mismatch Detector
 *
 * Verifies the golden rule:
 *   SUM(line_items.line_total) == SUM(financial_items.amount)
 *   == SUM(financial_trxn.total_amount WHERE is_payment=1)
 *   == contribution.total_amount
 */
class CRM_Civiledger_BAO_MismatchDetector {

  /**
   * Find all mismatched contributions.
   *
   * @param array $filters
   * @return array
   */
  public static function detect(array $filters = []): array {
    $where = self::buildWhere($filters);
    $sql = "
      SELECT
        c.id                             AS contribution_id,
        c.total_amount                   AS contribution_amount,
        c.receive_date,
        c.contribution_status_id,
        CONCAT(ct.first_name, ' ', ct.last_name) AS contact_name,
        ct.id                            AS contact_id,
        ft.name                          AS financial_type,
        COALESCE(li_sum.line_total, 0)   AS line_item_total,
        COALESCE(fi_sum.fi_total, 0)     AS financial_item_total,
        COALESCE(trxn_sum.trxn_total, 0) AS trxn_total,
        -- Flags
        ABS(c.total_amount - COALESCE(li_sum.line_total, 0))   AS line_item_diff,
        ABS(c.total_amount - COALESCE(fi_sum.fi_total, 0))     AS financial_item_diff,
        ABS(c.total_amount - COALESCE(trxn_sum.trxn_total, 0)) AS trxn_diff
      FROM civicrm_contribution c
      LEFT JOIN civicrm_contact ct ON ct.id = c.contact_id
      LEFT JOIN civicrm_financial_type ft ON ft.id = c.financial_type_id

      -- Sum of line items
      LEFT JOIN (
        SELECT contribution_id, SUM(line_total) AS line_total
        FROM civicrm_line_item
        WHERE qty <> 0
        GROUP BY contribution_id
      ) li_sum ON li_sum.contribution_id = c.id

      -- Sum of financial items
      LEFT JOIN (
        SELECT li2.contribution_id, SUM(fi2.amount) AS fi_total
        FROM civicrm_financial_item fi2
        INNER JOIN civicrm_line_item li2
               ON fi2.entity_table = 'civicrm_line_item'
              AND fi2.entity_id = li2.id
        GROUP BY li2.contribution_id
      ) fi_sum ON fi_sum.contribution_id = c.id

      -- Sum of payment transactions (include reversals so reversal+correction nets correctly)
      LEFT JOIN (
        SELECT eft2.entity_id AS contribution_id,
               SUM(ft2.total_amount) AS trxn_total
        FROM civicrm_entity_financial_trxn eft2
        INNER JOIN civicrm_financial_trxn ft2 ON ft2.id = eft2.financial_trxn_id
        WHERE eft2.entity_table = 'civicrm_contribution'
          AND ft2.is_payment = 1
        GROUP BY eft2.entity_id
      ) trxn_sum ON trxn_sum.contribution_id = c.id

      WHERE c.is_test = 0
        AND c.contribution_status_id = 1
        {$where}
      HAVING line_item_diff > 0.01
          OR financial_item_diff > 0.01
          OR trxn_diff > 0.01
      ORDER BY c.receive_date DESC
      LIMIT 500
    ";

    $rows = CRM_Core_DAO::executeQuery($sql)->fetchAll();

    // Tag each row with what's wrong
    foreach ($rows as &$row) {
      $row['issues'] = [];
      if ($row['line_item_diff'] > 0.01) {
        $row['issues'][] = 'Line items do not match contribution total';
      }
      if ($row['financial_item_diff'] > 0.01) {
        $row['issues'][] = 'Financial items do not match contribution total';
      }
      if ($row['trxn_diff'] > 0.01) {
        $row['issues'][] = 'Payments recorded do not match contribution total';
      }
    }

    return $rows;
  }

  /**
   * Get a summary count grouped by type of mismatch.
   */
  public static function getSummary(array $filters = []): array {
    $rows = self::detect($filters);
    $summary = [
      'total' => count($rows),
      'line_item_mismatch' => 0,
      'financial_item_mismatch' => 0,
      'trxn_mismatch' => 0,
    ];
    foreach ($rows as $row) {
      if ($row['line_item_diff'] > 0.01) {
        $summary['line_item_mismatch']++;
      }
      if ($row['financial_item_diff'] > 0.01) {
        $summary['financial_item_mismatch']++;
      }
      if ($row['trxn_diff'] > 0.01) {
        $summary['trxn_mismatch']++;
      }
    }
    return $summary;
  }

  /**
   * Alias: getMismatchCounts() → getSummary()
   */
  public static function getMismatchCounts(array $filters = []): array {
    return self::getSummary($filters);
  }

  /**
   * Alias: detectMismatches() with limit
   */
  public static function detectMismatches(int $limit = 50, array $filters = []): array {
    return array_slice(self::detect($filters), 0, $limit);
  }

  /**
   * Return granular breakdown for a single contribution: line items, financial
   * items, and all financial transactions — used by the mismatch_detail AJAX op.
   */
  public static function getDetail(int $cid): array {
    $contribs = CRM_Core_DAO::executeQuery("
      SELECT c.id, c.total_amount, c.receive_date,
             CONCAT(ct.first_name, ' ', ct.last_name) AS contact_name,
             ft.name AS financial_type
      FROM   civicrm_contribution c
      LEFT JOIN civicrm_contact ct       ON ct.id = c.contact_id
      LEFT JOIN civicrm_financial_type ft ON ft.id = c.financial_type_id
      WHERE  c.id = %1
    ", [1 => [$cid, 'Integer']])->fetchAll();

    if (empty($contribs)) {
      return ['found' => FALSE];
    }

    $lineItems = CRM_Core_DAO::executeQuery("
      SELECT li.id, li.label, li.qty, li.unit_price, li.line_total,
             ft.name AS financial_type
      FROM   civicrm_line_item li
      LEFT JOIN civicrm_financial_type ft ON ft.id = li.financial_type_id
      WHERE  li.contribution_id = %1 AND li.qty <> 0
      ORDER  BY li.id ASC
    ", [1 => [$cid, 'Integer']])->fetchAll();

    $fiItems = CRM_Core_DAO::executeQuery("
      SELECT fi.id, fi.amount, fi.description,
             ov.label AS status_label
      FROM   civicrm_financial_item fi
      INNER JOIN civicrm_line_item li
              ON fi.entity_table = 'civicrm_line_item' AND fi.entity_id = li.id
      LEFT  JOIN civicrm_option_value ov
              ON ov.value = fi.status_id
             AND ov.option_group_id = (
                   SELECT id FROM civicrm_option_group WHERE name = 'financial_item_status'
                 )
      WHERE  li.contribution_id = %1
      ORDER  BY fi.id ASC
    ", [1 => [$cid, 'Integer']])->fetchAll();

    $transactions = CRM_Core_DAO::executeQuery("
      SELECT ft.id, ft.total_amount, ft.trxn_date, ft.is_payment, ft.trxn_id,
             ov.label AS status_label
      FROM   civicrm_financial_trxn ft
      INNER JOIN civicrm_entity_financial_trxn eft
              ON eft.financial_trxn_id = ft.id
             AND eft.entity_table = 'civicrm_contribution'
      LEFT  JOIN civicrm_option_value ov
              ON ov.value = ft.status_id
             AND ov.option_group_id = (
                   SELECT id FROM civicrm_option_group WHERE name = 'contribution_status'
                 )
      WHERE  eft.entity_id = %1
      ORDER  BY ft.trxn_date ASC, ft.id ASC
    ", [1 => [$cid, 'Integer']])->fetchAll();

    return [
      'found'        => TRUE,
      'contribution' => $contribs[0],
      'line_items'   => $lineItems,
      'fi_items'     => $fiItems,
      'transactions' => $transactions,
    ];
  }

  private static function buildWhere(array $filters): string {
    $where = '';
    if (!empty($filters['date_from'])) {
      $d = CRM_Utils_Type::escape($filters['date_from'], 'String');
      $where .= " AND c.receive_date >= '{$d}'";
    }
    if (!empty($filters['date_to'])) {
      $d = CRM_Utils_Type::escape($filters['date_to'], 'String');
      $where .= " AND c.receive_date <= '{$d} 23:59:59'";
    }
    return $where;
  }

}
