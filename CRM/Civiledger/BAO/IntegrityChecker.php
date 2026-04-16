<?php

/**
 * CiviLedger - Feature 1: Financial Integrity Checker
 *
 * Detects broken financial chains in CiviCRM contributions.
 * A complete chain requires:
 *   civicrm_contribution
 *     → civicrm_line_item
 *       → civicrm_financial_item
 *         → civicrm_entity_financial_trxn (entity_table = civicrm_financial_item)
 *     → civicrm_entity_financial_trxn (entity_table = civicrm_contribution)
 *       → civicrm_financial_trxn
 *
 * @package  com.skvare.civiledger
 */
class CRM_Civiledger_BAO_IntegrityChecker {

  /**
   * Run full integrity check and return results grouped by issue type.
   *
   * @param array $filters Optional filters: date_from, date_to, status_id
   * @return array
   */
  public static function runCheck(array $filters = []): array {
    return [
      'missing_line_items' => self::getMissingLineItems($filters),
      'missing_financial_items' => self::getMissingFinancialItems($filters),
      'missing_contribution_trxn_link' => self::getMissingContributionTrxnLink($filters),
      'missing_financial_item_link' => self::getMissingFinancialItemLink($filters),
      'orphaned_financial_trxn' => self::getOrphanedFinancialTrxn($filters),
    ];
  }

  /**
   * Get summary counts only (fast, for dashboard widget).
   */
  public static function getSummaryCounts(array $filters = []): array {
    [$where, $params] = self::buildWhereClause('c', $filters);

    $summary = [
      'missing_line_items' => (int) CRM_Core_DAO::singleValueQuery(
        "SELECT COUNT(DISTINCT c.id) FROM civicrm_contribution c
         LEFT JOIN civicrm_line_item li ON li.contribution_id = c.id
         WHERE li.id IS NULL AND c.is_test = 0 $where", $params),

      'missing_financial_items' => (int) CRM_Core_DAO::singleValueQuery(
        "SELECT COUNT(DISTINCT li.id) FROM civicrm_contribution c
         INNER JOIN civicrm_line_item li ON li.contribution_id = c.id
         LEFT JOIN civicrm_financial_item fi
           ON fi.entity_table = 'civicrm_line_item' AND fi.entity_id = li.id
         WHERE fi.id IS NULL AND c.is_test = 0 $where", $params),

      'missing_contribution_trxn_link' => (int) CRM_Core_DAO::singleValueQuery(
        "SELECT COUNT(DISTINCT c.id) FROM civicrm_contribution c
         LEFT JOIN civicrm_entity_financial_trxn eft
           ON eft.entity_table = 'civicrm_contribution' AND eft.entity_id = c.id
         WHERE eft.id IS NULL AND c.contribution_status_id = 1 AND c.is_test = 0 $where", $params),

      'missing_financial_item_link' => (int) CRM_Core_DAO::singleValueQuery(
        "SELECT COUNT(DISTINCT fi.id) FROM civicrm_contribution c
         INNER JOIN civicrm_line_item li ON li.contribution_id = c.id
         INNER JOIN civicrm_financial_item fi
           ON fi.entity_table = 'civicrm_line_item' AND fi.entity_id = li.id
         LEFT JOIN civicrm_entity_financial_trxn eft
           ON eft.entity_table = 'civicrm_financial_item' AND eft.entity_id = fi.id
         WHERE eft.id IS NULL AND c.contribution_status_id = 1 AND c.is_test = 0 $where", $params),

      'orphaned_financial_trxn' => (int) CRM_Core_DAO::singleValueQuery(
        "SELECT COUNT(DISTINCT ft.id) FROM civicrm_financial_trxn ft
         LEFT JOIN civicrm_entity_financial_trxn eft ON eft.financial_trxn_id = ft.id
         WHERE eft.id IS NULL"),
    ];

    $summary['missing_eft_financial_item'] = $summary['missing_financial_item_link'];
    $summary['total'] = array_sum($summary);
    return $summary;
  }

  /**
   * Issue 1: Contributions with no line items at all.
   */
  public static function getMissingLineItems(array $filters = []): array {
    [$where, $params] = self::buildWhereClause('c', $filters);
    $sql = "
      SELECT c.id AS contribution_id,
             c.total_amount,
             c.currency,
             c.receive_date,
             c.contribution_status_id,
             c.contact_id,
             ct.display_name AS contact_name
      FROM civicrm_contribution c
      LEFT JOIN civicrm_contact ct ON ct.id = c.contact_id
      LEFT JOIN civicrm_line_item li ON li.contribution_id = c.id
      WHERE li.id IS NULL
        AND c.is_test = 0
        $where
      ORDER BY c.receive_date DESC
      LIMIT 500
    ";
    return self::fetchResults($sql, $params, 'missing_line_items');
  }

  /**
   * Issue 2: Line items with no corresponding financial_item.
   */
  public static function getMissingFinancialItems(array $filters = []): array {
    [$where, $params] = self::buildWhereClause('c', $filters);
    $sql = "
      SELECT c.id AS contribution_id,
             c.total_amount,
             c.currency,
             c.receive_date,
             c.contribution_status_id,
             c.contact_id,
             ct.display_name AS contact_name,
             li.id AS line_item_id,
             li.line_total AS line_total
      FROM civicrm_contribution c
      LEFT JOIN civicrm_contact ct ON ct.id = c.contact_id
      INNER JOIN civicrm_line_item li ON li.contribution_id = c.id
      LEFT JOIN civicrm_financial_item fi
        ON fi.entity_table = 'civicrm_line_item' AND fi.entity_id = li.id
      WHERE fi.id IS NULL
        AND c.is_test = 0
        $where
      ORDER BY c.receive_date DESC
      LIMIT 500
    ";
    return self::fetchResults($sql, $params, 'missing_financial_items');
  }

  /**
   * Issue 3: Contributions missing entity_financial_trxn link
   *          (entity_table = civicrm_contribution).
   */
  public static function getMissingContributionTrxnLink(array $filters = []): array {
    [$where, $params] = self::buildWhereClause('c', $filters);
    $sql = "
      SELECT c.id AS contribution_id,
             c.total_amount,
             c.currency,
             c.receive_date,
             c.contribution_status_id,
             c.contact_id,
             ct.display_name AS contact_name
      FROM civicrm_contribution c
      LEFT JOIN civicrm_contact ct ON ct.id = c.contact_id
      LEFT JOIN civicrm_entity_financial_trxn eft
        ON eft.entity_table = 'civicrm_contribution' AND eft.entity_id = c.id
      WHERE eft.id IS NULL
        AND c.contribution_status_id = 1
        AND c.is_test = 0
        $where
      ORDER BY c.receive_date DESC
      LIMIT 500
    ";
    return self::fetchResults($sql, $params, 'missing_contribution_trxn_link');
  }

  /**
   * Issue 4: Financial items missing entity_financial_trxn link
   *          (entity_table = civicrm_financial_item).
   *          This is the most common silent corruption.
   */
  public static function getMissingFinancialItemLink(array $filters = []): array {
    [$where, $params] = self::buildWhereClause('c', $filters);
    $sql = "
      SELECT c.id AS contribution_id,
             c.total_amount,
             c.currency,
             c.receive_date,
             c.contribution_status_id,
             c.contact_id,
             ct.display_name AS contact_name,
             fi.id AS financial_item_id,
             fi.amount AS fi_amount,
             fa.name AS account_name
      FROM civicrm_contribution c
      LEFT JOIN civicrm_contact ct ON ct.id = c.contact_id
      INNER JOIN civicrm_line_item li ON li.contribution_id = c.id
      INNER JOIN civicrm_financial_item fi
        ON fi.entity_table = 'civicrm_line_item' AND fi.entity_id = li.id
      LEFT JOIN civicrm_financial_account fa ON fa.id = fi.financial_account_id
      LEFT JOIN civicrm_entity_financial_trxn eft
        ON eft.entity_table = 'civicrm_financial_item' AND eft.entity_id = fi.id
      WHERE eft.id IS NULL
        AND c.contribution_status_id = 1
        AND c.is_test = 0
        $where
      ORDER BY c.receive_date DESC
      LIMIT 500
    ";
    return self::fetchResults($sql, $params, 'missing_financial_item_link');
  }

  /**
   * Issue 5: Financial transactions with no entity link at all (orphaned).
   */
  public static function getOrphanedFinancialTrxn(array $filters = []): array {
    $params = [];
    $dateWhere = '';
    if (!empty($filters['date_from'])) {
      $dateWhere .= " AND ft.trxn_date >= %1";
      $params[1] = [$filters['date_from'], 'String'];
    }
    if (!empty($filters['date_to'])) {
      $dateWhere .= " AND ft.trxn_date <= %2";
      $params[2] = [$filters['date_to'], 'String'];
    }
    $sql = "
      SELECT ft.id AS financial_trxn_id,
             ft.total_amount,
             ft.currency,
             ft.trxn_date,
             ft.trxn_id,
             ft.is_payment,
             fa_from.name AS from_account,
             fa_to.name AS to_account
      FROM civicrm_financial_trxn ft
      LEFT JOIN civicrm_financial_account fa_from ON fa_from.id = ft.from_financial_account_id
      LEFT JOIN civicrm_financial_account fa_to   ON fa_to.id   = ft.to_financial_account_id
      LEFT JOIN civicrm_entity_financial_trxn eft ON eft.financial_trxn_id = ft.id
      WHERE eft.id IS NULL
        $dateWhere
      ORDER BY ft.trxn_date DESC
      LIMIT 500
    ";
    return self::fetchResults($sql, $params, 'orphaned_financial_trxn');
  }

  // -----------------------------------------------------------------------
  // Aliases for backward compatibility with Page calls
  // -----------------------------------------------------------------------

  /** Alias: runAll() → runCheck() */
  public static function runAll(array $filters = []): array {
    $results = self::runCheck($filters);
    $results['summary'] = [
      'missing_line_items' => count($results['missing_line_items']),
      'missing_financial_items' => count($results['missing_financial_items']),
      'missing_contribution_trxn_link' => count($results['missing_contribution_trxn_link']),
      'missing_financial_item_trxn_link' => count($results['missing_financial_item_link']),
      'missing_financial_item' => count($results['missing_financial_items']),
      'orphaned_financial_trxn' => count($results['orphaned_financial_trxn']),
    ];
    // Merge renamed key for templates
    $results['missing_financial_item_trxn_link'] = $results['missing_financial_item_link'];
    return $results;
  }

  /** Alias: getSummary() → getSummaryCounts() */
  public static function getSummary(array $filters = []): array {
    return self::getSummaryCounts($filters);
  }

  /** Alias: checkMissingContributionTrxnLink() */
  public static function checkMissingContributionTrxnLink(int $limit = 200): array {
    return array_slice(self::getMissingContributionTrxnLink(), 0, $limit);
  }

  /** Alias: checkMissingFinancialItemTrxnLink() */
  public static function checkMissingFinancialItemTrxnLink(int $limit = 200): array {
    return array_slice(self::getMissingFinancialItemLink(), 0, $limit);
  }

  /** Alias: getMissingEftFinancialItem() */
  public static function getMissingEftFinancialItem(int $limit = 50): array {
    return array_slice(self::getMissingFinancialItemLink(), 0, $limit);
  }

  /** Get flat list of contribution IDs with any integrity issue. */
  public static function getBrokenContributionIds(int $limit = 200): array {
    $sql = "
      SELECT DISTINCT c.id
      FROM civicrm_contribution c
      LEFT JOIN civicrm_entity_financial_trxn eft
        ON eft.entity_table = 'civicrm_contribution' AND eft.entity_id = c.id
      WHERE eft.id IS NULL
        AND c.contribution_status_id = 1
        AND c.is_test = 0
      ORDER BY c.id DESC
      LIMIT $limit
    ";
    $ids = [];
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $ids[] = $dao->id;
    }
    return $ids;
  }

  // -----------------------------------------------------------------------
  // Private helpers
  // -----------------------------------------------------------------------

  private static function buildWhereClause(string $alias, array $filters): array {
    $where = '';
    $params = [];
    $i = 1;
    if (!empty($filters['date_from'])) {
      $where .= " AND $alias.receive_date >= %$i";
      $params[$i++] = [$filters['date_from'], 'String'];
    }
    if (!empty($filters['date_to'])) {
      $where .= " AND $alias.receive_date <= %$i";
      $params[$i++] = [$filters['date_to'], 'String'];
    }
    if (!empty($filters['status_id'])) {
      $where .= " AND $alias.contribution_status_id = %$i";
      $params[$i++] = [(int) $filters['status_id'], 'Integer'];
    }
    return [$where, $params];
  }

  private static function fetchResults(string $sql, array $params, string $issueType): array {
    $rows = [];
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    while ($dao->fetch()) {
      $row = $dao->toArray();
      $row['issue_type'] = $issueType;
      if (!empty($row['contribution_id'])) {
        $row['contribution_url'] = CRM_Civiledger_BAO_Utils::getContributionUrl($row['contribution_id']);
        $row['audit_trail_url'] = CRM_Civiledger_BAO_Utils::getAuditTrailUrl($row['contribution_id']);
        $row['repair_url'] = CRM_Utils_System::url('civicrm/civiledger/chain-repair',
          "reset=1&contribution_id={$row['contribution_id']}");
        if (!empty($row['contribution_status_id'])) {
          $row['status_label'] = CRM_Civiledger_BAO_Utils::getContributionStatusName(
            (int) $row['contribution_status_id']
          );
        }
      }
      $rows[] = $row;
    }
    return $rows;
  }

}
