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
    string $dateTo = '',
    string $contactType = ''
  ): array {
    $window   = max(1, $windowMinutes ?? (int) (Civi::settings()->get('civiledger_dup_payment_window') ?? 10));
    $dateFrom = $dateFrom ?: date('Y-m-d', strtotime('-90 days'));
    $dateTo   = $dateTo   ?: date('Y-m-d');

    $contactTypeFilter = '';
    $params = [
      1 => [$dateFrom . ' 00:00:00', 'String'],
      2 => [$dateTo   . ' 23:59:59', 'String'],
      3 => [$window,                  'Integer'],
    ];
    if ($contactType !== '') {
      $contactTypeFilter = 'AND ct.contact_type = %4';
      $params[4]         = [$contactType, 'String'];
    }

    // Self-join: find every pair within the window.
    // c2.id > c1.id ensures each pair appears exactly once.
    $pairSql = "
      SELECT
        c1.id                                                          AS id1,
        c2.id                                                          AS id2,
        c1.contact_id,
        ct.display_name                                                AS contact_name,
        ct.contact_type,
        c1.total_amount,
        ft.name                                                        AS financial_type_name,
        COALESCE(ov.label, 'Unknown')                                 AS payment_instrument_name,
        ABS(TIMESTAMPDIFF(SECOND, c1.receive_date, c2.receive_date))  AS delta_seconds
      FROM civicrm_contribution c1
      JOIN civicrm_contribution c2
        ON  c2.contact_id            = c1.contact_id
        AND c2.total_amount          = c1.total_amount
        AND c2.payment_instrument_id = c1.payment_instrument_id
        AND c2.financial_type_id     = c1.financial_type_id
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
        {$contactTypeFilter}
      ORDER BY c1.receive_date DESC
    ";

    $pairs = CRM_Core_DAO::executeQuery($pairSql, $params)->fetchAll();

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
        'contact_type'            => $pair['contact_type'],
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
        c.total_amount,
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
          'total_amount' => (float) $d['total_amount'],
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
        'contact_type'            => $m['contact_type'],
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
   * Issue a gateway refund for a duplicate payment contribution.
   *
   * Workflow:
   *   1. Verify the contribution is Completed.
   *   2. Resolve the payment processor from the contribution.
   *   3. Instantiate the processor object and call supportsRefund().
   *   4. Get the gateway trxn_id from civicrm_financial_trxn.
   *   5. Call doRefund() with trxn_id, amount and currency.
   *   6. Record a negative payment in CiviCRM via Payment.create.
   *   7. Write to the hash-chained audit log.
   *
   * @param int $contributionId  Contribution to refund.
   * @return array ['success' => bool, 'message' => string, 'refund_trxn_id' => string]
   */
  public static function refundContribution(int $contributionId): array {
    // Fetch contribution details.
    $contribRows = CRM_Core_DAO::executeQuery(
      "SELECT contribution_status_id, total_amount, currency, payment_processor_id
       FROM civicrm_contribution WHERE id = %1",
      [1 => [$contributionId, 'Integer']]
    )->fetchAll();
    if (empty($contribRows)) {
      return ['success' => FALSE, 'message' => ts('Contribution not found.')];
    }
    $row         = $contribRows[0];
    $statusId    = (int)   $row['contribution_status_id'];
    $amount      = (float) $row['total_amount'];
    $currency    = $row['currency'] ?: 'USD';
    $processorId = (int)   $row['payment_processor_id'];

    if ($statusId !== 1) {
      return ['success' => FALSE, 'message' => ts('Contribution is not Completed — cannot issue a refund.')];
    }
    if (!$processorId) {
      return ['success' => FALSE, 'message' => ts('No payment processor is associated with this contribution. Please process the refund manually through your payment gateway.')];
    }

    // Get the gateway trxn_id from the payment financial transaction.
    $gatewayTrxnId = CRM_Core_DAO::singleValueQuery(
      "SELECT ft.trxn_id
       FROM civicrm_financial_trxn ft
       JOIN civicrm_entity_financial_trxn eft
         ON  eft.financial_trxn_id = ft.id
         AND eft.entity_table      = 'civicrm_contribution'
       WHERE eft.entity_id = %1
         AND ft.is_payment  = 1
       ORDER BY ft.id ASC
       LIMIT 1",
      [1 => [$contributionId, 'Integer']]
    );
    if (empty($gatewayTrxnId)) {
      return ['success' => FALSE, 'message' => ts('No gateway transaction ID found. Please process the refund manually through your payment gateway.')];
    }

    // Instantiate the payment processor object.
    try {
      $processorRecord = CRM_Financial_BAO_PaymentProcessor::getPayment($processorId, 'live');
      $processor       = Civi\Payment\System::singleton()->getByProcessor($processorRecord);
    }
    catch (Exception $e) {
      return ['success' => FALSE, 'message' => ts('Could not load payment processor: %1', [1 => $e->getMessage()])];
    }

    // Check refund support — processors declare this via supportsRefund().
    if (!method_exists($processor, 'supportsRefund') || !$processor->supportsRefund()) {
      return [
        'success' => FALSE,
        'message' => ts(
          'The payment processor "%1" does not support automated refunds. Please process the refund manually through your payment gateway dashboard.',
          [1 => $processorRecord['name'] ?? $processorRecord['title'] ?? ts('Unknown')]
        ),
      ];
    }

    // Issue the refund at the gateway.
    try {
      $refundResult = $processor->doRefund([
        'trxn_id'  => $gatewayTrxnId,
        'amount'   => $amount,
        'currency' => $currency,
      ]);

      // Record the negative payment in CiviCRM financials.
      civicrm_api3('Payment', 'create', [
        'contribution_id'                   => $contributionId,
        'total_amount'                       => -abs($amount),
        'payment_processor_id'              => $processorId,
        'trxn_id'                            => $refundResult['refund_trxn_id'] ?? '',
        'fee_amount'                         => $refundResult['fee_amount']     ?? 0,
        'is_send_contribution_notification' => 0,
      ]);

      CRM_Civiledger_BAO_AuditLog::record(
        'REFUND_DUPLICATE_PAYMENT',
        'contribution',
        $contributionId,
        [
          'amount'          => $amount,
          'gateway_trxn_id' => $gatewayTrxnId,
          'refund_trxn_id'  => $refundResult['refund_trxn_id'] ?? '',
          'refund_status'   => $refundResult['refund_status']  ?? '',
          'reason'          => 'Refunded as duplicate payment via CiviLedger',
        ]
      );

      return [
        'success'        => TRUE,
        'message'        => ts('Refund issued for contribution #%1.', [1 => $contributionId]),
        'refund_trxn_id' => $refundResult['refund_trxn_id'] ?? '',
        'refund_status'  => $refundResult['refund_status']  ?? '',
      ];
    }
    catch (Exception $e) {
      return ['success' => FALSE, 'message' => $e->getMessage()];
    }
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
