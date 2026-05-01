<?php
/**
 * CiviLedger — Financial Type Account Mapping Validator
 *
 * Validates that every financial type has the correct account relationships
 * configured in civicrm_entity_financial_account. Surfaces:
 *   - Missing required relationships on active financial types
 *   - Relationships mapped to the wrong account type
 *   - Relationships mapped to inactive accounts
 *   - AR relationships mapped to non-AR asset accounts
 *   - Inactive financial types that still carry account mappings
 *   - Duplicate relationship entries on the same financial type
 *
 * All relationship and account-type identifiers are resolved dynamically from
 * civicrm_option_value using the stable `name` column so the validator works
 * correctly regardless of the numeric `value` in any given installation.
 */
class CRM_Civiledger_BAO_FinancialTypeMapping {

  /**
   * Canonical mapping: account_relationship.name → financial_account_type.name
   *
   * These are universal double-entry accounting rules, not customer-configurable.
   * Numeric option values are resolved at runtime via getExpectedTypesMap().
   */
  const RELATIONSHIP_TYPE_NAMES = [
    'Income Account is'                => 'Revenue',
    'Credit/Contra Revenue Account is' => 'Revenue',
    'Accounts Receivable Account is'   => 'Asset',
    'Credit Liability Account is'      => 'Liability',
    'Expense Account is'               => 'Expenses',
    'Asset Account is'                 => 'Asset',
    'Cost of Sales Account is'         => 'Cost of Sales',
    'Premiums Inventory Account is'    => 'Asset',
    'Discounts Account is'             => 'Revenue',
    'Sales Tax Account is'             => 'Liability',
    'Chargeback Account is'            => 'Asset',
    'Deferred Revenue Account is'      => 'Liability',
  ];

  /**
   * Relationship names that every active financial type MUST have configured.
   */
  const REQUIRED_RELATIONSHIP_NAMES = [
    'Income Account is',
    'Accounts Receivable Account is',
    'Expense Account is',
    'Cost of Sales Account is',
  ];

  /**
   * Double-entry normal balance per account type name.
   * 'debit'  = increases on the debit side  (Assets, Expenses, Cost of Sales)
   * 'credit' = increases on the credit side (Liabilities, Revenue)
   */
  const ACCOUNT_TYPE_NORMAL_BALANCE = [
    'Asset'         => 'debit',
    'Liability'     => 'credit',
    'Revenue'       => 'credit',
    'Cost of Sales' => 'debit',
    'Expenses'      => 'debit',
  ];

  // -------------------------------------------------------------------------
  // Runtime-resolved maps (built once per request, keyed by numeric value)
  // -------------------------------------------------------------------------

  /**
   * Returns [ account_relationship.value => financial_account_type.value ]
   * resolved from the customer's option tables.
   */
  private static function getExpectedTypesMap(): array {
    static $map = NULL;
    if ($map !== NULL) {
      return $map;
    }

    $relNameToValue  = self::getNameToValueMap('account_relationship');
    $typeNameToValue = self::getNameToValueMap('financial_account_type');

    $map = [];
    foreach (self::RELATIONSHIP_TYPE_NAMES as $relName => $typeName) {
      $relValue  = $relNameToValue[$relName]  ?? NULL;
      $typeValue = $typeNameToValue[$typeName] ?? NULL;
      if ($relValue !== NULL && $typeValue !== NULL) {
        $map[(int) $relValue] = (int) $typeValue;
      }
    }
    return $map;
  }

  /**
   * Returns [ account_relationship.value => financial_account_type.value ]
   * for required relationships only.
   */
  private static function getRequiredMap(): array {
    static $map = NULL;
    if ($map !== NULL) {
      return $map;
    }

    $relNameToValue  = self::getNameToValueMap('account_relationship');
    $typeNameToValue = self::getNameToValueMap('financial_account_type');

    $map = [];
    foreach (self::REQUIRED_RELATIONSHIP_NAMES as $relName) {
      $typeName  = self::RELATIONSHIP_TYPE_NAMES[$relName] ?? NULL;
      $relValue  = $relNameToValue[$relName]  ?? NULL;
      $typeValue = $typeName ? ($typeNameToValue[$typeName] ?? NULL) : NULL;
      if ($relValue !== NULL && $typeValue !== NULL) {
        $map[(int) $relValue] = (int) $typeValue;
      }
    }
    return $map;
  }

  /**
   * Returns the numeric option value for the "Accounts Receivable Account is"
   * relationship, used for the AR account-type-code check.
   */
  private static function getArRelationshipValue(): ?int {
    static $val = FALSE;
    if ($val !== FALSE) {
      return $val;
    }
    $map = self::getNameToValueMap('account_relationship');
    $val = isset($map['Accounts Receivable Account is'])
      ? (int) $map['Accounts Receivable Account is']
      : NULL;
    return $val;
  }

  /**
   * Returns [ financial_account_type.value => 'debit'|'credit' ]
   * resolved from the customer's option tables.
   */
  private static function getNormalBalanceMap(): array {
    static $map = NULL;
    if ($map !== NULL) {
      return $map;
    }
    $typeNameToValue = self::getNameToValueMap('financial_account_type');
    $map = [];
    foreach (self::ACCOUNT_TYPE_NORMAL_BALANCE as $typeName => $balance) {
      $typeValue = $typeNameToValue[$typeName] ?? NULL;
      if ($typeValue !== NULL) {
        $map[(int) $typeValue] = $balance;
      }
    }
    return $map;
  }

  // -------------------------------------------------------------------------
  // Public API
  // -------------------------------------------------------------------------

  /**
   * Build the full mapping report.
   *
   * @return array  One entry per financial type, sorted active-first then name.
   */
  public static function getMapping(): array {
    $expectedTypes    = self::getExpectedTypesMap();
    $requiredMap      = self::getRequiredMap();
    $arRelValue       = self::getArRelationshipValue();
    $accountTypeNames = self::getValueToLabelMap('financial_account_type');
    $relationshipNames= self::getValueToLabelMap('account_relationship');
    $normalBalanceMap = self::getNormalBalanceMap();

    // All financial types.
    $financialTypes = CRM_Core_DAO::executeQuery("
      SELECT id, name, label, is_active, is_deductible, is_reserved
      FROM   civicrm_financial_type
      ORDER  BY is_active DESC, name ASC
    ")->fetchAll();

    // All EFA rows for financial_type entities with account details.
    $efaRows = CRM_Core_DAO::executeQuery("
      SELECT
        efa.id                        AS efa_id,
        efa.entity_id                 AS financial_type_id,
        efa.account_relationship      AS rel_id,
        efa.financial_account_id      AS account_id,
        fa.name                       AS account_name,
        fa.label                      AS account_label,
        fa.financial_account_type_id  AS account_type_id,
        fa.account_type_code,
        fa.accounting_code,
        fa.is_active                  AS account_is_active,
        fat.label                     AS account_type_label
      FROM civicrm_entity_financial_account efa
      JOIN civicrm_financial_account fa
        ON fa.id = efa.financial_account_id
      LEFT JOIN civicrm_option_value fat
        ON  fat.value = fa.financial_account_type_id
        AND fat.option_group_id = (
              SELECT id FROM civicrm_option_group WHERE name = 'financial_account_type'
            )
      WHERE efa.entity_table = 'civicrm_financial_type'
      ORDER BY efa.entity_id ASC, efa.account_relationship ASC
    ")->fetchAll();

    // Index: financial_type_id → rel_id → [rows]
    $efaIndex = [];
    foreach ($efaRows as $row) {
      $efaIndex[(int) $row['financial_type_id']][(int) $row['rel_id']][] = $row;
    }

    $result = [];
    foreach ($financialTypes as $ft) {
      $ftId       = (int) $ft['id'];
      $isActive   = (bool) $ft['is_active'];
      $ftMappings = $efaIndex[$ftId] ?? [];

      $relationships = [];
      $issues        = [];

      // --- Process existing EFA rows ---
      foreach ($ftMappings as $relId => $rows) {
        $relLabel       = $relationshipNames[$relId] ?? "Relationship {$relId}";
        $expectedTypeId = $expectedTypes[$relId] ?? NULL;

        if (count($rows) > 1) {
          $issues[] = [
            'severity'  => 'error',
            'type'      => 'duplicate_rel',
            'rel_id'    => $relId,
            'rel_label' => $relLabel,
            'message'   => "{$relLabel} is mapped " . count($rows) . " times — only one account should be assigned per relationship.",
          ];
        }

        foreach ($rows as $row) {
          $actualTypeId  = (int) $row['account_type_id'];
          $isWrongType   = $expectedTypeId !== NULL && $actualTypeId !== $expectedTypeId;
          $isInactiveAcc = !(bool) $row['account_is_active'];
          $isWrongArCode = $arRelValue !== NULL
            && $relId === $arRelValue
            && $actualTypeId === (int) ($expectedTypes[$arRelValue] ?? -1)
            && !in_array($row['account_type_code'], ['AR', '', NULL], TRUE);

          $rowStatus = 'ok';
          if ($isWrongType) {
            $rowStatus = 'error';
            $issues[]  = [
              'severity'  => 'error',
              'type'      => 'wrong_type',
              'rel_id'    => $relId,
              'rel_label' => $relLabel,
              'message'   => "{$relLabel} maps to \"{$row['account_name']}\" ({$row['account_type_label']}) but must map to a "
                . ($accountTypeNames[$expectedTypeId] ?? 'Unknown') . " account.",
            ];
          }
          if ($isInactiveAcc) {
            $rowStatus = $rowStatus !== 'ok' ? $rowStatus : 'warning';
            $issues[]  = [
              'severity'  => 'warning',
              'type'      => 'inactive_account',
              'rel_id'    => $relId,
              'rel_label' => $relLabel,
              'message'   => "{$relLabel} maps to \"{$row['account_name']}\" which is currently inactive.",
            ];
          }
          if ($isWrongArCode) {
            $rowStatus = $rowStatus !== 'ok' ? $rowStatus : 'warning';
            $issues[]  = [
              'severity'  => 'warning',
              'type'      => 'wrong_ar_code',
              'rel_id'    => $relId,
              'rel_label' => $relLabel,
              'message'   => "Accounts Receivable maps to \"{$row['account_name']}\" (code: {$row['account_type_code']}) — expected an account with code AR.",
            ];
          }

          $relationships[] = [
            'efa_id'              => (int) $row['efa_id'],
            'rel_id'              => $relId,
            'rel_label'           => $relLabel,
            'is_required'         => isset($requiredMap[$relId]),
            'account_id'          => (int) $row['account_id'],
            'account_name'        => $row['account_name'],
            'account_type_id'     => $actualTypeId,
            'account_type_label'  => $row['account_type_label'] ?? '—',
            'account_type_code'   => $row['account_type_code'] ?? '',
            'expected_type_id'    => $expectedTypeId,
            'expected_type_label' => $expectedTypeId ? ($accountTypeNames[$expectedTypeId] ?? 'Unknown') : NULL,
            'accounting_code'     => $row['accounting_code'] ?? '',
            'account_is_active'   => (bool) $row['account_is_active'],
            'normal_balance'      => $normalBalanceMap[$actualTypeId] ?? NULL,
            'status'              => $rowStatus,
          ];
        }
      }

      // --- Placeholder rows for missing required relationships (active types) ---
      if ($isActive) {
        $mappedRelIds = array_column($relationships, 'rel_id');
        foreach ($requiredMap as $relId => $expectedTypeId) {
          if (!in_array($relId, $mappedRelIds, TRUE)) {
            $relLabel = $relationshipNames[$relId] ?? "Relationship {$relId}";
            $issues[] = [
              'severity'  => 'error',
              'type'      => 'missing',
              'rel_id'    => $relId,
              'rel_label' => $relLabel,
              'message'   => "Missing required relationship: {$relLabel} (must map to a "
                . ($accountTypeNames[$expectedTypeId] ?? 'Unknown') . " account).",
            ];
            $relationships[] = [
              'efa_id'              => NULL,
              'rel_id'              => $relId,
              'rel_label'           => $relLabel,
              'is_required'         => TRUE,
              'account_id'          => NULL,
              'account_name'        => NULL,
              'account_type_id'     => NULL,
              'account_type_label'  => NULL,
              'account_type_code'   => NULL,
              'expected_type_id'    => $expectedTypeId,
              'expected_type_label' => $accountTypeNames[$expectedTypeId] ?? 'Unknown',
              'accounting_code'     => '',
              'account_is_active'   => NULL,
              'normal_balance'      => $normalBalanceMap[$expectedTypeId] ?? NULL,
              'status'              => 'missing',
            ];
          }
        }
        usort($relationships, fn($a, $b) => $a['rel_id'] - $b['rel_id']);
      }

      // --- Inactive type still carrying mappings ---
      if (!$isActive && !empty($ftMappings)) {
        $count    = array_sum(array_map('count', $ftMappings));
        $issues[] = [
          'severity' => 'warning',
          'type'     => 'stale_inactive',
          'rel_id'   => NULL,
          'rel_label'=> NULL,
          'message'  => "This financial type is inactive but still has {$count} account relationship mapping(s). Consider removing them.",
        ];
      }

      $hasError   = !empty(array_filter($issues, fn($i) => $i['severity'] === 'error'));
      $hasWarning = !empty(array_filter($issues, fn($i) => $i['severity'] === 'warning'));
      $status     = $hasError ? 'error' : ($hasWarning ? 'warning' : 'ok');

      $result[] = [
        'id'            => $ftId,
        'name'          => $ft['name'],
        'label'         => $ft['label'],
        'is_active'     => $isActive,
        'is_deductible' => (bool) $ft['is_deductible'],
        'is_reserved'   => (bool) $ft['is_reserved'],
        'relationships' => $relationships,
        'issues'        => $issues,
        'status'        => $status,
        'issue_count'   => count($issues),
        'edit_url'      => CRM_Utils_System::url(
          'civicrm/admin/financial/financialType/edit',
          "action=update&id={$ftId}&reset=1"
        ),
      ];
    }

    return $result;
  }

  /**
   * Returns account type entries with their normal balance for the legend block.
   * Order matches ACCOUNT_TYPE_NORMAL_BALANCE declaration.
   */
  public static function getAccountTypesLegend(): array {
    $typeNameToValue  = self::getNameToValueMap('financial_account_type');
    $typeValueToLabel = self::getValueToLabelMap('financial_account_type');
    $result = [];
    foreach (self::ACCOUNT_TYPE_NORMAL_BALANCE as $typeName => $balance) {
      $typeValue = $typeNameToValue[$typeName] ?? NULL;
      $result[] = [
        'name'           => $typeName,
        'label'          => $typeValue ? ($typeValueToLabel[(int) $typeValue] ?? $typeName) : $typeName,
        'normal_balance' => $balance,
      ];
    }
    return $result;
  }

  /**
   * Aggregate summary counts for the banner.
   */
  public static function getSummary(): array {
    $mapping = self::getMapping();
    return [
      'total'    => count($mapping),
      'active'   => count(array_filter($mapping, fn($t) => $t['is_active'])),
      'inactive' => count(array_filter($mapping, fn($t) => !$t['is_active'])),
      'errors'   => count(array_filter($mapping, fn($t) => $t['status'] === 'error')),
      'warnings' => count(array_filter($mapping, fn($t) => $t['status'] === 'warning')),
      'ok'       => count(array_filter($mapping, fn($t) => $t['status'] === 'ok')),
    ];
  }

  // -------------------------------------------------------------------------
  // Option table helpers
  // -------------------------------------------------------------------------

  /**
   * Returns [ name => value ] for an option group.
   * Used to resolve stable name strings to the customer's numeric values.
   */
  private static function getNameToValueMap(string $groupName): array {
    static $cache = [];
    if (isset($cache[$groupName])) {
      return $cache[$groupName];
    }
    $rows = CRM_Core_DAO::executeQuery("
      SELECT ov.name, ov.value
      FROM   civicrm_option_value ov
      JOIN   civicrm_option_group og ON og.id = ov.option_group_id
      WHERE  og.name = %1
    ", [1 => [$groupName, 'String']])->fetchAll();
    $map = [];
    foreach ($rows as $r) {
      $map[$r['name']] = $r['value'];
    }
    $cache[$groupName] = $map;
    return $map;
  }

  /**
   * Returns [ value => label ] for an option group.
   * Used for human-readable display.
   */
  private static function getValueToLabelMap(string $groupName): array {
    static $cache = [];
    if (isset($cache[$groupName])) {
      return $cache[$groupName];
    }
    $rows = CRM_Core_DAO::executeQuery("
      SELECT ov.value, ov.label
      FROM   civicrm_option_value ov
      JOIN   civicrm_option_group og ON og.id = ov.option_group_id
      WHERE  og.name = %1
    ", [1 => [$groupName, 'String']])->fetchAll();
    $map = [];
    foreach ($rows as $r) {
      $map[(int) $r['value']] = $r['label'];
    }
    $cache[$groupName] = $map;
    return $map;
  }

}
