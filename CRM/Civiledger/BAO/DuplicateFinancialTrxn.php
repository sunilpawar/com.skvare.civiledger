<?php
/**
 * CiviLedger — Duplicate Financial Transaction Detector
 *
 * A single civicrm_contribution can end up linked (via civicrm_entity_financial_trxn)
 * to two or more civicrm_financial_trxn rows that share the same trxn_id AND status_id.
 * This is the footprint of a double IPN callback that each created their own trxn row
 * for the same payment event.
 *
 * Detection: GROUP BY (contribution_id, from_financial_account_id, to_financial_account_id,
 * total_amount, trxn_id, status_id) WHERE COUNT > 1.
 * Groups are excluded when the contribution already has a reversal row (total_amount < 0,
 * is_payment = 1) for the same trxn_id — that pattern is an account correction, not a double IPN.
 * The lowest ft.id in each surviving group is treated as the original; all others are candidates
 * for deletion.
 *
 * Delete action removes:
 *   1. civicrm_entity_financial_trxn WHERE financial_trxn_id = $ftId (all entity sides)
 *   2. civicrm_financial_trxn         WHERE id = $ftId
 * All inside a CRM_Core_Transaction; logged to the hash-chained audit log.
 */
class CRM_Civiledger_BAO_DuplicateFinancialTrxn {

  /**
   * Find contributions that have duplicate financial transactions.
   *
   * @param string $dateFrom  Lower bound on contribution receive_date (Y-m-d).
   * @param string $dateTo    Upper bound on contribution receive_date (Y-m-d).
   * @return array  Array of duplicate sets.
   */
  public static function findDuplicates(string $dateFrom = '', string $dateTo = ''): array {
    $dateFrom = $dateFrom ?: date('Y-m-d', strtotime('-90 days'));
    $dateTo   = $dateTo   ?: date('Y-m-d');

    $params = [
      1 => [$dateFrom . ' 00:00:00', 'String'],
      2 => [$dateTo   . ' 23:59:59', 'String'],
    ];

    // Step 1 — find every (contribution, from_account, to_account, amount, trxn_id, status_id) group with 2+ rows.
    $groupSql = "
      SELECT
        c.id                                                    AS contribution_id,
        c.contact_id,
        ct.display_name                                         AS contact_name,
        c.receive_date                                          AS contribution_date,
        c.total_amount                                          AS contribution_amount,
        ftype.name                                              AS financial_type_name,
        ft.from_financial_account_id,
        ft.to_financial_account_id,
        ft.total_amount                                         AS trxn_amount,
        ft.trxn_id,
        ft.status_id,
        COALESCE(MAX(cs.label), 'Unknown')                     AS status_label,
        COUNT(ft.id)                                            AS trxn_count
      FROM civicrm_entity_financial_trxn eft
      JOIN civicrm_financial_trxn ft
        ON  ft.id        = eft.financial_trxn_id
        AND ft.is_payment = 1
      JOIN civicrm_contribution c
        ON  eft.entity_table = 'civicrm_contribution'
        AND eft.entity_id    = c.id
      JOIN civicrm_contact ct         ON ct.id    = c.contact_id
      JOIN civicrm_financial_type ftype ON ftype.id = c.financial_type_id
      LEFT JOIN civicrm_option_value cs
        ON  cs.value = ft.status_id
        AND cs.option_group_id = (
              SELECT id FROM civicrm_option_group WHERE name = 'contribution_status'
            )
      WHERE c.is_test = 0
        AND c.receive_date BETWEEN %1 AND %2
      GROUP BY c.id, ft.from_financial_account_id, ft.to_financial_account_id, ft.total_amount, ft.trxn_id, ft.status_id
      HAVING COUNT(ft.id) > 1
        AND NOT EXISTS (
          SELECT 1
          FROM civicrm_financial_trxn ft2
          JOIN civicrm_entity_financial_trxn eft2
            ON  eft2.financial_trxn_id = ft2.id
            AND eft2.entity_table      = 'civicrm_contribution'
            AND eft2.entity_id         = c.id
          WHERE (ft2.trxn_id                  <=> ft.trxn_id)
            AND (ft2.from_financial_account_id <=> ft.from_financial_account_id)
            AND (ft2.to_financial_account_id   <=> ft.to_financial_account_id)
            AND ft2.total_amount < 0
            AND ft2.is_payment   = 1
        )
      ORDER BY c.receive_date DESC
    ";

    $groups = CRM_Core_DAO::executeQuery($groupSql, $params)->fetchAll();
    if (empty($groups)) {
      return [];
    }

    // Step 2 — fetch full trxn details for all involved contributions in one query.
    $contribIds = array_unique(array_column($groups, 'contribution_id'));
    $idList     = implode(',', array_map('intval', $contribIds));

    $trxnSql = "
      SELECT
        ft.id,
        ft.trxn_id,
        ft.trxn_date,
        ft.total_amount,
        ft.fee_amount,
        ft.net_amount,
        ft.status_id,
        ft.from_financial_account_id,
        ft.to_financial_account_id,
        ft.check_number,
        ft.trxn_result_code,
        eft.entity_id                                           AS contribution_id,
        COALESCE(fa_from.name, '—')                            AS from_account_name,
        COALESCE(fa_to.name,   '—')                            AS to_account_name,
        COALESCE(ov.label,     'Unknown')                      AS payment_instrument_name
      FROM civicrm_financial_trxn ft
      JOIN civicrm_entity_financial_trxn eft
        ON  eft.financial_trxn_id = ft.id
        AND eft.entity_table      = 'civicrm_contribution'
      LEFT JOIN civicrm_financial_account fa_from
        ON fa_from.id = ft.from_financial_account_id
      LEFT JOIN civicrm_financial_account fa_to
        ON fa_to.id   = ft.to_financial_account_id
      LEFT JOIN civicrm_option_value ov
        ON  ov.value = ft.payment_instrument_id
        AND ov.option_group_id = (
              SELECT id FROM civicrm_option_group WHERE name = 'payment_instrument'
            )
      WHERE eft.entity_id IN ({$idList})
        AND ft.is_payment = 1
      ORDER BY eft.entity_id ASC, ft.trxn_id ASC, ft.status_id ASC, ft.id ASC
    ";

    // Index detail rows by contribution_id → from_account:to_account:amount:trxn_id:status_id → [rows]
    $trxnIndex = [];
    foreach (CRM_Core_DAO::executeQuery($trxnSql)->fetchAll() as $row) {
      $key = $row['from_financial_account_id'] . ':' . $row['to_financial_account_id'] . ':' . $row['total_amount'] . ':' . $row['trxn_id'] . ':' . $row['status_id'];
      $trxnIndex[(int) $row['contribution_id']][$key][] = $row;
    }

    // Step 3 — assemble sets for the template.
    $sets = [];
    foreach ($groups as $g) {
      $cid    = (int) $g['contribution_id'];
      $key    = $g['from_financial_account_id'] . ':' . $g['to_financial_account_id'] . ':' . $g['trxn_amount'] . ':' . $g['trxn_id'] . ':' . $g['status_id'];
      $rows   = $trxnIndex[$cid][$key] ?? [];
      if (count($rows) < 2) {
        continue; // already resolved or data mismatch — skip
      }

      // Lowest id = original; everything else = duplicate candidate.
      $minId = min(array_column($rows, 'id'));
      $trxns = [];
      foreach ($rows as $r) {
        $trxns[] = [
          'id'                     => (int) $r['id'],
          'trxn_date'              => $r['trxn_date'],
          'total_amount'           => (float) $r['total_amount'],
          'fee_amount'             => (float) ($r['fee_amount'] ?? 0),
          'net_amount'             => (float) ($r['net_amount'] ?? 0),
          'check_number'           => $r['check_number'] ?? '',
          'trxn_result_code'       => $r['trxn_result_code'] ?? '',
          'from_account_name'      => $r['from_account_name'],
          'to_account_name'        => $r['to_account_name'],
          'payment_instrument_name' => $r['payment_instrument_name'],
          'is_original'            => (int) $r['id'] === $minId,
        ];
      }

      $sets[] = [
        'contribution_id'     => $cid,
        'contact_id'          => $g['contact_id'],
        'contact_name'        => $g['contact_name'],
        'contact_url'         => CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$g['contact_id']}"),
        'contribution_date'   => $g['contribution_date'],
        'contribution_amount' => (float) $g['contribution_amount'],
        'financial_type_name' => $g['financial_type_name'],
        'trxn_id'             => $g['trxn_id'],
        'status_label'        => $g['status_label'],
        'audit_url'           => CRM_Utils_System::url(
          'civicrm/civiledger/audit-trail', "reset=1&contribution_id={$cid}"
        ),
        'contribution_url'    => CRM_Utils_System::url(
          'civicrm/contact/view/contribution',
          "reset=1&id={$cid}&cid={$g['contact_id']}&action=view"
        ),
        'trxns'               => $trxns,
      ];
    }

    return $sets;
  }

  /**
   * Delete one duplicate civicrm_financial_trxn and all its entity links.
   *
   * Safety rules:
   *   - The trxn must actually be linked to $contributionId.
   *   - The trxn must NOT be the lowest-ID sibling (that is the original).
   *   - At least one sibling must remain after deletion.
   *
   * @param int $ftId            civicrm_financial_trxn.id to delete.
   * @param int $contributionId  Parent contribution (for ownership verification).
   * @return array ['success' => bool, 'message' => string]
   */
  public static function deleteDuplicateTrxn(int $ftId, int $contributionId): array {
    // Ownership check: verify this trxn is linked to the given contribution.
    $linked = (int) CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_entity_financial_trxn
       WHERE financial_trxn_id = %1
         AND entity_table       = 'civicrm_contribution'
         AND entity_id          = %2",
      [
        1 => [$ftId,           'Integer'],
        2 => [$contributionId, 'Integer'],
      ]
    );
    if (!$linked) {
      return ['success' => FALSE, 'message' => ts('Transaction is not linked to this contribution.')];
    }

    // Fetch the trxn_id and status_id for this row so we can find siblings.
    $trxnRow = CRM_Core_DAO::executeQuery(
      "SELECT trxn_id, status_id FROM civicrm_financial_trxn WHERE id = %1",
      [1 => [$ftId, 'Integer']]
    )->fetchAll();
    if (empty($trxnRow)) {
      return ['success' => FALSE, 'message' => ts('Transaction not found.')];
    }
    $trxnId  = $trxnRow[0]['trxn_id'];
    $statusId = (int) $trxnRow[0]['status_id'];

    // Find all siblings (same contribution, same trxn_id, same status_id, is_payment=1).
    $siblings = CRM_Core_DAO::executeQuery(
      "SELECT ft.id
       FROM civicrm_financial_trxn ft
       JOIN civicrm_entity_financial_trxn eft
         ON  eft.financial_trxn_id = ft.id
         AND eft.entity_table      = 'civicrm_contribution'
         AND eft.entity_id         = %1
       WHERE ft.trxn_id   = %2
         AND ft.status_id = %3
         AND ft.is_payment = 1",
      [
        1 => [$contributionId, 'Integer'],
        2 => [$trxnId,         'String'],
        3 => [$statusId,       'Integer'],
      ]
    )->fetchAll();

    $siblingIds = array_map(fn($r) => (int) $r['id'], $siblings);
    $minId      = min($siblingIds);

    if ($ftId === $minId) {
      return ['success' => FALSE, 'message' => ts('Cannot delete the original (earliest) transaction.')];
    }
    if (count($siblingIds) < 2) {
      return ['success' => FALSE, 'message' => ts('Only one transaction remains — nothing to delete.')];
    }

    $tx = new CRM_Core_Transaction();
    try {
      // Remove all entity links for this trxn (contribution side + financial item side).
      CRM_Core_DAO::executeQuery(
        "DELETE FROM civicrm_entity_financial_trxn WHERE financial_trxn_id = %1",
        [1 => [$ftId, 'Integer']]
      );

      // Remove the trxn itself.
      CRM_Core_DAO::executeQuery(
        "DELETE FROM civicrm_financial_trxn WHERE id = %1",
        [1 => [$ftId, 'Integer']]
      );

      CRM_Civiledger_BAO_AuditLog::record(
        'DELETE_DUPLICATE_TRXN',
        'financial_trxn',
        $ftId,
        [
          'contribution_id' => $contributionId,
          'trxn_id'         => $trxnId,
          'reason'          => 'Deleted as duplicate financial transaction via CiviLedger',
        ]
      );

      $tx->commit();
      return ['success' => TRUE, 'message' => ts('Transaction #%1 deleted.', [1 => $ftId])];
    }
    catch (Exception $e) {
      $tx->rollback();
      return ['success' => FALSE, 'message' => $e->getMessage()];
    }
  }

  /**
   * Quick pair count for dashboard widgets (last 90 days).
   */
  public static function getSummaryCount(): int {
    $dateFrom = date('Y-m-d', strtotime('-90 days'));
    $dateTo   = date('Y-m-d');

    return (int) CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*)
       FROM (
         SELECT 1
         FROM civicrm_entity_financial_trxn eft
         JOIN civicrm_financial_trxn ft
           ON  ft.id         = eft.financial_trxn_id
           AND ft.is_payment = 1
         JOIN civicrm_contribution c
           ON  eft.entity_table = 'civicrm_contribution'
           AND eft.entity_id    = c.id
         WHERE c.is_test = 0
           AND c.receive_date BETWEEN %1 AND %2
         GROUP BY c.id, ft.from_financial_account_id, ft.to_financial_account_id, ft.total_amount, ft.trxn_id, ft.status_id
         HAVING COUNT(ft.id) > 1
           AND NOT EXISTS (
             SELECT 1
             FROM civicrm_financial_trxn ft2
             JOIN civicrm_entity_financial_trxn eft2
               ON  eft2.financial_trxn_id = ft2.id
               AND eft2.entity_table      = 'civicrm_contribution'
               AND eft2.entity_id         = c.id
             WHERE (ft2.trxn_id                  <=> ft.trxn_id)
               AND (ft2.from_financial_account_id <=> ft.from_financial_account_id)
               AND (ft2.to_financial_account_id   <=> ft.to_financial_account_id)
               AND ft2.total_amount < 0
               AND ft2.is_payment   = 1
           )
       ) grouped",
      [
        1 => [$dateFrom . ' 00:00:00', 'String'],
        2 => [$dateTo   . ' 23:59:59', 'String'],
      ]
    );
  }

}
