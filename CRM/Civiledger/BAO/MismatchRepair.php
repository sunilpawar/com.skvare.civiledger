<?php
/**
 * CiviLedger - Feature: Bulk Mismatch Auto-Repair
 *
 * Provides "Suggest Fix" logic per mismatch type and executes safe repairs.
 * Rule:
 *   - line_item mismatch  → offer to recalculate line_total from contribution.total_amount
 *   - financial_item mismatch → offer to rebuild fi.amount from line_item.line_total
 *   - trxn mismatch → flag for manual review only (never auto-fix)
 */
class CRM_Civiledger_BAO_MismatchRepair {

  /**
   * Analyse a mismatch row and return suggested actions.
   *
   * @param array $row  Row from MismatchDetector::detect()
   * @return array  Keyed by mismatch type, each with: fixable, action, label, warning
   */
  public static function suggestFix(array $row): array {
    $cid          = (int) $row['contribution_id'];
    $suggestions  = [];

    // ── Line item mismatch ──────────────────────────────────────────────────
    if ($row['line_item_diff'] > 0.01) {
      $lineItemCount = (int) CRM_Core_DAO::singleValueQuery(
        "SELECT COUNT(*) FROM civicrm_line_item WHERE contribution_id = %1",
        [1 => [$cid, 'Integer']]
      );
      if ($lineItemCount === 1) {
        $suggestions['line_items'] = [
          'fixable' => TRUE,
          'action'  => 'repair_line_items',
          'label'   => ts('Recalculate line item from contribution total'),
          'warning' => ts('Sets line_total = unit_price = %1 on the single line item.',
            [1 => CRM_Utils_Money::format($row['contribution_amount'])]),
        ];
      }
      else {
        $suggestions['line_items'] = [
          'fixable' => FALSE,
          'action'  => 'manual_review',
          'label'   => ts('Manual review required'),
          'warning' => ts('%1 line items found — automatic recalculation is not safe for multi-line contributions.', [1 => $lineItemCount]),
        ];
      }
    }

    // ── Financial item mismatch ─────────────────────────────────────────────
    if ($row['financial_item_diff'] > 0.01) {
      $suggestions['financial_items'] = [
        'fixable' => TRUE,
        'action'  => 'repair_financial_items',
        'label'   => ts('Rebuild financial items from line items'),
        'warning' => ts('Sets each financial_item.amount to match its linked line_item.line_total.'),
      ];
    }

    // ── Transaction mismatch ────────────────────────────────────────────────
    if ($row['trxn_diff'] > 0.01) {
      $suggestions['trxn'] = [
        'fixable' => FALSE,
        'action'  => 'manual_review',
        'label'   => ts('Manual review required'),
        'warning' => ts('Payment transaction totals differ from contribution amount. This usually indicates a genuine partial payment or refund. Use the Account Correction Tool to investigate.'),
      ];
    }

    return $suggestions;
  }

  /**
   * Repair: set line_total = unit_price = contribution.total_amount for a
   * single-line-item contribution.
   *
   * @return array ['success' => bool, 'error' => string]
   */
  public static function repairLineItems(int $contributionId): array {
    $contribution = CRM_Core_DAO::executeQuery(
      "SELECT total_amount, currency FROM civicrm_contribution WHERE id = %1",
      [1 => [$contributionId, 'Integer']]
    )->fetchRow();

    if (!$contribution) {
      return ['success' => FALSE, 'error' => ts('Contribution not found.')];
    }

    $lineItemCount = (int) CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_line_item WHERE contribution_id = %1",
      [1 => [$contributionId, 'Integer']]
    );
    if ($lineItemCount !== 1) {
      return ['success' => FALSE, 'error' => ts('Repair aborted: expected exactly 1 line item, found %1.', [1 => $lineItemCount])];
    }

    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_line_item
      SET line_total = %1, unit_price = %1
      WHERE contribution_id = %2
    ", [
      1 => [(float) $contribution->total_amount, 'Float'],
      2 => [$contributionId, 'Integer'],
    ]);

    CRM_Civiledger_BAO_Utils::logAction(
      'mismatch_repair_line_items',
      $contributionId,
      "Set line_total = unit_price = {$contribution->total_amount}",
      CRM_Core_Session::getLoggedInContactID()
    );

    return ['success' => TRUE];
  }

  /**
   * Repair: set financial_item.amount = line_item.line_total for all
   * financial items linked to this contribution's line items.
   *
   * @return array ['success' => bool, 'rows_updated' => int, 'error' => string]
   */
  public static function repairFinancialItems(int $contributionId): array {
    $contribution = CRM_Core_DAO::executeQuery(
      "SELECT id FROM civicrm_contribution WHERE id = %1",
      [1 => [$contributionId, 'Integer']]
    )->fetchRow();

    if (!$contribution) {
      return ['success' => FALSE, 'error' => ts('Contribution not found.')];
    }

    $dao = CRM_Core_DAO::executeQuery("
      UPDATE civicrm_financial_item fi
      JOIN civicrm_line_item li
        ON fi.entity_table = 'civicrm_line_item' AND fi.entity_id = li.id
      SET fi.amount = li.line_total
      WHERE li.contribution_id = %1
    ", [1 => [$contributionId, 'Integer']]);

    $updated = $dao->affectedRows();

    CRM_Civiledger_BAO_Utils::logAction(
      'mismatch_repair_financial_items',
      $contributionId,
      "Rebuilt {$updated} financial item(s) from line_total values",
      CRM_Core_Session::getLoggedInContactID()
    );

    return ['success' => TRUE, 'rows_updated' => $updated];
  }

}
