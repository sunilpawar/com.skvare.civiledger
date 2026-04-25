<?php
/**
 * CiviLedger - Feature 3: Financial Audit Trail
 *
 * Shows the complete financial data chain for any contribution:
 *   Contribution → Line Items → Financial Items → Transactions → Accounts
 */
class CRM_Civiledger_BAO_AuditTrail {

  /**
   * Get the full audit trail for a contribution.
   *
   * @param int $contributionId
   * @return array
   */
  public static function getTrail(int $contributionId) {
    $contribution = self::getContribution($contributionId);
    if (!$contribution) {
      return NULL;
    }

    $trail = [
      'contribution' => $contribution,
      'line_items' => [],
      'trxns' => self::getTransactions($contributionId),
      'health' => self::getChainStatus($contributionId),
      'audit_log' => self::getAuditLog($contributionId),
    ];

    $lineItems = self::getLineItems($contributionId);
    foreach ($lineItems as $li) {
      $li['financial_items'] = self::getFinancialItemsForLineItem($li['id']);
      foreach ($li['financial_items'] as &$fi) {
        $fi['trxn_links'] = self::getTrxnLinksForFinancialItem($fi['id']);
      }
      $trail['line_items'][] = $li;
    }

    return $trail;
  }

  /**
   * Get contribution details.
   */
  private static function getContribution(int $id) {
    $sql = "
      SELECT c.id, c.contact_id, c.total_amount, c.fee_amount, c.net_amount,
             c.currency, c.receive_date, c.contribution_status_id,
             c.trxn_id, c.invoice_id, c.invoice_number,
             c.tax_amount, c.cancel_date, c.cancel_reason,
             c.source,
             con.display_name AS contact_name,
             ft.name AS financial_type_name,
             pi.label AS payment_instrument,
             cs.label AS status_label
      FROM civicrm_contribution c
      LEFT JOIN civicrm_contact con ON con.id = c.contact_id
      LEFT JOIN civicrm_financial_type ft ON ft.id = c.financial_type_id
      LEFT JOIN civicrm_option_value pi
        ON pi.value = c.payment_instrument_id
        AND pi.option_group_id = (
          SELECT id FROM civicrm_option_group WHERE name = 'payment_instrument'
        )
      LEFT JOIN civicrm_option_value cs
        ON cs.value = c.contribution_status_id
        AND cs.option_group_id = (
          SELECT id FROM civicrm_option_group WHERE name = 'contribution_status'
        )
      WHERE c.id = %1
    ";
    $rows = CRM_Core_DAO::executeQuery($sql, [1 => [$id, 'Integer']])->fetchAll();
    return !empty($rows) ? $rows[0] : NULL;
  }

  /**
   * Get line items for a contribution.
   */
  private static function getLineItems(int $contributionId) {
    $sql = "
      SELECT li.id, li.label, li.qty, li.unit_price, li.line_total,
             li.tax_amount, li.financial_type_id,
             ft.name AS financial_type_name
      FROM civicrm_line_item li
      LEFT JOIN civicrm_financial_type ft ON ft.id = li.financial_type_id
      WHERE li.contribution_id = %1
      ORDER BY li.id
    ";
    return CRM_Core_DAO::executeQuery($sql, [1 => [$contributionId, 'Integer']])->fetchAll();
  }

  /**
   * Get financial items for a line item.
   */
  private static function getFinancialItemsForLineItem(int $lineItemId) {
    $sql = "
      SELECT fi.id, fi.amount, fi.currency, fi.created_date,
             fi.transaction_date, fi.status_id, fi.description,
             fa.name AS account_name, fa.accounting_code,
             fat.label AS account_type_label,
             s.label AS status_label
      FROM civicrm_financial_item fi
      LEFT JOIN civicrm_financial_account fa ON fa.id = fi.financial_account_id
      LEFT JOIN civicrm_option_value fat
        ON fat.value = fa.financial_account_type_id
        AND fat.option_group_id = (
          SELECT id FROM civicrm_option_group WHERE name = 'financial_account_type'
        )
      LEFT JOIN civicrm_option_value s
        ON s.value = fi.status_id
        AND s.option_group_id = (
          SELECT id FROM civicrm_option_group WHERE name = 'financial_item_status'
        )
      WHERE fi.entity_table = 'civicrm_line_item' AND fi.entity_id = %1
      ORDER BY fi.id
    ";
    return CRM_Core_DAO::executeQuery($sql, [1 => [$lineItemId, 'Integer']])->fetchAll();
  }

  /**
   * Get entity_financial_trxn links for a financial item, with full trxn details.
   */
  private static function getTrxnLinksForFinancialItem(int $financialItemId) {
    $sql = "
      SELECT eft.id AS eft_id, eft.amount AS allocated_amount,
             ft.id AS trxn_id, ft.total_amount, ft.trxn_date,
             ft.trxn_id AS processor_trxn_id, ft.is_payment,
             ft.currency, ft.fee_amount,
             fa_from.name AS from_account_name,
             fa_from.id   AS from_account_id,
             fa_to.name   AS to_account_name,
             fa_to.id     AS to_account_id,
             pi.label     AS payment_instrument
      FROM civicrm_entity_financial_trxn eft
      INNER JOIN civicrm_financial_trxn ft ON ft.id = eft.financial_trxn_id
      LEFT JOIN civicrm_financial_account fa_from ON fa_from.id = ft.from_financial_account_id
      LEFT JOIN civicrm_financial_account fa_to   ON fa_to.id   = ft.to_financial_account_id
      LEFT JOIN civicrm_option_value pi
        ON pi.value = ft.payment_instrument_id
        AND pi.option_group_id = (
          SELECT id FROM civicrm_option_group WHERE name = 'payment_instrument'
        )
      WHERE eft.entity_table = 'civicrm_financial_item' AND eft.entity_id = %1
      ORDER BY ft.trxn_date
    ";
    return CRM_Core_DAO::executeQuery($sql, [1 => [$financialItemId, 'Integer']])->fetchAll();
  }

  /**
   * Get all financial transactions linked to a contribution.
   */
  private static function getTransactions(int $contributionId) {
    $sql = "
      SELECT ft.id, ft.total_amount, ft.fee_amount, ft.net_amount,
             ft.trxn_date, ft.trxn_id AS processor_trxn_id,
             ft.is_payment, ft.currency, ft.check_number, ft.pan_truncation,
             fa_from.name AS from_account_name,
             fa_to.name   AS to_account_name,
             eft.amount   AS allocated_amount
      FROM civicrm_entity_financial_trxn eft
      INNER JOIN civicrm_financial_trxn ft ON ft.id = eft.financial_trxn_id
      LEFT JOIN civicrm_financial_account fa_from ON fa_from.id = ft.from_financial_account_id
      LEFT JOIN civicrm_financial_account fa_to   ON fa_to.id   = ft.to_financial_account_id
      WHERE eft.entity_table = 'civicrm_contribution' AND eft.entity_id = %1
      ORDER BY ft.trxn_date
    ";
    return CRM_Core_DAO::executeQuery($sql, [1 => [$contributionId, 'Integer']])->fetchAll();
  }

  /**
   * Check integrity of the chain and return status flags.
   */
  private static function getChainStatus(int $contributionId) {
    $status = [
      'has_line_items' => FALSE,
      'has_financial_items' => FALSE,
      'has_eft_contribution' => FALSE,
      'has_eft_fi' => FALSE,
      'has_trxns' => FALSE,
      'amounts_match' => FALSE,
      'is_complete' => FALSE,
    ];

    $status['has_line_items'] = (bool) CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_line_item WHERE contribution_id = %1",
      [1 => [$contributionId, 'Integer']]
    );

    $status['has_financial_items'] = (bool) CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_financial_item fi
       INNER JOIN civicrm_line_item li ON fi.entity_table='civicrm_line_item' AND fi.entity_id=li.id
       WHERE li.contribution_id = %1",
      [1 => [$contributionId, 'Integer']]
    );

    $status['has_eft_contribution'] = (bool) CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_entity_financial_trxn
       WHERE entity_table='civicrm_contribution' AND entity_id = %1",
      [1 => [$contributionId, 'Integer']]
    );

    $status['has_eft_fi'] = (bool) CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_entity_financial_trxn eft
       INNER JOIN civicrm_financial_item fi ON eft.entity_table='civicrm_financial_item' AND eft.entity_id=fi.id
       INNER JOIN civicrm_line_item li ON fi.entity_table='civicrm_line_item' AND fi.entity_id=li.id
       WHERE li.contribution_id = %1",
      [1 => [$contributionId, 'Integer']]
    );

    $status['has_trxns'] = (bool) CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_entity_financial_trxn eft
       INNER JOIN civicrm_financial_trxn ft ON ft.id=eft.financial_trxn_id
       WHERE ft.is_payment = 1 AND eft.entity_table='civicrm_contribution'
         AND eft.entity_id = %1",
      [1 => [$contributionId, 'Integer']]
    );

    $contributionTotal = (float) CRM_Core_DAO::singleValueQuery(
      "SELECT total_amount FROM civicrm_contribution WHERE id = %1",
      [1 => [$contributionId, 'Integer']]
    );

    $lineItemTotal = (float) CRM_Core_DAO::singleValueQuery(
      "SELECT COALESCE(SUM(line_total), 0)
       FROM civicrm_line_item
       WHERE contribution_id = %1 AND qty <> 0",
      [1 => [$contributionId, 'Integer']]
    );

    $financialItemTotal = (float) CRM_Core_DAO::singleValueQuery(
      "SELECT COALESCE(SUM(fi.amount), 0)
       FROM civicrm_financial_item fi
       INNER JOIN civicrm_line_item li
              ON fi.entity_table = 'civicrm_line_item' AND fi.entity_id = li.id
       WHERE li.contribution_id = %1",
      [1 => [$contributionId, 'Integer']]
    );

    $trxnTotal = (float) CRM_Core_DAO::singleValueQuery(
      "SELECT COALESCE(SUM(ft.total_amount), 0)
       FROM civicrm_entity_financial_trxn eft
       INNER JOIN civicrm_financial_trxn ft ON ft.id = eft.financial_trxn_id
       WHERE ft.is_payment = 1 AND eft.entity_table = 'civicrm_contribution'
         AND eft.entity_id = %1",
      [1 => [$contributionId, 'Integer']]
    );

    $status['line_item_diff']      = round(abs($contributionTotal - $lineItemTotal), 4);
    $status['financial_item_diff'] = round(abs($contributionTotal - $financialItemTotal), 4);
    $status['trxn_diff']           = round(abs($contributionTotal - $trxnTotal), 4);

    $status['amounts_match'] = (
      $status['line_item_diff'] < 0.01 &&
      $status['financial_item_diff'] < 0.01 &&
      $status['trxn_diff'] < 0.01
    );

    $status['is_complete'] = $status['has_line_items']
      && $status['has_financial_items']
      && $status['has_eft_contribution']
      && $status['has_eft_fi']
      && $status['has_trxns']
      && $status['amounts_match'];

    return $status;
  }

  /**
   * Get CiviLedger audit log entries for a contribution.
   */
  private static function getAuditLog(int $contributionId) {
    static $tableExists = NULL;
    if ($tableExists === NULL) {
      $tableExists = (bool) CRM_Core_DAO::singleValueQuery(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'civicrm_civiledger_audit_log'"
      );
    }
    if (!$tableExists) {
      return [];
    }
    $sql = "
      SELECT al.id, al.event_type, al.entity_type, al.entity_id,
             al.actor_id, al.logged_at, al.detail, al.entry_hash,
             con.display_name AS performed_by
      FROM civicrm_civiledger_audit_log al
      LEFT JOIN civicrm_contact con ON con.id = al.actor_id
      WHERE (al.entity_type = 'contribution' AND al.entity_id = %1)
         OR (al.entity_type = 'financial_trxn'
             AND al.detail LIKE %2)
      ORDER BY al.logged_at DESC
    ";
    $rows = CRM_Core_DAO::executeQuery($sql, [
      1 => [$contributionId, 'Integer'],
      2 => ['%"contribution_id":' . $contributionId . '%', 'String'],
    ])->fetchAll();

    // Decode detail JSON and batch-lookup account names for CORRECTION entries.
    $accountIds = [];
    foreach ($rows as &$row) {
      $row['detail_decoded'] = !empty($row['detail']) ? json_decode($row['detail'], TRUE) : [];
      foreach (['old_from_account_id', 'new_from_account_id', 'old_to_account_id', 'new_to_account_id'] as $k) {
        if (!empty($row['detail_decoded'][$k])) {
          $accountIds[(int) $row['detail_decoded'][$k]] = NULL;
        }
      }
    }
    unset($row);

    if (!empty($accountIds)) {
      $ids = implode(',', array_keys($accountIds));
      $accs = CRM_Core_DAO::executeQuery("SELECT id, name FROM civicrm_financial_account WHERE id IN ({$ids})")->fetchAll();
      foreach ($accs as $acc) {
        $accountIds[(int) $acc['id']] = $acc['name'];
      }
      foreach ($rows as &$row) {
        $d = $row['detail_decoded'];
        $row['old_from_name'] = $accountIds[$d['old_from_account_id'] ?? 0] ?? NULL;
        $row['new_from_name'] = $accountIds[$d['new_from_account_id'] ?? 0] ?? NULL;
        $row['old_to_name']   = $accountIds[$d['old_to_account_id'] ?? 0] ?? NULL;
        $row['new_to_name']   = $accountIds[$d['new_to_account_id'] ?? 0] ?? NULL;
      }
      unset($row);
    }

    return $rows;
  }

  /**
   * Search contributions for the audit trail listing.
   */
  public static function searchContributions(array $params = []) {
    $where = ['c.is_test = 0'];
    $queryParams = [];
    $i = 1;

    if (!empty($params['contact_id'])) {
      $where[] = "c.contact_id = %{$i}";
      $queryParams[$i++] = [(int) $params['contact_id'], 'Integer'];
    }
    if (!empty($params['contribution_id'])) {
      $where[] = "c.id = %{$i}";
      $queryParams[$i++] = [(int) $params['contribution_id'], 'Integer'];
    }
    if (!empty($params['date_from'])) {
      $where[] = "c.receive_date >= %{$i}";
      $queryParams[$i++] = [$params['date_from'] . ' 00:00:00', 'String'];
    }
    if (!empty($params['date_to'])) {
      $where[] = "c.receive_date <= %{$i}";
      $queryParams[$i++] = [$params['date_to'] . ' 23:59:59', 'String'];
    }

    $whereClause = implode(' AND ', $where);
    $limit = (int) ($params['limit'] ?? 25);
    $offset = (int) ($params['offset'] ?? 0);

    $sql = "
      SELECT c.id, c.contact_id, c.total_amount, c.currency,
             c.receive_date, c.contribution_status_id,
             con.display_name AS contact_name,
             ft.name AS financial_type_name
      FROM civicrm_contribution c
      LEFT JOIN civicrm_contact con ON con.id = c.contact_id
      LEFT JOIN civicrm_financial_type ft ON ft.id = c.financial_type_id
      WHERE {$whereClause}
      ORDER BY c.receive_date DESC
      LIMIT {$limit} OFFSET {$offset}
    ";
    return CRM_Core_DAO::executeQuery($sql, $queryParams)->fetchAll();
  }

}
