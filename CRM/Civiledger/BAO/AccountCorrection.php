<?php
/**
 * CiviLedger - Feature 6: Account Correction Tool
 *
 * Corrects from_financial_account_id or to_financial_account_id on a
 * financial transaction using proper double-entry reversal:
 *
 *   Step 1: Create a NEGATIVE reversal transaction on the OLD accounts
 *   Step 2: Create a NEW positive transaction on the CORRECT accounts
 *   Step 3: Link both to the same contribution
 *   Step 4: Log the correction for audit purposes
 */
class CRM_Civiledger_BAO_AccountCorrection {

  /**
   * Get a financial transaction with full account details.
   */
  public static function getTransaction(int $trxnId) {
    $sql = "
      SELECT ft.id, ft.total_amount, ft.fee_amount, ft.net_amount,
             ft.currency, ft.trxn_date, ft.is_payment,
             ft.trxn_id AS processor_ref, ft.payment_instrument_id,
             ft.check_number, ft.status_id,
             ft.from_financial_account_id, fa_from.name AS from_account_name,
             ft.to_financial_account_id,   fa_to.name   AS to_account_name,
             c.id AS contribution_id, con.display_name AS contact_name
      FROM civicrm_financial_trxn ft
      LEFT JOIN civicrm_financial_account fa_from ON fa_from.id = ft.from_financial_account_id
      LEFT JOIN civicrm_financial_account fa_to   ON fa_to.id   = ft.to_financial_account_id
      LEFT JOIN civicrm_entity_financial_trxn eft
        ON eft.financial_trxn_id = ft.id AND eft.entity_table = 'civicrm_contribution'
      LEFT JOIN civicrm_contribution c   ON c.id   = eft.entity_id
      LEFT JOIN civicrm_contact con      ON con.id = c.contact_id
      WHERE ft.id = %1 LIMIT 1
    ";
    $rows = CRM_Core_DAO::executeQuery($sql, [1 => [$trxnId, 'Integer']])->fetchAll();
    return !empty($rows) ? $rows[0] : NULL;
  }

  /**
   * Apply account correction via reversal + repost.
   *
   * @param int $trxnId
   * @param int $newFromAccountId 0 = no change
   * @param int $newToAccountId 0 = no change
   * @param string $reason
   * @return array
   */
  public static function correctAccounts(int $trxnId, int $newFromAccountId, int $newToAccountId, string $reason) {
    $original = self::getTransaction($trxnId);
    if (!$original) {
      return ['success' => FALSE, 'message' => "Transaction #{$trxnId} not found."];
    }

    $finalFromId = $newFromAccountId ?: (int) $original['from_financial_account_id'];
    $finalToId = $newToAccountId ?: (int) $original['to_financial_account_id'];

    $fromChanged = $newFromAccountId && $newFromAccountId != (int) $original['from_financial_account_id'];
    $toChanged = $newToAccountId && $newToAccountId != (int) $original['to_financial_account_id'];

    if (!$fromChanged && !$toChanged) {
      return ['success' => FALSE, 'message' => 'No changes detected — selected accounts match the original.'];
    }

    $tx = new CRM_Core_Transaction();
    try {
      // Step 1: reversal on old accounts (negative amount)
      $reversalId = self::insertTrxn($original, (int) $original['from_financial_account_id'], (int) $original['to_financial_account_id'], -1);

      // Step 2: correction on new accounts (positive amount)
      $correctionId = self::insertTrxn($original, $finalFromId, $finalToId, 1);

      // Step 3: link to contribution
      if (!empty($original['contribution_id'])) {
        $cid = (int) $original['contribution_id'];
        self::linkToContribution($reversalId, $cid, -1 * (float) $original['total_amount']);
        self::linkToContribution($correctionId, $cid, (float) $original['total_amount']);
      }

      // Step 4: log
      self::logCorrection($trxnId, $reversalId, $correctionId, $original, $finalFromId, $finalToId, $reason);

      $tx->commit();
      return [
        'success' => TRUE,
        'reversal_id' => $reversalId,
        'correction_id' => $correctionId,
        'message' => "Success. Reversal #{$reversalId} and Correction #{$correctionId} created.",
      ];
    }
    catch (Exception $e) {
      $tx->rollback();
      return ['success' => FALSE, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  private static function insertTrxn(array $o, int $fromId, int $toId, int $sign) {
    $sql = "
      INSERT INTO civicrm_financial_trxn
        (from_financial_account_id, to_financial_account_id, trxn_date,
         total_amount, fee_amount, net_amount, currency, is_payment,
         trxn_id, status_id, payment_instrument_id, check_number)
      VALUES (%1,%2,NOW(),%3,%4,%5,%6,%7,%8,%9,%10,%11)
    ";
    $prefix = $sign < 0 ? 'REVERSAL-' : 'CORRECTION-';
    CRM_Core_DAO::executeQuery($sql, [
      1 => [$fromId, 'Integer'],
      2 => [$toId, 'Integer'],
      3 => [$sign * (float) $o['total_amount'], 'Float'],
      4 => [$sign * (float) ($o['fee_amount'] ?? 0), 'Float'],
      5 => [$sign * (float) ($o['net_amount'] ?? 0), 'Float'],
      6 => [$o['currency'] ?? 'USD', 'String'],
      7 => [(int) ($o['is_payment'] ?? 0), 'Integer'],
      8 => [$prefix . ($o['processor_ref'] ?? $o['id']), 'String'],
      9 => [(int) ($o['status_id'] ?? 1), 'Integer'],
      10 => [(int) ($o['payment_instrument_id'] ?? 0), 'Integer'],
      11 => [$o['check_number'] ?? '', 'String'],
    ]);
    return (int) CRM_Core_DAO::singleValueQuery('SELECT LAST_INSERT_ID()');
  }

  private static function linkToContribution(int $trxnId, int $cid, float $amount) {
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_entity_financial_trxn (entity_table, entity_id, financial_trxn_id, amount)
       VALUES ('civicrm_contribution', %1, %2, %3)",
      [1 => [$cid, 'Integer'], 2 => [$trxnId, 'Integer'], 3 => [$amount, 'Float']]
    );
  }

  private static function logCorrection(int $origId, int $revId, int $corId, array $o, int $newFrom, int $newTo, string $reason) {
    $userId = CRM_Core_Session::singleton()->get('userID');
    $data = json_encode([
      'action' => 'account_correction',
      'original' => $origId,
      'reversal' => $revId,
      'correction' => $corId,
      'old_from' => $o['from_financial_account_id'],
      'old_to' => $o['to_financial_account_id'],
      'new_from' => $newFrom,
      'new_to' => $newTo,
      'reason' => $reason,
      'by' => $userId,
      'at' => date('Y-m-d H:i:s'),
    ]);
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_log (entity_table, entity_id, data, modified_id, modified_date)
       VALUES ('civicrm_financial_trxn', %1, %2, %3, NOW())",
      [1 => [$origId, 'Integer'], 2 => [$data, 'String'], 3 => [(int) $userId, 'Integer']]
    );
  }

  /**
   * Get correction history for a transaction.
   */
  public static function getCorrectionHistory(int $trxnId) {
    $sql = "
      SELECT cl.data, cl.modified_date, con.display_name AS modified_by
      FROM civicrm_log cl
      LEFT JOIN civicrm_contact con ON con.id = cl.modified_id
      WHERE cl.entity_table = 'civicrm_financial_trxn' AND cl.entity_id = %1
      ORDER BY cl.modified_date DESC
    ";
    $rows = CRM_Core_DAO::executeQuery($sql, [1 => [$trxnId, 'Integer']])->fetchAll();
    foreach ($rows as &$row) {
      $row['data'] = json_decode($row['data'], TRUE);
    }
    return $rows;
  }

  /**
   * Search financial transactions.
   */
  public static function searchTransactions(array $params = []) {
    $where = ['1=1'];
    $qp = [];
    $i = 1;
    if (!empty($params['contribution_id'])) {
      $where[] = "eft.entity_table='civicrm_contribution' AND eft.entity_id=%{$i}";
      $qp[$i++] = [(int) $params['contribution_id'], 'Integer'];
    }
    if (!empty($params['trxn_id'])) {
      $where[] = "ft.id=%{$i}";
      $qp[$i++] = [(int) $params['trxn_id'], 'Integer'];
    }
    if (!empty($params['date_from'])) {
      $where[] = "ft.trxn_date>=%{$i}";
      $qp[$i++] = [$params['date_from'] . ' 00:00:00', 'String'];
    }
    if (!empty($params['date_to'])) {
      $where[] = "ft.trxn_date<=%{$i}";
      $qp[$i++] = [$params['date_to'] . ' 23:59:59', 'String'];
    }
    $limit = (int) ($params['limit'] ?? 25);
    $offset = (int) ($params['offset'] ?? 0);
    $sql = "
      SELECT DISTINCT ft.id, ft.total_amount, ft.currency, ft.trxn_date,
             ft.is_payment, ft.trxn_id AS processor_ref,
             fa_from.name AS from_account, fa_to.name AS to_account,
             con.display_name AS contact_name, c.id AS contribution_id
      FROM civicrm_financial_trxn ft
      LEFT JOIN civicrm_financial_account fa_from ON fa_from.id=ft.from_financial_account_id
      LEFT JOIN civicrm_financial_account fa_to   ON fa_to.id=ft.to_financial_account_id
      LEFT JOIN civicrm_entity_financial_trxn eft ON eft.financial_trxn_id=ft.id
      LEFT JOIN civicrm_contribution c ON c.id=eft.entity_id AND eft.entity_table='civicrm_contribution'
      LEFT JOIN civicrm_contact con ON con.id=c.contact_id
      WHERE " . implode(' AND ', $where) . "
      ORDER BY ft.trxn_date DESC
      LIMIT {$limit} OFFSET {$offset}
    ";
    return CRM_Core_DAO::executeQuery($sql, $qp)->fetchAll();
  }

}
