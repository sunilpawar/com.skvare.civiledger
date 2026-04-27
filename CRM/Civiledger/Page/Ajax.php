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

      default:
        CRM_Utils_JSON::output(['error' => 'Unknown action']);
    }
  }

}
