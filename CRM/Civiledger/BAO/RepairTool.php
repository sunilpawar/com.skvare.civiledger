<?php
/**
 * BAO: Chain Repair Tool
 * Rebuilds missing financial chain rows for broken contributions.
 */
class CRM_Civiledger_BAO_RepairTool {

  /**
   * Repair a single contribution's financial chain.
   *
   * @param int $contributionId
   * @return array  ['success' => bool, 'actions' => [], 'errors' => []]
   */
  public static function repairContribution(int $contributionId): array {
    $result = ['success' => TRUE, 'actions' => [], 'errors' => []];

    try {
      // Load the contribution
      $contribution = CRM_Core_DAO::executeQuery(
        "SELECT * FROM civicrm_contribution WHERE id = %1",
        [1 => [$contributionId, 'Integer']]
      )->fetchRow(DB_FETCHMODE_ASSOC);

      if (!$contribution) {
        throw new Exception("Contribution #{$contributionId} not found.");
      }

      // Step 1: Ensure line items exist
      self::ensureLineItems($contribution, $result);

      // Step 2: Ensure financial_item exists for each line item
      self::ensureFinancialItems($contribution, $result);

      // Step 3: Ensure financial_trxn exists
      $trxnId = self::ensureFinancialTrxn($contribution, $result);

      // Step 4: Ensure entity_financial_trxn (contribution link)
      self::ensureContributionTrxnLink($contribution, $trxnId, $result);

      // Step 5: Ensure entity_financial_trxn (financial_item links)
      self::ensureFinancialItemTrxnLinks($contribution, $trxnId, $result);

      // Log the repair
      self::logRepair($contributionId, $result['actions']);

    }
    catch (Exception $e) {
      $result['success'] = FALSE;
      $result['errors'][] = $e->getMessage();
    }

    return $result;
  }

  /**
   * Repair multiple contributions at once.
   *
   * @param array $contributionIds
   * @return array
   */
  public static function repairBatch(array $contributionIds): array {
    $results = ['total' => count($contributionIds), 'repaired' => 0, 'failed' => 0, 'details' => []];
    foreach ($contributionIds as $id) {
      $r = self::repairContribution((int) $id);
      if ($r['success']) {
        $results['repaired']++;
      }
      else {
        $results['failed']++;
      }
      $results['details'][$id] = $r;
    }
    return $results;
  }

  /**
   * Step 1: Create missing line items from contribution.
   */
  private static function ensureLineItems(array $contribution, array &$result): void {
    $existing = CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_line_item WHERE contribution_id = %1",
      [1 => [$contribution['id'], 'Integer']]
    );

    if ($existing > 0) {
      return;
    }

    // Create a basic line item from the contribution
    $sql = "
      INSERT INTO civicrm_line_item
        (entity_table, entity_id, contribution_id, price_field_id,
         label, qty, unit_price, line_total, financial_type_id)
      VALUES
        ('civicrm_contribution', %1, %1, NULL,
         'Contribution', 1, %2, %2, %3)
    ";
    CRM_Core_DAO::executeQuery($sql, [
      1 => [$contribution['id'], 'Integer'],
      2 => [$contribution['total_amount'], 'Money'],
      3 => [$contribution['financial_type_id'], 'Integer'],
    ]);

    $result['actions'][] = "Created missing line item for contribution #{$contribution['id']}";
  }

  /**
   * Step 2: Create missing financial_item for each line item.
   */
  private static function ensureFinancialItems(array $contribution, array &$result): void {
    // Get line items without financial_items
    $lineItems = CRM_Core_DAO::executeQuery("
      SELECT li.*
      FROM civicrm_line_item li
      LEFT JOIN civicrm_financial_item fi
             ON fi.entity_table = 'civicrm_line_item' AND fi.entity_id = li.id
      WHERE li.contribution_id = %1
        AND fi.id IS NULL
    ", [1 => [$contribution['id'], 'Integer']])->fetchAll();

    foreach ($lineItems as $li) {
      // Get income account for this financial type
      $accountId = CRM_Core_DAO::singleValueQuery("
        SELECT efa.financial_account_id
        FROM civicrm_entity_financial_account efa
        INNER JOIN civicrm_option_value ov
               ON ov.value = efa.account_relationship
              AND ov.name = 'Income Account is'
        WHERE efa.entity_table = 'civicrm_financial_type'
          AND efa.entity_id = %1
        LIMIT 1
      ", [1 => [$li['financial_type_id'], 'Integer']]);

      if (!$accountId) {
        // Fallback to contribution's financial type
        $accountId = CRM_Core_DAO::singleValueQuery("
          SELECT efa.financial_account_id
          FROM civicrm_entity_financial_account efa
          INNER JOIN civicrm_option_value ov
                 ON ov.value = efa.account_relationship
                AND ov.name = 'Income Account is'
          WHERE efa.entity_table = 'civicrm_financial_type'
            AND efa.entity_id = %1
          LIMIT 1
        ", [1 => [$contribution['financial_type_id'], 'Integer']]);
      }

      $receiveDate = $contribution['receive_date'] ?? date('Y-m-d H:i:s');
      $statusId = ($contribution['contribution_status_id'] == 1) ? 1 : 3; // 1=Paid, 3=Unpaid

      CRM_Core_DAO::executeQuery("
        INSERT INTO civicrm_financial_item
          (created_date, transaction_date, contact_id, description,
           amount, currency, financial_account_id, status_id,
           entity_table, entity_id)
        VALUES
          (NOW(), %1, %2, %3, %4, %5, %6, %7, 'civicrm_line_item', %8)
      ", [
        1 => [$receiveDate, 'String'],
        2 => [$contribution['contact_id'], 'Integer'],
        3 => ['Contribution', 'String'],
        4 => [$li['line_total'], 'Money'],
        5 => [$contribution['currency'] ?? 'USD', 'String'],
        6 => [$accountId ?? 1, 'Integer'],
        7 => [$statusId, 'Integer'],
        8 => [$li['id'], 'Integer'],
      ]);

      $result['actions'][] = "Created financial_item for line_item #{$li['id']}";
    }
  }

  /**
   * Step 3: Ensure financial_trxn exists for this contribution.
   * Returns the trxn ID to use for linking.
   */
  private static function ensureFinancialTrxn(array $contribution, array &$result): int {
    // Check if one already exists via entity_financial_trxn
    $existingTrxnId = CRM_Core_DAO::singleValueQuery("
      SELECT eft.financial_trxn_id
      FROM civicrm_entity_financial_trxn eft
      INNER JOIN civicrm_financial_trxn ft ON ft.id = eft.financial_trxn_id
      WHERE eft.entity_table = 'civicrm_contribution'
        AND eft.entity_id = %1
        AND ft.is_payment = 1
      LIMIT 1
    ", [1 => [$contribution['id'], 'Integer']]);

    if ($existingTrxnId) {
      return (int) $existingTrxnId;
    }

    // Get payment instrument account
    $toAccountId = self::getPaymentAccount($contribution);
    // Get AR account
    $fromAccountId = self::getARAccount($contribution['financial_type_id']);

    CRM_Core_DAO::executeQuery("
      INSERT INTO civicrm_financial_trxn
        (from_financial_account_id, to_financial_account_id,
         trxn_date, total_amount, fee_amount, net_amount,
         currency, is_payment, trxn_id, status_id, payment_instrument_id)
      VALUES (%1, %2, %3, %4, %5, %6, %7, 1, %8, %9, %10)
    ", [
      1 => [$fromAccountId, 'Integer'],
      2 => [$toAccountId, 'Integer'],
      3 => [$contribution['receive_date'] ?? date('Y-m-d H:i:s'), 'String'],
      4 => [$contribution['total_amount'], 'Money'],
      5 => [$contribution['fee_amount'] ?? 0, 'Money'],
      6 => [$contribution['net_amount'] ?? $contribution['total_amount'], 'Money'],
      7 => [$contribution['currency'] ?? 'USD', 'String'],
      8 => [$contribution['trxn_id'] ?? '', 'String'],
      9 => [1, 'Integer'], // Completed
      10 => [$contribution['payment_instrument_id'] ?? 1, 'Integer'],
    ]);

    $trxnId = (int) CRM_Core_DAO::singleValueQuery("SELECT LAST_INSERT_ID()");
    $result['actions'][] = "Created financial_trxn #{$trxnId} for contribution #{$contribution['id']}";
    return $trxnId;
  }

  /**
   * Step 4: Create entity_financial_trxn (contribution → trxn link).
   */
  private static function ensureContributionTrxnLink(array $contribution, int $trxnId, array &$result): void {
    $existing = CRM_Core_DAO::singleValueQuery("
      SELECT COUNT(*) FROM civicrm_entity_financial_trxn
      WHERE entity_table = 'civicrm_contribution'
        AND entity_id = %1
        AND financial_trxn_id = %2
    ", [
      1 => [$contribution['id'], 'Integer'],
      2 => [$trxnId, 'Integer'],
    ]);

    if ($existing) {
      return;
    }

    CRM_Core_DAO::executeQuery("
      INSERT INTO civicrm_entity_financial_trxn
        (entity_table, entity_id, financial_trxn_id, amount)
      VALUES ('civicrm_contribution', %1, %2, %3)
    ", [
      1 => [$contribution['id'], 'Integer'],
      2 => [$trxnId, 'Integer'],
      3 => [$contribution['total_amount'], 'Money'],
    ]);

    $result['actions'][] = "Linked contribution #{$contribution['id']} → trxn #{$trxnId}";
  }

  /**
   * Step 5: Create entity_financial_trxn (financial_item → trxn links).
   */
  private static function ensureFinancialItemTrxnLinks(array $contribution, int $trxnId, array &$result): void {
    // Get all financial_items for this contribution's line items
    $financialItems = CRM_Core_DAO::executeQuery("
      SELECT fi.id, fi.amount
      FROM civicrm_financial_item fi
      INNER JOIN civicrm_line_item li
              ON fi.entity_table = 'civicrm_line_item' AND fi.entity_id = li.id
      WHERE li.contribution_id = %1
    ", [1 => [$contribution['id'], 'Integer']])->fetchAll();

    foreach ($financialItems as $fi) {
      $existing = CRM_Core_DAO::singleValueQuery("
        SELECT COUNT(*) FROM civicrm_entity_financial_trxn
        WHERE entity_table = 'civicrm_financial_item'
          AND entity_id = %1
          AND financial_trxn_id = %2
      ", [
        1 => [$fi['id'], 'Integer'],
        2 => [$trxnId, 'Integer'],
      ]);

      if ($existing) {
        continue;
      }

      CRM_Core_DAO::executeQuery("
        INSERT INTO civicrm_entity_financial_trxn
          (entity_table, entity_id, financial_trxn_id, amount)
        VALUES ('civicrm_financial_item', %1, %2, %3)
      ", [
        1 => [$fi['id'], 'Integer'],
        2 => [$trxnId, 'Integer'],
        3 => [$fi['amount'], 'Money'],
      ]);

      $result['actions'][] = "Linked financial_item #{$fi['id']} → trxn #{$trxnId}";
    }
  }

  /**
   * Get the payment/asset account for a contribution.
   */
  private static function getPaymentAccount(array $contribution): int {
    // Try payment instrument account first
    if (!empty($contribution['payment_instrument_id'])) {
      $accountId = CRM_Core_DAO::singleValueQuery("
        SELECT efa.financial_account_id
        FROM civicrm_entity_financial_account efa
        INNER JOIN civicrm_option_value ov
               ON ov.value = efa.account_relationship
              AND ov.name = 'Asset Account is'
        WHERE efa.entity_table = 'civicrm_option_value'
          AND efa.entity_id = %1
        LIMIT 1
      ", [1 => [$contribution['payment_instrument_id'], 'Integer']]);
      if ($accountId) {
        return (int) $accountId;
      }
    }
    // Fallback: first asset account
    return (int) (CRM_Core_DAO::singleValueQuery("
      SELECT id FROM civicrm_financial_account
      WHERE financial_account_type_id = (
        SELECT ov.value FROM civicrm_option_value ov
        INNER JOIN civicrm_option_group og ON og.id = ov.option_group_id
        WHERE og.name = 'financial_account_type' AND ov.name = 'Asset'
        LIMIT 1
      )
      AND is_default = 1
      LIMIT 1
    ") ?? 6);
  }

  /**
   * Get AR (Accounts Receivable) account for a financial type.
   */
  private static function getARAccount(int $financialTypeId): int {
    $accountId = CRM_Core_DAO::singleValueQuery("
      SELECT efa.financial_account_id
      FROM civicrm_entity_financial_account efa
      INNER JOIN civicrm_option_value ov
             ON ov.value = efa.account_relationship
            AND ov.name = 'Accounts Receivable Account is'
      WHERE efa.entity_table = 'civicrm_financial_type'
        AND efa.entity_id = %1
      LIMIT 1
    ", [1 => [$financialTypeId, 'Integer']]);
    return (int) ($accountId ?? 7); // 7 = typical AR account
  }

  /**
   * Log a repair action to the hash-chained audit log.
   */
  public static function logRepair(int $contributionId, array $actions): void {
    CRM_Civiledger_BAO_AuditLog::record(
      CRM_Civiledger_BAO_AuditLog::EVENT_REPAIR,
      'contribution',
      $contributionId,
      ['actions' => $actions]
    );
  }

}
