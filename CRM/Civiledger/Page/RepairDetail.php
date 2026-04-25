<?php
/**
 * CiviLedger — Repair Detail Page
 *
 * Dedicated single-contribution repair page that shows:
 *   1. Contribution header
 *   2. Pre-repair chain analysis (what is missing before we start)
 *   3. Step-by-step repair execution log (fixed / skipped / warning / error)
 *   4. Post-repair chain validation (did every link get created?)
 *
 * URL:  /civicrm/civiledger/repair-detail?cid=XXXX
 * Run:  /civicrm/civiledger/repair-detail?cid=XXXX&action=run
 */
class CRM_Civiledger_Page_RepairDetail extends CRM_Core_Page {

  public function run() {
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');

    $contributionId = (int) CRM_Utils_Request::retrieve('cid', 'Integer');
    if (!$contributionId) {
      CRM_Core_Error::statusBounce(
        ts('No contribution ID specified.'),
        CRM_Utils_System::url('civicrm/civiledger/chain-repair', 'reset=1')
      );
    }

    $contribution = $this->loadContribution($contributionId);
    if (!$contribution) {
      CRM_Core_Error::statusBounce(
        ts('Contribution #%1 not found.', [1 => $contributionId]),
        CRM_Utils_System::url('civicrm/civiledger/chain-repair', 'reset=1')
      );
    }

    CRM_Utils_System::setTitle(ts('CiviLedger — Repair Contribution #%1', [1 => $contributionId]));

    $preChain = $this->analyzeChain($contributionId);
    $action = CRM_Utils_Request::retrieve('operation', 'String');
    $repairRan = FALSE;
    $repairLog = [];
    $logSummary = [];
    $postChain = NULL;

    if ($action == 'run') {
      // Execute repair and capture the detailed log
      $repairLog = CRM_Civiledger_BAO_ChainRepair::repairContribution($contributionId);
      $postChain = $this->analyzeChain($contributionId);
      $repairRan = TRUE;
      $logSummary = [
        'fixed' => count(array_filter($repairLog, fn($l) => isset($l['fixed']))),
        'skipped' => count(array_filter($repairLog, fn($l) => isset($l['skip']))),
        'warning' => count(array_filter($repairLog, fn($l) => isset($l['warning']))),
        'error' => count(array_filter($repairLog, fn($l) => isset($l['error']))),
        'info' => count(array_filter($repairLog, fn($l) => isset($l['info']))),
      ];
    }

    // URLs
    $runUrl = CRM_Utils_System::url('civicrm/civiledger/repair-detail',
      "cid={$contributionId}&operation=run");
    $backUrl = CRM_Utils_System::url('civicrm/civiledger/chain-repair', 'reset=1');
    $auditUrl = CRM_Utils_System::url('civicrm/civiledger/audit-trail',
      "reset=1&contribution_id={$contributionId}");
    $contribUrl = CRM_Civiledger_BAO_Utils::getContributionUrl($contributionId);

    $this->assign('contributionId', $contributionId);
    $this->assign('contribution', $contribution);
    $this->assign('preChain', $preChain);
    $this->assign('repairRan', $repairRan);
    $this->assign('repairLog', $repairLog);
    $this->assign('logSummary', $logSummary);
    $this->assign('postChain', $postChain);
    $this->assign('runUrl', $runUrl);
    $this->assign('backUrl', $backUrl);
    $this->assign('auditUrl', $auditUrl);
    $this->assign('contribUrl', $contribUrl);
    $this->assign('cms_type', CIVICRM_UF);
    $this->assign('repairHistory', $this->loadRepairHistory($contributionId));

    parent::run();
  }

  // -----------------------------------------------------------------------
  // Private helpers
  // -----------------------------------------------------------------------

  /**
   * Load repair history for this contribution, grouped by repair run (repaired_at timestamp).
   */
  private function loadRepairHistory(int $contributionId): array {
    try {
      $rows = CRM_Core_DAO::executeQuery(
        "SELECT rl.id, rl.action, rl.message, rl.repaired_by, rl.repaired_at,
                c.display_name AS repaired_by_name
         FROM civicrm_civiledger_repair_log rl
         LEFT JOIN civicrm_contact c ON c.id = rl.repaired_by
         WHERE rl.contribution_id = %1
         ORDER BY rl.repaired_at DESC, rl.id ASC",
        [1 => [$contributionId, 'Integer']]
      )->fetchAll();
    }
    catch (Exception $e) {
      return [];
    }

    $runs = [];
    foreach ($rows as $row) {
      $key = $row['repaired_at'];
      if (!isset($runs[$key])) {
        $runs[$key] = [
          'repaired_at'      => $row['repaired_at'],
          'repaired_by_name' => $row['repaired_by_name'],
          'entries'          => [],
          'counts'           => ['fixed' => 0, 'skip' => 0, 'warning' => 0, 'error' => 0, 'info' => 0],
        ];
      }
      $runs[$key]['entries'][] = $row;
      if (array_key_exists($row['action'], $runs[$key]['counts'])) {
        $runs[$key]['counts'][$row['action']]++;
      }
    }

    return array_values($runs);
  }

  /**
   * Load full contribution details for the header card.
   */
  private function loadContribution(int $id): ?array {
    $sql = "
      SELECT
        c.id, c.contact_id, c.total_amount, c.fee_amount, c.net_amount,
        c.currency, c.receive_date, c.contribution_status_id,
        c.trxn_id, c.financial_type_id, c.source,
        con.display_name AS contact_name,
        ft.name          AS financial_type_name,
        cs.label         AS status_label,
        pi.label         AS payment_instrument
      FROM civicrm_contribution c
      LEFT JOIN civicrm_contact con ON con.id = c.contact_id
      LEFT JOIN civicrm_financial_type ft ON ft.id = c.financial_type_id
      LEFT JOIN civicrm_option_value cs
        ON cs.value = c.contribution_status_id
        AND cs.option_group_id = (
          SELECT id FROM civicrm_option_group WHERE name = 'contribution_status'
        )
      LEFT JOIN civicrm_option_value pi
        ON pi.value = c.payment_instrument_id
        AND pi.option_group_id = (
          SELECT id FROM civicrm_option_group WHERE name = 'payment_instrument'
        )
      WHERE c.id = %1
    ";
    $rows = CRM_Core_DAO::executeQuery($sql, [1 => [$id, 'Integer']])->fetchAll();
    return !empty($rows) ? $rows[0] : NULL;
  }

  /**
   * Snapshot the full chain state for a contribution.
   * Called both before and after repair so the template can compare.
   *
   * Returns an array with keys:
   *   line_items[]            – rows from civicrm_line_item
   *   financial_items[]       – rows from civicrm_financial_item (joined to line item)
   *   financial_trxns[]       – rows from civicrm_financial_trxn linked to this contribution
   *   eft_contribution        – bool: does entity_financial_trxn row for contribution exist?
   *   eft_by_fi[fi_id]        – bool map: does each financial_item have an EFT row?
   *   amount_match            – bool: do trxn totals reconcile with contribution.total_amount?
   *   checks[]                – named pass/fail flags (used for the checklist display)
   */
  private function analyzeChain(int $contributionId): array {
    $chain = [
      'line_items' => [],
      'financial_items' => [],
      'financial_trxns' => [],
      'eft_contribution' => FALSE,
      'eft_by_fi' => [],
      'amount_match' => FALSE,
      'checks' => [],
    ];

    // --- Line Items ---------------------------------------------------------
    $chain['line_items'] = CRM_Core_DAO::executeQuery(
      "SELECT li.id, li.label, li.line_total, li.qty, li.financial_type_id,
              ft.name AS financial_type_name
       FROM civicrm_line_item li
       LEFT JOIN civicrm_financial_type ft ON ft.id = li.financial_type_id
       WHERE li.contribution_id = %1
       ORDER BY li.id",
      [1 => [$contributionId, 'Integer']]
    )->fetchAll();

    // --- Financial Items (linked via line items) ----------------------------
    $chain['financial_items'] = CRM_Core_DAO::executeQuery(
      "SELECT fi.id, fi.amount, fi.currency, fi.status_id,
              fa.name AS account_name,
              li.id   AS line_item_id, li.label AS line_item_label
       FROM civicrm_financial_item fi
       INNER JOIN civicrm_line_item li
         ON li.id = fi.entity_id AND fi.entity_table = 'civicrm_line_item'
       LEFT JOIN civicrm_financial_account fa ON fa.id = fi.financial_account_id
       WHERE li.contribution_id = %1
       ORDER BY fi.id",
      [1 => [$contributionId, 'Integer']]
    )->fetchAll();

    // --- Financial Transactions (linked via EFT on contribution) -----------
    $chain['financial_trxns'] = CRM_Core_DAO::executeQuery(
      "SELECT ft.id, ft.total_amount, ft.currency, ft.trxn_date,
              ft.is_payment, ft.trxn_id AS processor_ref,
              fa_from.name AS from_account,
              fa_to.name   AS to_account,
              eft.amount   AS eft_amount
       FROM civicrm_entity_financial_trxn eft
       INNER JOIN civicrm_financial_trxn ft ON ft.id = eft.financial_trxn_id
       LEFT JOIN civicrm_financial_account fa_from ON fa_from.id = ft.from_financial_account_id
       LEFT JOIN civicrm_financial_account fa_to   ON fa_to.id   = ft.to_financial_account_id
       WHERE eft.entity_table = 'civicrm_contribution' AND eft.entity_id = %1
       ORDER BY ft.trxn_date",
      [1 => [$contributionId, 'Integer']]
    )->fetchAll();

    // --- EFT flag for contribution ------------------------------------------
    $chain['eft_contribution'] = (bool) CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_entity_financial_trxn
       WHERE entity_table = 'civicrm_contribution' AND entity_id = %1",
      [1 => [$contributionId, 'Integer']]
    );

    // --- EFT flag per financial item ----------------------------------------
    foreach ($chain['financial_items'] as $fi) {
      $chain['eft_by_fi'][$fi['id']] = (bool) CRM_Core_DAO::singleValueQuery(
        "SELECT COUNT(*) FROM civicrm_entity_financial_trxn
         WHERE entity_table = 'civicrm_financial_item' AND entity_id = %1",
        [1 => [(int) $fi['id'], 'Integer']]
      );
    }

    // --- Amount reconciliation ----------------------------------------------
    $contribTotal = (float) CRM_Core_DAO::singleValueQuery(
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
       WHERE ft.is_payment = 1
         AND eft.entity_table = 'civicrm_contribution' AND eft.entity_id = %1",
      [1 => [$contributionId, 'Integer']]
    );

    $chain['diffs'] = [
      'line_item'      => round(abs($contribTotal - $lineItemTotal), 4),
      'financial_item' => round(abs($contribTotal - $financialItemTotal), 4),
      'trxn'           => round(abs($contribTotal - $trxnTotal), 4),
    ];
    $chain['amount_match'] = (
      $chain['diffs']['line_item'] < 0.01 &&
      $chain['diffs']['financial_item'] < 0.01 &&
      $chain['diffs']['trxn'] < 0.01
    );

    // --- Named pass/fail checks (drives checklist in template) -------------
    $allFiHaveEft = count($chain['financial_items']) > 0
      && !in_array(FALSE, $chain['eft_by_fi'], TRUE);

    $chain['checks'] = [
      'has_line_items' => count($chain['line_items']) > 0,
      'has_financial_items' => count($chain['financial_items']) > 0,
      'has_financial_trxns' => count($chain['financial_trxns']) > 0,
      'has_eft_contribution' => $chain['eft_contribution'],
      'has_eft_fi_all' => $allFiHaveEft,
      'amounts_match' => $chain['amount_match'],
    ];
    $chain['checks']['is_complete'] = !in_array(FALSE, $chain['checks'], TRUE);

    // Counts for the template
    $chain['counts'] = [
      'line_items' => count($chain['line_items']),
      'financial_items' => count($chain['financial_items']),
      'financial_trxns' => count($chain['financial_trxns']),
      'fi_with_eft' => count(array_filter($chain['eft_by_fi'])),
      'fi_total' => count($chain['financial_items']),
    ];

    return $chain;
  }

}