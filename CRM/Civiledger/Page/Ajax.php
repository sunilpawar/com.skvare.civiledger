<?php
/**
 * Ajax handler for CiviLedger actions.
 */
class CRM_Civiledger_Page_Ajax extends CRM_Core_Page {

  public function run() {
    $action = CRM_Utils_Request::retrieve('op', 'String');
    switch ($action) {
      case 'repair_contribution':
        $cid = (int) CRM_Utils_Request::retrieve('cid', 'Integer');
        $result = CRM_Civiledger_BAO_RepairTool::repairContribution($cid);
        CRM_Utils_JSON::output($result);
        break;

      case 'search_contributions':
        $term = CRM_Utils_Type::escape(
          CRM_Utils_Request::retrieve('term', 'String') ?? '', 'String'
        );
        $rows = CRM_Core_DAO::executeQuery("
          SELECT c.id, c.total_amount, c.receive_date,
                 CONCAT(ct.first_name, ' ', ct.last_name) AS contact_name
          FROM civicrm_contribution c
          LEFT JOIN civicrm_contact ct ON ct.id = c.contact_id
          WHERE c.id LIKE '%{$term}%'
             OR ct.first_name LIKE '%{$term}%'
             OR ct.last_name LIKE '%{$term}%'
          LIMIT 20
        ")->fetchAll();
        CRM_Utils_JSON::output(['rows' => $rows]);
        break;

      case 'repair_mismatch_line_items':
        $cid    = (int) CRM_Utils_Request::retrieve('cid', 'Integer');
        $result = CRM_Civiledger_BAO_MismatchRepair::repairLineItems($cid);
        CRM_Utils_JSON::output($result);
        break;

      case 'repair_mismatch_financial_items':
        $cid    = (int) CRM_Utils_Request::retrieve('cid', 'Integer');
        $result = CRM_Civiledger_BAO_MismatchRepair::repairFinancialItems($cid);
        CRM_Utils_JSON::output($result);
        break;

      case 'cancel_duplicate_payment':
        $cid = (int) CRM_Utils_Request::retrieve('cid', 'Integer');
        if (!$cid) {
          CRM_Utils_JSON::output(['success' => FALSE, 'message' => 'Missing cid.']);
          break;
        }
        $result = CRM_Civiledger_BAO_DuplicatePaymentDetector::cancelContribution($cid);
        CRM_Utils_JSON::output($result);
        break;

      case 'delete_duplicate_trxn':
        $ftId           = (int) CRM_Utils_Request::retrieve('ft_id',           'Integer');
        $contributionId = (int) CRM_Utils_Request::retrieve('contribution_id', 'Integer');
        if (!$ftId || !$contributionId) {
          CRM_Utils_JSON::output(['success' => FALSE, 'message' => 'Missing ft_id or contribution_id.']);
          break;
        }
        $result = CRM_Civiledger_BAO_DuplicateFinancialTrxn::deleteDuplicateTrxn($ftId, $contributionId);
        CRM_Utils_JSON::output($result);
        break;

      case 'refund_duplicate_payment':
        $cid = (int) CRM_Utils_Request::retrieve('cid', 'Integer');
        if (!$cid) {
          CRM_Utils_JSON::output(['success' => FALSE, 'message' => 'Missing cid.']);
          break;
        }
        $result = CRM_Civiledger_BAO_DuplicatePaymentDetector::refundContribution($cid);
        CRM_Utils_JSON::output($result);
        break;

      case 'delete_financial_item':
        $fiId  = (int) CRM_Utils_Request::retrieve('fi_id',  'Integer');
        $cid   = (int) CRM_Utils_Request::retrieve('cid',    'Integer');
        if (!$fiId || !$cid) {
          CRM_Utils_JSON::output(['success' => FALSE, 'message' => 'Missing fi_id or cid.']);
          break;
        }
        $result = CRM_Civiledger_BAO_AuditTrail::deleteFinancialItem($fiId, $cid);
        CRM_Utils_JSON::output($result);
        break;

      case 'mismatch_detail':
        $cid = (int) CRM_Utils_Request::retrieve('cid', 'Integer');
        if (!$cid) {
          CRM_Utils_JSON::output(['html' => '<em>Missing contribution ID.</em>']);
          break;
        }
        $detail = CRM_Civiledger_BAO_MismatchDetector::getDetail($cid);
        if (empty($detail['found'])) {
          CRM_Utils_JSON::output(['html' => '<em>Contribution #' . $cid . ' not found.</em>']);
          break;
        }

        $c         = $detail['contribution'];
        $contrib   = (float) $c['total_amount'];
        $liTotal   = array_sum(array_column($detail['line_items'], 'line_total'));
        $fiTotal   = array_sum(array_column($detail['fi_items'],   'amount'));
        $trxnTotal = 0;
        foreach ($detail['transactions'] as $t) {
          if ($t['is_payment']) {
            $trxnTotal += (float) $t['total_amount'];
          }
        }

        $fmt = function ($n) { return '$' . number_format((float) $n, 2); };
        $bad = function ($val, $ref) { return abs((float) $val - (float) $ref) > 0.01; };
        $sc  = function ($val, $ref) use ($bad) {
          return $bad($val, $ref) ? 'mmd-bad' : 'mmd-ok';
        };

        // ── Line items rows ──────────────────────────────────────────────────
        $liRows = '';
        foreach ($detail['line_items'] as $li) {
          $liRows .= '<tr>'
            . '<td>' . (int) $li['id'] . '</td>'
            . '<td>' . htmlspecialchars($li['label'] ?? '') . '</td>'
            . '<td>' . htmlspecialchars($li['financial_type'] ?? '') . '</td>'
            . '<td class="mmd-amt">' . $fmt($li['qty']) . '</td>'
            . '<td class="mmd-amt">' . $fmt($li['unit_price']) . '</td>'
            . '<td class="mmd-amt">' . $fmt($li['line_total']) . '</td>'
            . '</tr>';
        }

        // ── Financial items rows ─────────────────────────────────────────────
        $fiRows = '';
        foreach ($detail['fi_items'] as $fi) {
          $fiRows .= '<tr>'
            . '<td>' . (int) $fi['id'] . '</td>'
            . '<td>' . htmlspecialchars($fi['description'] ?? '—') . '</td>'
            . '<td>' . htmlspecialchars($fi['status_label'] ?? '—') . '</td>'
            . '<td class="mmd-amt">' . $fmt($fi['amount']) . '</td>'
            . '</tr>';
        }

        // ── Transaction rows ─────────────────────────────────────────────────
        $trxnRows = '';
        foreach ($detail['transactions'] as $t) {
          $typeTag = $t['is_payment']
            ? '<span class="mmd-tag mmd-tag-pay">payment</span>'
            : '<span class="mmd-tag mmd-tag-nonpay">non-payment</span>';
          $trxnRows .= '<tr' . ($t['is_payment'] ? '' : ' style="opacity:.65"') . '>'
            . '<td>' . (int) $t['id'] . '</td>'
            . '<td>' . htmlspecialchars($t['trxn_id'] ?? '—') . '</td>'
            . '<td>' . htmlspecialchars(substr($t['trxn_date'] ?? '', 0, 10)) . '</td>'
            . '<td>' . $typeTag . '</td>'
            . '<td class="mmd-amt">' . $fmt($t['total_amount']) . '</td>'
            . '</tr>';
        }

        $liNone   = $liRows   ?: '<tr><td colspan="6" class="mmd-none">None</td></tr>';
        $fiNone   = $fiRows   ?: '<tr><td colspan="4" class="mmd-none">None</td></tr>';
        $trxnNone = $trxnRows ?: '<tr><td colspan="5" class="mmd-none">None</td></tr>';

        $html = '
<div class="mmd-detail-wrap">
  <div class="mmd-detail-header">
    <span class="mmd-detail-label">Contribution #' . $cid . '</span>
    <span class="mmd-detail-ref">Contribution: <strong>' . $fmt($contrib) . '</strong></span>
    <span class="mmd-detail-sum ' . $sc($liTotal,   $contrib) . '">Line Items: '     . $fmt($liTotal)   . '</span>
    <span class="mmd-detail-sum ' . $sc($fiTotal,   $contrib) . '">Financial Items: '. $fmt($fiTotal)   . '</span>
    <span class="mmd-detail-sum ' . $sc($trxnTotal, $contrib) . '">Payments: '       . $fmt($trxnTotal) . '</span>
  </div>
  <div class="mmd-detail-tables">

    <div class="mmd-detail-block">
      <div class="mmd-detail-block-title">Line Items (' . count($detail['line_items']) . ')</div>
      <table class="mmd-inner-table">
        <thead><tr><th>#</th><th>Label</th><th>Financial Type</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr></thead>
        <tbody>' . $liNone . '</tbody>
        <tfoot><tr><td colspan="5">Sum</td>
          <td class="mmd-amt ' . $sc($liTotal, $contrib) . '">' . $fmt($liTotal) . '</td></tr></tfoot>
      </table>
    </div>

    <div class="mmd-detail-block">
      <div class="mmd-detail-block-title">Financial Items (' . count($detail['fi_items']) . ')</div>
      <table class="mmd-inner-table">
        <thead><tr><th>#</th><th>Description</th><th>Status</th><th>Amount</th></tr></thead>
        <tbody>' . $fiNone . '</tbody>
        <tfoot><tr><td colspan="3">Sum</td>
          <td class="mmd-amt ' . $sc($fiTotal, $contrib) . '">' . $fmt($fiTotal) . '</td></tr></tfoot>
      </table>
    </div>

    <div class="mmd-detail-block">
      <div class="mmd-detail-block-title">Financial Transactions (' . count($detail['transactions']) . ')</div>
      <table class="mmd-inner-table">
        <thead><tr><th>#</th><th>Trxn ID</th><th>Date</th><th>Type</th><th>Amount</th></tr></thead>
        <tbody>' . $trxnNone . '</tbody>
        <tfoot><tr><td colspan="4">Payment sum (is_payment=1)</td>
          <td class="mmd-amt ' . $sc($trxnTotal, $contrib) . '">' . $fmt($trxnTotal) . '</td></tr></tfoot>
      </table>
    </div>

  </div>
</div>';

        CRM_Utils_JSON::output(['html' => $html]);
        break;

      default:
        CRM_Utils_JSON::output(['error' => 'Unknown action']);
    }
  }

}
