<?php
/**
 * CiviLedger - Feature 4: Account Balance Dashboard
 *
 * Calculates live balances for every financial account by summing
 * all from/to movements in civicrm_financial_trxn.
 */
class CRM_Civiledger_BAO_AccountBalance {

  /**
   * Get balances for all active financial accounts.
   *
   * @param string|null $dateFrom  Y-m-d
   * @param string|null $dateTo    Y-m-d
   * @return array
   */
  public static function getBalances($dateFrom = NULL, $dateTo = NULL) {
    $dateWhere = self::buildDateWhere($dateFrom, $dateTo);
    $dateParams = self::buildDateParams($dateFrom, $dateTo);

    $sql = "
      SELECT
        fa.id,
        fa.name,
        fa.accounting_code,
        fa.is_active,
        ov.label AS account_type,
        COALESCE(SUM(CASE WHEN ft.to_financial_account_id   = fa.id {$dateWhere} THEN ft.total_amount ELSE 0 END), 0) AS total_credits,
        COALESCE(SUM(CASE WHEN ft.from_financial_account_id = fa.id {$dateWhere} THEN ft.total_amount ELSE 0 END), 0) AS total_debits,
        COALESCE(SUM(CASE WHEN ft.to_financial_account_id   = fa.id {$dateWhere} THEN ft.total_amount ELSE 0 END), 0)
        - COALESCE(SUM(CASE WHEN ft.from_financial_account_id = fa.id {$dateWhere} THEN ft.total_amount ELSE 0 END), 0) AS balance,
        COUNT(DISTINCT CASE WHEN (ft.from_financial_account_id = fa.id OR ft.to_financial_account_id = fa.id) {$dateWhere} THEN ft.id END) AS trxn_count
      FROM civicrm_financial_account fa
      LEFT JOIN civicrm_financial_trxn ft
        ON (ft.from_financial_account_id = fa.id OR ft.to_financial_account_id = fa.id)
      LEFT JOIN civicrm_option_value ov
        ON ov.value = fa.financial_account_type_id
        AND ov.option_group_id = (
          SELECT id FROM civicrm_option_group WHERE name = 'financial_account_type'
        )
      WHERE fa.is_active = 1
      GROUP BY fa.id, fa.name, fa.accounting_code, fa.is_active, ov.label
      ORDER BY ov.label, fa.name
    ";

    return CRM_Core_DAO::executeQuery($sql, $dateParams)->fetchAll();
  }

  /**
   * Get movement details for a specific account.
   *
   * @param int         $accountId
   * @param string|null $dateFrom
   * @param string|null $dateTo
   * @param int         $limit
   * @param int         $offset
   * @return array
   */
  public static function getAccountMovements(int $accountId, $dateFrom = NULL, $dateTo = NULL, $limit = 50, $offset = 0) {
    $conditions = ['(ft.from_financial_account_id = %1 OR ft.to_financial_account_id = %1)'];
    $params = [1 => [$accountId, 'Integer']];
    $i = 2;

    if ($dateFrom) {
      $conditions[] = "ft.trxn_date >= %{$i}";
      $params[$i++] = [$dateFrom . ' 00:00:00', 'String'];
    }
    if ($dateTo) {
      $conditions[] = "ft.trxn_date <= %{$i}";
      $params[$i++] = [$dateTo . ' 23:59:59', 'String'];
    }

    $where = implode(' AND ', $conditions);

    $sql = "
      SELECT
        ft.id AS trxn_id,
        ft.trxn_date,
        ft.total_amount,
        ft.currency,
        ft.is_payment,
        ft.trxn_id AS processor_ref,
        fa_from.name AS from_account,
        fa_to.name   AS to_account,
        CASE
          WHEN ft.to_financial_account_id = {$accountId} THEN 'credit'
          ELSE 'debit'
        END AS direction,
        CASE
          WHEN ft.to_financial_account_id = {$accountId} THEN ft.total_amount
          ELSE 0
        END AS credit_amount,
        CASE
          WHEN ft.from_financial_account_id = {$accountId} THEN ft.total_amount
          ELSE 0
        END AS debit_amount,
        con.display_name AS contact_name,
        c.id AS contribution_id
      FROM civicrm_financial_trxn ft
      LEFT JOIN civicrm_financial_account fa_from ON fa_from.id = ft.from_financial_account_id
      LEFT JOIN civicrm_financial_account fa_to   ON fa_to.id   = ft.to_financial_account_id
      LEFT JOIN civicrm_entity_financial_trxn eft
        ON eft.financial_trxn_id = ft.id AND eft.entity_table = 'civicrm_contribution'
      LEFT JOIN civicrm_contribution c ON c.id = eft.entity_id
      LEFT JOIN civicrm_contact con ON con.id = c.contact_id
      WHERE {$where}
      ORDER BY ft.trxn_date DESC
      LIMIT {$limit} OFFSET {$offset}
    ";

    return CRM_Core_DAO::executeQuery($sql, $params)->fetchAll();
  }

  /**
   * Summary stats for the dashboard header.
   */
  public static function getSummaryStats($dateFrom = NULL, $dateTo = NULL) {
    $dateCondition = '';
    $params = [];
    $i = 1;

    if ($dateFrom) {
      $dateCondition .= " AND ft.trxn_date >= %{$i}";
      $params[$i++] = [$dateFrom . ' 00:00:00', 'String'];
    }
    if ($dateTo) {
      $dateCondition .= " AND ft.trxn_date <= %{$i}";
      $params[$i++] = [$dateTo . ' 23:59:59', 'String'];
    }

    $sql = "
      SELECT
        COUNT(DISTINCT ft.id)   AS total_transactions,
        SUM(CASE WHEN ft.is_payment = 1 THEN ft.total_amount ELSE 0 END) AS total_payments,
        COUNT(DISTINCT CASE WHEN ft.total_amount < 0 THEN ft.id END) AS refund_count,
        COUNT(DISTINCT fa_to.id) AS accounts_with_activity
      FROM civicrm_financial_trxn ft
      LEFT JOIN civicrm_financial_account fa_to ON fa_to.id = ft.to_financial_account_id
      WHERE 1=1 {$dateCondition}
    ";

    $result = CRM_Core_DAO::executeQuery($sql, $params)->fetchAll();
    return !empty($result) ? $result[0] : [];
  }

  /**
   * Get all financial accounts as options for dropdowns.
   */
  public static function getAccountOptions() {
    $sql = "SELECT id, name, accounting_code FROM civicrm_financial_account WHERE is_active = 1 ORDER BY name";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $options = ['' => ts('-- Select Account --')];
    while ($dao->fetch()) {
      $label = $dao->name;
      if ($dao->accounting_code) {
        $label .= " ({$dao->accounting_code})";
      }
      $options[$dao->id] = $label;
    }
    return $options;
  }

  private static function buildDateWhere($dateFrom, $dateTo) {
    $parts = [];
    if ($dateFrom) $parts[] = 'AND ft.trxn_date >= \'' . CRM_Core_DAO::escapeString($dateFrom) . ' 00:00:00\'';
    if ($dateTo)   $parts[] = 'AND ft.trxn_date <= \'' . CRM_Core_DAO::escapeString($dateTo) . ' 23:59:59\'';
    return implode(' ', $parts);
  }

  private static function buildDateParams($dateFrom, $dateTo) {
    return []; // Used inline in buildDateWhere above
  }

}
