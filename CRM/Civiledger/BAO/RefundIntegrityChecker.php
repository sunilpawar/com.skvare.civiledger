<?php
/**
 * CiviLedger - Refund / Reversal Integrity Checker
 *
 * Three categories of refund anomalies:
 *   1. No Reversal        — contribution_status_id=7 but no negative financial_trxn linked
 *   2. Amount Mismatch    — status=7 with reversal(s) but totals don't match original
 *   3. Orphaned Reversal  — negative financial_trxn exists but contribution status is not 7
 *
 * @package com.skvare.civiledger
 */
class CRM_Civiledger_BAO_RefundIntegrityChecker {

  public static function runCheck(array $filters = []): array {
    return [
      'no_reversal'       => self::getNoReversal($filters),
      'amount_mismatch'   => self::getAmountMismatch($filters),
      'orphaned_reversal' => self::getOrphanedReversal($filters),
    ];
  }

  // -----------------------------------------------------------------------
  // Issue 1: Refunded contributions with no negative financial transaction
  // -----------------------------------------------------------------------

  public static function getNoReversal(array $filters = []): array {
    [$where, $params] = self::buildWhere('c', $filters);
    $sql = "
      SELECT
        c.id                AS contribution_id,
        c.total_amount,
        c.currency,
        c.receive_date,
        c.trxn_id           AS transaction_id,
        ct.id               AS contact_id,
        ct.display_name     AS contact_name,
        ft.name             AS financial_type_name,
        pi.label            AS payment_instrument
      FROM civicrm_contribution c
      INNER JOIN civicrm_contact ct ON ct.id = c.contact_id
      LEFT  JOIN civicrm_financial_type ft ON ft.id = c.financial_type_id
      LEFT  JOIN civicrm_option_value pi
               ON pi.value = c.payment_instrument_id
              AND pi.option_group_id = (
                    SELECT id FROM civicrm_option_group WHERE name = 'payment_instrument'
                  )
      WHERE c.contribution_status_id = 7
        AND c.is_test = 0
        AND NOT EXISTS (
          SELECT 1
          FROM civicrm_entity_financial_trxn eft2
          INNER JOIN civicrm_financial_trxn ft2 ON ft2.id = eft2.financial_trxn_id
          WHERE eft2.entity_table = 'civicrm_contribution'
            AND eft2.entity_id = c.id
            AND ft2.total_amount < 0
        )
        {$where}
      ORDER BY c.receive_date DESC
      LIMIT 500
    ";
    return self::addContribUrl(CRM_Core_DAO::executeQuery($sql, $params)->fetchAll());
  }

  // -----------------------------------------------------------------------
  // Issue 2: Refunded contributions where reversal amount doesn't match
  // -----------------------------------------------------------------------

  public static function getAmountMismatch(array $filters = []): array {
    [$where, $params] = self::buildWhere('c', $filters);
    $sql = "
      SELECT
        c.id                                         AS contribution_id,
        c.total_amount                               AS original_amount,
        c.currency,
        c.receive_date,
        c.trxn_id                                    AS transaction_id,
        ct.id                                        AS contact_id,
        ct.display_name                              AS contact_name,
        ft.name                                      AS financial_type_name,
        pi.label                                     AS payment_instrument,
        SUM(ABS(trxn.total_amount))                  AS refund_amount,
        c.total_amount - SUM(ABS(trxn.total_amount)) AS gap_amount
      FROM civicrm_contribution c
      INNER JOIN civicrm_contact ct ON ct.id = c.contact_id
      LEFT  JOIN civicrm_financial_type ft ON ft.id = c.financial_type_id
      LEFT  JOIN civicrm_option_value pi
               ON pi.value = c.payment_instrument_id
              AND pi.option_group_id = (
                    SELECT id FROM civicrm_option_group WHERE name = 'payment_instrument'
                  )
      INNER JOIN civicrm_entity_financial_trxn eft
               ON eft.entity_table = 'civicrm_contribution'
              AND eft.entity_id = c.id
      INNER JOIN civicrm_financial_trxn trxn
               ON trxn.id = eft.financial_trxn_id
              AND trxn.total_amount < 0
      WHERE c.contribution_status_id = 7
        AND c.is_test = 0
        {$where}
      GROUP BY c.id, c.total_amount, c.currency, c.receive_date, c.trxn_id,
               ct.id, ct.display_name, ft.name, pi.label
      HAVING ABS(c.total_amount - SUM(ABS(trxn.total_amount))) > 0.01
      ORDER BY ABS(c.total_amount - SUM(ABS(trxn.total_amount))) DESC
      LIMIT 500
    ";
    return self::addContribUrl(CRM_Core_DAO::executeQuery($sql, $params)->fetchAll());
  }

  // -----------------------------------------------------------------------
  // Issue 3: Negative transaction exists but contribution status is not 7
  // -----------------------------------------------------------------------

  public static function getOrphanedReversal(array $filters = []): array {
    [$where, $params] = self::buildWhere('c', $filters);
    $sql = "
      SELECT
        c.id                       AS contribution_id,
        c.total_amount             AS original_amount,
        c.currency,
        c.receive_date,
        c.contribution_status_id,
        cs.label                   AS status_label,
        ct.id                      AS contact_id,
        ct.display_name            AS contact_name,
        ft.name                    AS financial_type_name,
        COUNT(trxn.id)             AS reversal_count,
        SUM(ABS(trxn.total_amount)) AS total_reversed,
        MAX(trxn.trxn_date)        AS latest_reversal_date
      FROM civicrm_contribution c
      INNER JOIN civicrm_contact ct ON ct.id = c.contact_id
      LEFT  JOIN civicrm_financial_type ft ON ft.id = c.financial_type_id
      LEFT  JOIN civicrm_option_value cs
               ON cs.value = c.contribution_status_id
              AND cs.option_group_id = (
                    SELECT id FROM civicrm_option_group WHERE name = 'contribution_status'
                  )
      INNER JOIN civicrm_entity_financial_trxn eft
               ON eft.entity_table = 'civicrm_contribution'
              AND eft.entity_id = c.id
      INNER JOIN civicrm_financial_trxn trxn
               ON trxn.id = eft.financial_trxn_id
              AND trxn.total_amount < 0
      WHERE c.contribution_status_id != 7
        AND c.is_test = 0
        {$where}
      GROUP BY c.id, c.total_amount, c.currency, c.receive_date,
               c.contribution_status_id, cs.label,
               ct.id, ct.display_name, ft.name
      ORDER BY latest_reversal_date DESC
      LIMIT 500
    ";
    return self::addContribUrl(CRM_Core_DAO::executeQuery($sql, $params)->fetchAll());
  }

  // -----------------------------------------------------------------------
  // Helpers
  // -----------------------------------------------------------------------

  private static function buildWhere(string $alias, array $filters): array {
    $where  = '';
    $params = [];
    $i      = 1;
    if (!empty($filters['date_from'])) {
      $where .= " AND {$alias}.receive_date >= %{$i}";
      $params[$i++] = [$filters['date_from'] . ' 00:00:00', 'String'];
    }
    if (!empty($filters['date_to'])) {
      $where .= " AND {$alias}.receive_date <= %{$i}";
      $params[$i++] = [$filters['date_to'] . ' 23:59:59', 'String'];
    }
    if (!empty($filters['financial_type_id'])) {
      $where .= " AND {$alias}.financial_type_id = %{$i}";
      $params[$i++] = [(int) $filters['financial_type_id'], 'Integer'];
    }
    return [$where, $params];
  }

  private static function addContribUrl(array $rows): array {
    foreach ($rows as &$row) {
      $row['contribution_url'] = CRM_Civiledger_BAO_Utils::getAuditTrailUrl((int) $row['contribution_id']);
    }
    return $rows;
  }

}
