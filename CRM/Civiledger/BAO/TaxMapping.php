<?php
/**
 * CiviLedger — Tax Mapping BAO
 *
 * Column usage rationale:
 *
 *   civicrm_financial_type.is_deductible
 *     Type-level flag: "contributions of this type may be fully OR partially
 *     deductible — non-deductible amount is stored in the Contribution record."
 *     Used for classification only.
 *
 *   civicrm_line_item.non_deductible_amount  ← PRIMARY source
 *     Most granular level. Populated from price_field_value.non_deductible_amount
 *     at purchase time. Each line item has its own financial_type_id, so grouping
 *     here correctly handles contributions that mix financial types (e.g. a gala
 *     with both "Event Fee" and "Donation" lines).
 *
 *   civicrm_contribution.non_deductible_amount  ← FALLBACK
 *     Rollup of line-item values. CiviCRM sets it equal to total_amount for
 *     non-deductible financial types. Used when a contribution has no line items
 *     or when detecting roll-up mismatches.
 *
 *   civicrm_price_field_value.non_deductible_amount
 *     Template/default benefit amount per price option.
 *     Used only in issue detection: flags where the actual line item value drifted
 *     from the price field template.
 *
 *   civicrm_financial_account.is_deductible
 *     Account-level flag (defaults to 1). Not used here — financial_type.is_deductible
 *     is the authoritative classification for contribution-level reporting.
 */
class CRM_Civiledger_BAO_TaxMapping {

  /**
   * Top-level summary totals.
   * Non-deductible = SUM of line-item values; fallback to contribution-level when absent.
   */
  public static function getSummary($dateFrom = NULL, $dateTo = NULL): array {
    [$where, $params] = self::buildContribWhere($dateFrom, $dateTo);

    $sql = "
      SELECT
        COUNT(DISTINCT c.id)                                                           AS total_contributions,
        COALESCE(SUM(c.total_amount), 0)                                               AS total_amount,
        -- Use line-item rollup as primary; fall back to contribution-level field
        COALESCE(SUM(
          COALESCE(NULLIF(li_totals.li_non_ded, 0), c.non_deductible_amount, 0)
        ), 0)                                                                          AS total_non_deductible,
        COALESCE(SUM(c.total_amount), 0)
          - COALESCE(SUM(
              COALESCE(NULLIF(li_totals.li_non_ded, 0), c.non_deductible_amount, 0)
            ), 0)                                                                      AS total_deductible,
        COUNT(DISTINCT CASE
          WHEN COALESCE(NULLIF(li_totals.li_non_ded, 0), c.non_deductible_amount, 0) > 0
           AND COALESCE(NULLIF(li_totals.li_non_ded, 0), c.non_deductible_amount, 0) < c.total_amount
          THEN c.id END)                                                               AS split_count,
        -- Issues: non-ded > total (impossible) OR li sum mismatches contribution rollup
        COUNT(DISTINCT CASE
          WHEN c.non_deductible_amount > c.total_amount
            OR (li_totals.li_non_ded IS NOT NULL
                AND ABS(li_totals.li_non_ded - c.non_deductible_amount) > 0.01)
          THEN c.id END)                                                               AS issues_count
      FROM civicrm_contribution c
      JOIN  civicrm_financial_type ft        ON ft.id  = c.financial_type_id
      LEFT JOIN (
        SELECT contribution_id, SUM(non_deductible_amount) AS li_non_ded
        FROM   civicrm_line_item
        WHERE  contribution_id IS NOT NULL
        GROUP  BY contribution_id
      ) li_totals ON li_totals.contribution_id = c.id
      WHERE {$where}
    ";

    $rows = CRM_Core_DAO::executeQuery($sql, $params)->fetchAll();
    return $rows[0] ?? [];
  }

  /**
   * Breakdown by financial type — grouped on LINE ITEM financial_type_id,
   * not the contribution header, so mixed-type contributions are split correctly.
   *
   * Starts from civicrm_contribution (LEFT JOIN to line items) so that:
   *   1. Contributions with no line items are included (using contribution financial type).
   *   2. When all line items have non_deductible_amount=0 but the contribution-level
   *      non_deductible_amount>0, a proportional share is applied as a fallback — matching
   *      the same fallback used in getSummary().
   */
  public static function getByFinancialType($dateFrom = NULL, $dateTo = NULL): array {
    [$where, $params] = self::buildContribWhere($dateFrom, $dateTo, 'c');

    $sql = "
      SELECT
        financial_type_id,
        financial_type_name,
        is_deductible,
        COUNT(DISTINCT cid)                                                  AS contribution_count,
        COUNT(liid)                                                          AS line_item_count,
        COALESCE(SUM(line_total), 0)                                         AS total_amount,
        COALESCE(SUM(non_ded), 0)                                            AS non_deductible_amount,
        COALESCE(SUM(line_total - non_ded), 0)                               AS deductible_amount,
        COUNT(CASE WHEN non_ded > 0 AND non_ded < line_total THEN 1 END)     AS split_count,
        SUM(has_issue)                                                       AS issue_count
      FROM (
        SELECT
          COALESCE(li_ft.id,   c_ft.id)                                      AS financial_type_id,
          COALESCE(li_ft.name, c_ft.name)                                    AS financial_type_name,
          COALESCE(li_ft.is_deductible, c_ft.is_deductible)                  AS is_deductible,
          c.id                                                               AS cid,
          li.id                                                              AS liid,
          COALESCE(li.line_total, c.total_amount)                            AS line_total,
          CASE
            -- Line item has its own non-deductible value — use it directly
            WHEN li.id IS NOT NULL AND li.non_deductible_amount > 0
              THEN li.non_deductible_amount
            -- All line items for this contribution have 0 but contribution-level is set
            -- — distribute proportionally across line items
            WHEN li.id IS NOT NULL
                 AND COALESCE(li_totals.li_non_ded, 0) = 0
                 AND c.non_deductible_amount > 0
              THEN (li.line_total / NULLIF(c.total_amount, 0)) * c.non_deductible_amount
            -- No line items at all — use contribution-level value
            WHEN li.id IS NULL
              THEN COALESCE(c.non_deductible_amount, 0)
            ELSE 0
          END                                                                AS non_ded,
          CASE WHEN li.id IS NOT NULL AND li.non_deductible_amount > li.line_total
               THEN 1 ELSE 0 END                                            AS has_issue
        FROM civicrm_contribution c
        JOIN  civicrm_financial_type c_ft  ON c_ft.id = c.financial_type_id
        LEFT JOIN civicrm_line_item  li    ON li.contribution_id = c.id
        LEFT JOIN civicrm_financial_type li_ft ON li_ft.id = li.financial_type_id
        LEFT JOIN (
          SELECT contribution_id, SUM(non_deductible_amount) AS li_non_ded
          FROM   civicrm_line_item
          WHERE  contribution_id IS NOT NULL
          GROUP  BY contribution_id
        ) li_totals ON li_totals.contribution_id = c.id
        WHERE {$where}
      ) base
      GROUP BY financial_type_id, financial_type_name, is_deductible
      ORDER BY financial_type_name
    ";

    return CRM_Core_DAO::executeQuery($sql, $params)->fetchAll();
  }

  /**
   * Contributions with data problems.
   *
   * Issue types detected:
   *   non_deductible_exceeds_total  — contribution.non_deductible_amount > total_amount
   *   li_sum_mismatch               — SUM(line_item.non_ded) differs from contribution rollup by > $0.01
   *   non_deductible_type_not_set   — financial_type.is_deductible=0 but non_deductible_amount=0
   *   pfv_mismatch                  — a line item's non_ded differs from its price_field_value template
   */
  public static function getIssues($dateFrom = NULL, $dateTo = NULL, int $limit = 200): array {
    [$where, $params] = self::buildContribWhere($dateFrom, $dateTo);

    $sql = "
      SELECT
        c.id                                                                      AS contribution_id,
        c.receive_date,
        c.total_amount,
        COALESCE(c.non_deductible_amount, 0)                                      AS contrib_non_deductible,
        COALESCE(li_totals.li_non_ded, 0)                                         AS li_non_deductible,
        c.total_amount - COALESCE(c.non_deductible_amount, 0)                     AS deductible_amount,
        ft.name                                                                   AS financial_type_name,
        ft.is_deductible,
        con.display_name                                                          AS contact_name,
        CASE
          WHEN c.non_deductible_amount > c.total_amount
            THEN 'non_deductible_exceeds_total'
          WHEN li_totals.li_non_ded IS NOT NULL
            AND ABS(li_totals.li_non_ded - COALESCE(c.non_deductible_amount, 0)) > 0.01
            THEN 'li_sum_mismatch'
          WHEN ft.is_deductible = 0
            AND c.total_amount > 0
            AND (c.non_deductible_amount IS NULL OR c.non_deductible_amount = 0)
            THEN 'non_deductible_type_not_set'
        END                                                                       AS issue_type
      FROM civicrm_contribution c
      JOIN  civicrm_financial_type ft  ON ft.id  = c.financial_type_id
      JOIN  civicrm_contact         con ON con.id = c.contact_id
      LEFT JOIN (
        SELECT contribution_id, SUM(non_deductible_amount) AS li_non_ded
        FROM   civicrm_line_item
        WHERE  contribution_id IS NOT NULL
        GROUP  BY contribution_id
      ) li_totals ON li_totals.contribution_id = c.id
      WHERE {$where}
        AND (
          c.non_deductible_amount > c.total_amount
          OR (li_totals.li_non_ded IS NOT NULL
              AND ABS(li_totals.li_non_ded - COALESCE(c.non_deductible_amount, 0)) > 0.01)
          OR (ft.is_deductible = 0
              AND c.total_amount > 0
              AND (c.non_deductible_amount IS NULL OR c.non_deductible_amount = 0))
        )
      ORDER BY c.receive_date DESC
      LIMIT {$limit}
    ";

    $issues = CRM_Core_DAO::executeQuery($sql, $params)->fetchAll();

    // Second pass: detect price_field_value template mismatches per line item.
    // Only run if the result set is non-empty (avoids an extra query for clean datasets).
    if (!empty($issues)) {
      $pfvMismatches = self::getPriceFieldValueMismatches($dateFrom, $dateTo);
      // Merge pfv-mismatch contribution IDs into the issues list
      $pfvCids = array_column($pfvMismatches, 'contribution_id');
      $existingCids = array_column($issues, 'contribution_id');
      foreach ($pfvMismatches as $row) {
        if (!in_array($row['contribution_id'], $existingCids)) {
          $row['issue_type'] = 'pfv_mismatch';
          $issues[] = $row;
        }
      }
    }

    return $issues;
  }

  /**
   * Line items where actual non_deductible_amount differs from the price_field_value template.
   */
  public static function getPriceFieldValueMismatches($dateFrom = NULL, $dateTo = NULL, int $limit = 100): array {
    [$where, $params] = self::buildContribWhere($dateFrom, $dateTo);

    $sql = "
      SELECT
        c.id                                                               AS contribution_id,
        c.receive_date,
        c.total_amount,
        COALESCE(c.non_deductible_amount, 0)                               AS contrib_non_deductible,
        NULL                                                               AS li_non_deductible,
        ft.name                                                            AS financial_type_name,
        ft.is_deductible,
        con.display_name                                                   AS contact_name,
        'pfv_mismatch'                                                     AS issue_type,
        li.id                                                              AS line_item_id,
        li.label                                                           AS line_item_label,
        li.non_deductible_amount                                           AS li_non_ded,
        pfv.non_deductible_amount                                          AS pfv_non_ded
      FROM civicrm_line_item li
      JOIN  civicrm_contribution   c   ON c.id   = li.contribution_id
      JOIN  civicrm_financial_type ft  ON ft.id  = li.financial_type_id
      JOIN  civicrm_contact        con ON con.id  = c.contact_id
      JOIN  civicrm_price_field_value pfv ON pfv.id = li.price_field_value_id
      WHERE {$where}
        AND ABS(li.non_deductible_amount - pfv.non_deductible_amount) > 0.01
      ORDER BY c.receive_date DESC
      LIMIT {$limit}
    ";

    return CRM_Core_DAO::executeQuery($sql, $params)->fetchAll();
  }

  /**
   * Monthly deductible / non-deductible totals (last N months) for the bar chart.
   * Uses line_item level — the most accurate source.
   */
  public static function getMonthlyBreakdown(int $months = 12): array {
    $rows = [];
    for ($i = $months - 1; $i >= 0; $i--) {
      $start = date('Y-m-01', strtotime("-{$i} months"));
      $end   = date('Y-m-t',  strtotime("-{$i} months"));
      $label = date('M Y',    strtotime($start));

      $row = CRM_Core_DAO::executeQuery(
        "SELECT
           COALESCE(SUM(li.line_total - li.non_deductible_amount), 0) AS deductible,
           COALESCE(SUM(li.non_deductible_amount), 0)                 AS non_deductible
         FROM civicrm_contribution c
         JOIN civicrm_line_item li ON li.contribution_id = c.id
         WHERE c.is_test = 0
           AND c.receive_date BETWEEN %1 AND %2",
        [
          1 => [$start . ' 00:00:00', 'String'],
          2 => [$end   . ' 23:59:59', 'String'],
        ]
      )->fetchAll();

      $rows[] = [
        'label'          => $label,
        'deductible'     => (float) ($row[0]['deductible']     ?? 0),
        'non_deductible' => (float) ($row[0]['non_deductible'] ?? 0),
      ];
    }
    return $rows;
  }

  // -----------------------------------------------------------------------

  /**
   * Build the contribution-level WHERE clause.
   * $tableAlias is 'c' by default; use when the primary table is civicrm_line_item
   * and civicrm_contribution is joined as 'c'.
   */
  private static function buildContribWhere($dateFrom, $dateTo, string $tableAlias = 'c'): array {
    $conds  = ["{$tableAlias}.is_test = 0"];
    $params = [];
    $i      = 1;
    if ($dateFrom) {
      $conds[]    = "{$tableAlias}.receive_date >= %{$i}";
      $params[$i++] = [$dateFrom . ' 00:00:00', 'String'];
    }
    if ($dateTo) {
      $conds[]    = "{$tableAlias}.receive_date <= %{$i}";
      $params[$i++] = [$dateTo . ' 23:59:59', 'String'];
    }
    return [implode(' AND ', $conds), $params];
  }

}
