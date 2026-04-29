<?php
/**
 * CiviLedger — Duplicate Payment Detector
 *
 * Finds contributions where the same contact paid the same amount with the same
 * payment instrument within a configurable time window. The pattern indicates an
 * IPN double-fire, network retry, or browser double-submission.
 *
 * Detection approach:
 *   A self-join on civicrm_contribution matches pairs sharing (contact_id,
 *   total_amount, payment_instrument_id) whose receive_date values are within
 *   the time window. Pairs are then merged into sets via union-find so that
 *   triple or quadruple fires are represented as a single set.
 */
class CRM_Civiledger_BAO_DuplicatePaymentDetector {

  /**
   * Find groups of potential duplicate payments.
   *
   * @param int|null $windowMinutes  Time window override; NULL = use setting.
   * @param string   $dateFrom       Lower bound on receive_date (Y-m-d).
   * @param string   $dateTo         Upper bound on receive_date (Y-m-d).
   * @return array   Array of duplicate sets, each with a 'contributions' sub-array.
   */
  public static function findDuplicates(
    ?int $windowMinutes = NULL,
    string $dateFrom = '',
    string $dateTo = ''
  ): array {
    $window   = max(1, $windowMinutes ?? (int) (Civi::settings()->get('civiledger_dup_payment_window') ?? 10));
    $dateFrom = $dateFrom ?: date('Y-m-d', strtotime('-90 days'));
    $dateTo   = $dateTo   ?: date('Y-m-d');

    // Self-join: find every pair within the window.
    // c2.id > c1.id ensures each pair appears exactly once.
    $pairSql = "
      SELECT
        c1.id                                                          AS id1,
        c2.id                                                          AS id2,
        c1.contact_id,
        ct.display_name                                                AS contact_name,
        c1.total_amount,
        ft.name                                                        AS financial_type_name,
        COALESCE(ov.label, 'Unknown')                                 AS payment_instrument_name,
        ABS(TIMESTAMPDIFF(SECOND, c1.receive_date, c2.receive_date))  AS delta_seconds
      FROM civicrm_contribution c1
      JOIN civicrm_contribution c2
        ON  c2.contact_id            = c1.contact_id
        AND c2.total_amount          = c1.total_amount
        AND c2.payment_instrument_id = c1.payment_instrument_id
        AND c2.id > c1.id
        AND ABS(TIMESTAMPDIFF(MINUTE, c1.receive_date, c2.receive_date)) <= %3
      JOIN civicrm_contact ct        ON ct.id  = c1.contact_id
      JOIN civicrm_financial_type ft ON ft.id  = c1.financial_type_id
      LEFT JOIN civicrm_option_value ov
        ON  ov.value = c1.payment_instrument_id
        AND ov.option_group_id = (
              SELECT id FROM civicrm_option_group WHERE name = 'payment_instrument'
            )
      WHERE c1.contribution_status_id = 1
        AND c2.contribution_status_id = 1
        AND c1.is_test = 0
        AND c2.is_test = 0
        AND c1.receive_date BETWEEN %1 AND %2
      ORDER BY c1.receive_date DESC
    ";

    $pairs = CRM_Core_DAO::executeQuery($pairSql, [
      1 => [$dateFrom . ' 00:00:00', 'String'],
      2 => [$dateTo   . ' 23:59:59', 'String'],
      3 => [$window,                  'Integer'],
    ])->fetchAll();

    if (empty($pairs)) {
      return [];
    }

    // Union-find: merge pairs sharing an ID into a single set.
    $groups   = [];   // gid → [id, ...]
    $memberOf = [];   // id  → gid
    $setMeta  = [];   // gid → header metadata

    foreach ($pairs as $pair) {
      $id1 = (int) $pair['id1'];
      $id2 = (int) $pair['id2'];
      $m   = [
        'contact_id'              => $pair['contact_id'],
        'contact_name'            => $pair['contact_name'],
        'total_amount'            => $pair['total_amount'],
        'financial_type_name'     => $pair['financial_type_name'],
        'payment_instrument_name' => $pair['payment_instrument_name'],
      ];

      $g1 = $memberOf[$id1] ?? NULL;
      $g2 = $memberOf[$id2] ?? NULL;

      if ($g1 === NULL && $g2 === NULL) {
        $gid            = count($groups);
        $groups[$gid]   = [$id1, $id2];
        $memberOf[$id1] = $gid;
        $memberOf[$id2] = $gid;
        $setMeta[$gid]  = $m;
      }
      elseif ($g1 !== NULL && $g2 === NULL) {
        $groups[$g1][]  = $id2;
        $memberOf[$id2] = $g1;
      }
      elseif ($g1 === NULL) {
        $groups[$g2][]  = $id1;
        $memberOf[$id1] = $g2;
      }
      elseif ($g1 !== $g2) {
        // Merge the smaller group into the larger one.
        foreach ($groups[$g2] as $mid) {
          $groups[$g1][]  = $mid;
          $memberOf[$mid] = $g1;
        }
        unset($groups[$g2], $setMeta[$g2]);
      }
    }

    if (empty($groups)) {
      return [];
    }

    // Fetch full per-contribution detail for every ID in one query.
    $allIds = array_unique(array_merge(...array_values($groups)));
    $idList = implode(',', array_map('intval', $allIds));

    $detailRows = CRM_Core_DAO::executeQuery("
      SELECT
        c.id,
        c.receive_date,
        c.trxn_id,
        c.check_number,
        c.contribution_status_id  AS status_id,
        COALESCE(cs.label, 'Unknown') AS status_label
      FROM civicrm_contribution c
      LEFT JOIN civicrm_option_value cs
        ON  cs.value = c.contribution_status_id
        AND cs.option_group_id = (
              SELECT id FROM civicrm_option_group WHERE name = 'contribution_status'
            )
      WHERE c.id IN ({$idList})
      ORDER BY c.receive_date ASC
    ")->fetchAll();

    $details = [];
    foreach ($detailRows as $row) {
      $details[(int) $row['id']] = $row;
    }

    // Build the final output set array.
    $sets = [];
    foreach ($groups as $gid => $ids) {
      $m = $setMeta[$gid] ?? NULL;
      if (!$m) {
        continue;
      }

      // Collect and sort contributions by receive_date ascending.
      $contribs = [];
      foreach ($ids as $cid) {
        if (!isset($details[$cid])) {
          continue;
        }
        $d = $details[$cid];
        $contribs[] = [
          'id'           => $cid,
          'receive_date' => $d['receive_date'],
          'trxn_id'      => $d['trxn_id']      ?? '',
          'check_number' => $d['check_number']  ?? '',
          'status_id'    => (int) $d['status_id'],
          'status_label' => $d['status_label'],
          'audit_url'    => CRM_Utils_System::url(
            'civicrm/civiledger/audit-trail',
            "reset=1&contribution_id={$cid}"
          ),
          'view_url'     => CRM_Utils_System::url(
            'civicrm/contact/view/contribution',
            "reset=1&id={$cid}&cid={$m['contact_id']}&action=view"
          ),
        ];
      }
      usort($contribs, fn($a, $b) => strcmp($a['receive_date'], $b['receive_date']));

      // Mark the earliest as the original; compute Δ seconds for the rest.
      $refTs = NULL;
      foreach ($contribs as &$c) {
        $ts = strtotime($c['receive_date']);
        if ($refTs === NULL) {
          $refTs             = $ts;
          $c['delta_seconds'] = 0;
          $c['is_original']  = TRUE;
        }
        else {
          $c['delta_seconds'] = $ts - $refTs;
          $c['is_original']  = FALSE;
        }
      }
      unset($c);

      $sets[] = [
        'contact_id'              => $m['contact_id'],
        'contact_name'            => $m['contact_name'],
        'contact_url'             => CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$m['contact_id']}"),
        'total_amount'            => (float) $m['total_amount'],
        'financial_type_name'     => $m['financial_type_name'],
        'payment_instrument_name' => $m['payment_instrument_name'],
        'contributions'           => $contribs,
      ];
    }

    return $sets;
  }

  /**
   * Cancel a contribution confirmed as a duplicate payment.
   * Uses CiviCRM API so hooks fire correctly, then logs to the audit trail.
   */
  public static function cancelContribution(int $contributionId): array {
    $statusId = (int) CRM_Core_DAO::singleValueQuery(
      "SELECT contribution_status_id FROM civicrm_contribution WHERE id = %1",
      [1 => [$contributionId, 'Integer']]
    );

    if ($statusId !== 1) {
      return [
        'success' => FALSE,
        'message' => ts('Contribution is not Completed — cannot cancel.'),
      ];
    }

    try {
      civicrm_api3('Contribution', 'create', [
        'id'                     => $contributionId,
        'contribution_status_id' => 'Cancelled',
      ]);

      CRM_Civiledger_BAO_AuditLog::record(
        'CANCEL_DUPLICATE_PAYMENT',
        'contribution',
        $contributionId,
        ['reason' => 'Cancelled as duplicate payment via CiviLedger Duplicate Payment Detector']
      );

      return [
        'success' => TRUE,
        'message' => ts('Contribution #%1 cancelled.', [1 => $contributionId]),
      ];
    }
    catch (Exception $e) {
      return ['success' => FALSE, 'message' => $e->getMessage()];
    }
  }

  /**
   * Quick count for dashboard widgets — no grouping, just pair count.
   */
  public static function getSummaryCount(?int $windowMinutes = NULL): int {
    $window   = max(1, $windowMinutes ?? (int) (Civi::settings()->get('civiledger_dup_payment_window') ?? 10));
    $dateFrom = date('Y-m-d', strtotime('-90 days'));
    $dateTo   = date('Y-m-d');

    return (int) CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*)
       FROM civicrm_contribution c1
       JOIN civicrm_contribution c2
         ON  c2.contact_id            = c1.contact_id
         AND c2.total_amount          = c1.total_amount
         AND c2.payment_instrument_id = c1.payment_instrument_id
         AND c2.id > c1.id
         AND ABS(TIMESTAMPDIFF(MINUTE, c1.receive_date, c2.receive_date)) <= %3
       WHERE c1.contribution_status_id = 1
         AND c2.contribution_status_id = 1
         AND c1.is_test = 0
         AND c1.receive_date BETWEEN %1 AND %2",
      [
        1 => [$dateFrom . ' 00:00:00', 'String'],
        2 => [$dateTo   . ' 23:59:59', 'String'],
        3 => [$window,                  'Integer'],
      ]
    );
  }

}
