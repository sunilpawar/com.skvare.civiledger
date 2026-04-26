<?php

/**
 * CiviLedger - Feature 2: Financial Chain Repair Tool
 *
 * Automatically reconstructs missing financial chain records for a contribution.
 * Handles:
 *   - Creating missing civicrm_financial_item rows from line items
 *   - Creating missing civicrm_entity_financial_trxn rows for both
 *     entity_table=civicrm_contribution and entity_table=civicrm_financial_item
 *
 * @package  com.skvare.civiledger
 */
class CRM_Civiledger_BAO_ChainRepair {

  /**
   * Repair all broken chains for a single contribution.
   *
   * @param int $contributionId
   * @return array  Log of actions taken
   */
  public static function repairContribution(int $contributionId): array {
    $log = [];

    // Load contribution
    $contribution = self::loadContribution($contributionId);
    if (!$contribution) {
      return [['error' => "Contribution #$contributionId not found."]];
    }

    $log[] = ['info' => "Starting repair for Contribution #$contributionId (Amount: {$contribution['total_amount']} {$contribution['currency']})"];

    // Step 1: Ensure line items exist
    $lineItems = self::getLineItems($contributionId);
    if (empty($lineItems)) {
      $log[] = ['warning' => "No line items found. Creating default line item from contribution."];
      $lineItems = self::createDefaultLineItem($contribution);
      $log[] = ['fixed' => "Created default line item (ID: {$lineItems[0]['id']})"];
    }

    // Step 2: Ensure financial_item exists for each line item
    foreach ($lineItems as $lineItem) {
      $fiResult = self::ensureFinancialItem($lineItem, $contribution);
      $log = array_merge($log, $fiResult['log']);
    }

    // Step 3: Ensure financial_trxn exists for the contribution
    $trxnResult = self::ensureFinancialTrxn($contribution);
    $log = array_merge($log, $trxnResult['log']);
    $financialTrxnId = $trxnResult['financial_trxn_id'];

    if ($financialTrxnId) {
      // Step 4: Ensure entity_financial_trxn exists for contribution
      $eftContrib = self::ensureEntityFinancialTrxn(
        'civicrm_contribution', $contributionId, $financialTrxnId,
        (float) $contribution['total_amount'], $log
      );
      $log = array_merge($log, $eftContrib);

      // Step 5: Ensure entity_financial_trxn exists for each financial_item
      $financialItems = self::getFinancialItems($contributionId);
      foreach ($financialItems as $fi) {
        $eftFi = self::ensureEntityFinancialTrxn(
          'civicrm_financial_item', (int) $fi['id'], $financialTrxnId,
          (float) $fi['amount'], $log
        );
        $log = array_merge($log, $eftFi);
      }
    }

    $log[] = ['info' => "Repair complete for Contribution #$contributionId"];

    $fixedCount = count(array_filter($log, fn($l) => isset($l['fixed'])));
    $errorCount = count(array_filter($log, fn($l) => isset($l['error'])));

    // Detailed per-step repair log
    self::saveRepairLog($contributionId, $log);

    // Central hash-chained audit log
    CRM_Civiledger_BAO_AuditLog::record(
      CRM_Civiledger_BAO_AuditLog::EVENT_REPAIR,
      'contribution',
      $contributionId,
      [
        'fixed'   => $fixedCount,
        'skipped' => count(array_filter($log, fn($l) => isset($l['skip']))),
        'warning' => count(array_filter($log, fn($l) => isset($l['warning']))),
        'error'   => $errorCount,
      ]
    );

    return $log;
  }

  /**
   * Batch repair multiple contributions.
   *
   * @param array $contributionIds
   * @return array  Summary results per contribution
   */
  public static function repairBatch(array $contributionIds): array {
    $results = [];
    foreach ($contributionIds as $id) {
      $log = self::repairContribution((int) $id);
      $results[$id] = [
        'log' => $log,
        'fixed_count' => count(array_filter($log, fn($l) => isset($l['fixed']))),
        'error_count' => count(array_filter($log, fn($l) => isset($l['error']))),
      ];
    }
    return $results;
  }

  // -----------------------------------------------------------------------
  // Private helpers
  // -----------------------------------------------------------------------

  private static function loadContribution(int $id): ?array {
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT c.*, ft.name AS financial_type_name
       FROM civicrm_contribution c
       LEFT JOIN civicrm_financial_type ft ON ft.id = c.financial_type_id
       WHERE c.id = %1",
      [1 => [$id, 'Integer']]
    );
    if ($dao->fetch()) {
      return $dao->toArray();
    }
    return NULL;
  }

  private static function getLineItems(int $contributionId): array {
    $rows = [];
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT * FROM civicrm_line_item WHERE contribution_id = %1",
      [1 => [$contributionId, 'Integer']]
    );
    while ($dao->fetch()) {
      $rows[] = $dao->toArray();
    }
    return $rows;
  }

  private static function createDefaultLineItem(array $contribution): array {
    // Minimal line item derived from the contribution itself
    $params = [
      'entity_table' => 'civicrm_contribution',
      'entity_id' => $contribution['id'],
      'contribution_id' => $contribution['id'],
      'price_field_id' => NULL,
      'label' => 'Contribution',
      'qty' => 1,
      'unit_price' => $contribution['total_amount'],
      'line_total' => $contribution['total_amount'],
      'financial_type_id' => $contribution['financial_type_id'],
    ];

    $lineItem = new CRM_Price_DAO_LineItem();
    $lineItem->copyValues($params);
    $lineItem->save();

    return [['id' => $lineItem->id] + $params];
  }

  private static function ensureFinancialItem(array $lineItem, array $contribution): array {
    $log = [];
    $lineItemId = (int) $lineItem['id'];

    // Check if financial_item already exists
    $existing = CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_financial_item
       WHERE entity_table = 'civicrm_line_item' AND entity_id = %1",
      [1 => [$lineItemId, 'Integer']]
    );

    if ($existing > 0) {
      $log[] = ['skip' => "financial_item already exists for line_item #$lineItemId"];
      return ['log' => $log];
    }

    // Get income account for this financial type
    $incomeAccountId = self::getIncomeAccount((int) $lineItem['financial_type_id']);
    if (!$incomeAccountId) {
      $log[] = ['warning' => "Could not find income account for financial_type_id {$lineItem['financial_type_id']}. Using default."];
      $incomeAccountId = self::getDefaultIncomeAccount();
    }

    $fiParams = [
      'created_date' => date('Y-m-d H:i:s'),
      'transaction_date' => $contribution['receive_date'] ?? date('Y-m-d H:i:s'),
      'contact_id' => $contribution['contact_id'],
      'description' => $lineItem['label'] ?? 'Contribution Line Item',
      'amount' => $lineItem['line_total'],
      'currency' => $contribution['currency'],
      'financial_account_id' => $incomeAccountId,
      'status_id' => 1, // Paid
      'entity_table' => 'civicrm_line_item',
      'entity_id' => $lineItemId,
    ];

    $fi = new CRM_Financial_DAO_FinancialItem();
    $fi->copyValues($fiParams);
    $fi->save();

    $log[] = ['fixed' => "Created financial_item #$fi->id for line_item #$lineItemId (Amount: {$lineItem['line_total']})"];

    return ['log' => $log];
  }

  private static function ensureFinancialTrxn(array $contribution): array {
    $log = [];

    // Check if a financial_trxn already exists linked to this contribution
    $existingTrxnId = CRM_Core_DAO::singleValueQuery(
      "SELECT eft.financial_trxn_id
       FROM civicrm_entity_financial_trxn eft
       WHERE eft.entity_table = 'civicrm_contribution' AND eft.entity_id = %1
       LIMIT 1",
      [1 => [(int) $contribution['id'], 'Integer']]
    );

    if ($existingTrxnId) {
      $log[] = ['skip' => "financial_trxn already linked to contribution #$contribution[id] (trxn_id: $existingTrxnId)"];
      return ['log' => $log, 'financial_trxn_id' => (int) $existingTrxnId];
    }

    // Also check by trxn_id match
    if (!empty($contribution['trxn_id'])) {
      $existingTrxnId = CRM_Core_DAO::singleValueQuery(
        "SELECT id FROM civicrm_financial_trxn WHERE trxn_id = %1 LIMIT 1",
        [1 => [$contribution['trxn_id'], 'String']]
      );
    }

    if ($existingTrxnId) {
      $log[] = ['info' => "Found existing financial_trxn #$existingTrxnId by trxn_id match"];
      return ['log' => $log, 'financial_trxn_id' => (int) $existingTrxnId];
    }

    // No trxn found — create one
    $toAccount = self::getPaymentAccount($contribution);
    $fromAccount = self::getARAccount((int) $contribution['financial_type_id']);

    $ftParams = [
      'from_financial_account_id' => $fromAccount,
      'to_financial_account_id' => $toAccount,
      'trxn_date' => $contribution['receive_date'] ?? date('Y-m-d H:i:s'),
      'total_amount' => $contribution['total_amount'],
      'fee_amount' => $contribution['fee_amount'] ?? 0,
      'net_amount' => $contribution['net_amount'] ?? $contribution['total_amount'],
      'currency' => $contribution['currency'],
      'is_payment' => 1,
      'trxn_id' => $contribution['trxn_id'],
      'status_id' => 1, // Completed
      'payment_instrument_id' => $contribution['payment_instrument_id'],
    ];

    $ft = new CRM_Financial_DAO_FinancialTrxn();
    $ft->copyValues($ftParams);
    $ft->save();

    $log[] = ['fixed' => "Created financial_trxn #$ft->id for contribution #$contribution[id]"];

    return ['log' => $log, 'financial_trxn_id' => (int) $ft->id];
  }

  private static function ensureEntityFinancialTrxn(
    string $entityTable,
    int    $entityId,
    int    $financialTrxnId,
    float  $amount,
    array  &$log
  ): array {
    $innerLog = [];

    $existing = CRM_Core_DAO::singleValueQuery(
      "SELECT id FROM civicrm_entity_financial_trxn
       WHERE entity_table = %1 AND entity_id = %2 AND financial_trxn_id = %3",
      [
        1 => [$entityTable, 'String'],
        2 => [$entityId, 'Integer'],
        3 => [$financialTrxnId, 'Integer'],
      ]
    );

    if ($existing) {
      $innerLog[] = ['skip' => "entity_financial_trxn already exists for $entityTable #$entityId → trxn #$financialTrxnId"];
      return $innerLog;
    }

    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_entity_financial_trxn
         (entity_table, entity_id, financial_trxn_id, amount)
       VALUES (%1, %2, %3, %4)",
      [
        1 => [$entityTable, 'String'],
        2 => [$entityId, 'Integer'],
        3 => [$financialTrxnId, 'Integer'],
        4 => [$amount, 'Float'],
      ]
    );

    $innerLog[] = ['fixed' => "Created entity_financial_trxn: $entityTable #$entityId → trxn #$financialTrxnId (Amount: $amount)"];
    return $innerLog;
  }

  private static function getIncomeAccount(int $financialTypeId): ?int {
    // account_relationship option value for "Income Account is" = 1
    return (int) CRM_Core_DAO::singleValueQuery(
      "SELECT financial_account_id
       FROM civicrm_entity_financial_account
       WHERE entity_table = 'civicrm_financial_type'
         AND entity_id = %1
         AND account_relationship = 1",
      [1 => [$financialTypeId, 'Integer']]
    ) ?: NULL;
  }

  private static function getARAccount(int $financialTypeId): ?int {
    // account_relationship for "Accounts Receivable Account is" = 3
    return (int) CRM_Core_DAO::singleValueQuery(
      "SELECT financial_account_id
       FROM civicrm_entity_financial_account
       WHERE entity_table = 'civicrm_financial_type'
         AND entity_id = %1
         AND account_relationship = 3",
      [1 => [$financialTypeId, 'Integer']]
    ) ?: NULL;
  }

  private static function getPaymentAccount(array $contribution): ?int {
    // Try to get from payment_instrument
    if (!empty($contribution['payment_instrument_id'])) {
      $accountId = CRM_Core_DAO::singleValueQuery(
        "SELECT efa.financial_account_id
         FROM civicrm_entity_financial_account efa
         WHERE efa.entity_table = 'civicrm_option_value'
           AND efa.entity_id = %1
           AND efa.account_relationship = 7",
        [1 => [(int) $contribution['payment_instrument_id'], 'Integer']]
      );
      if ($accountId) {
        return (int) $accountId;
      }
    }
    // Fallback: first active asset account
    return self::getDefaultAssetAccount();
  }

  private static function getDefaultIncomeAccount(): ?int {
    return (int) CRM_Core_DAO::singleValueQuery(
      "SELECT id FROM civicrm_financial_account
       WHERE financial_account_type_id = 4 AND is_active = 1
       ORDER BY is_default DESC LIMIT 1"
    ) ?: NULL;
  }

  private static function getDefaultAssetAccount(): ?int {
    return (int) CRM_Core_DAO::singleValueQuery(
      "SELECT id FROM civicrm_financial_account
       WHERE financial_account_type_id = 3 AND is_active = 1
       ORDER BY is_default DESC LIMIT 1"
    ) ?: NULL;
  }

  private static function saveRepairLog(int $contributionId, array $log): void {
    if (empty($log)) {
      return;
    }
    $userId = (int) CRM_Core_Session::getLoggedInContactID() ?: NULL;
    $now = date('Y-m-d H:i:s');

    foreach ($log as $entry) {
      $action  = key($entry);
      $message = current($entry);
      $userSql = $userId ? (int) $userId : 'NULL';
      CRM_Core_DAO::executeQuery(
        "INSERT INTO civicrm_civiledger_repair_log
           (contribution_id, action, message, repaired_by, repaired_at)
         VALUES (%1, %2, %3, {$userSql}, %4)",
        [
          1 => [$contributionId, 'Integer'],
          2 => [$action, 'String'],
          3 => [$message, 'String'],
          4 => [$now, 'String'],
        ]
      );
    }
  }

  private static function getFinancialItems(int $contributionId): array {
    $rows = [];
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT fi.*
       FROM civicrm_financial_item fi
       INNER JOIN civicrm_line_item li
         ON li.id = fi.entity_id AND fi.entity_table = 'civicrm_line_item'
       WHERE li.contribution_id = %1",
      [1 => [$contributionId, 'Integer']]
    );
    while ($dao->fetch()) {
      $rows[] = $dao->toArray();
    }
    return $rows;
  }

}
