<?php
/**
 * CiviLedger - Feature 2: Chain Repair Form
 */
class CRM_Civiledger_Form_ChainRepair extends CRM_Core_Form {

  public function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('CiviLedger — Financial Chain Repair'));

    $brokenIds = CRM_Civiledger_BAO_IntegrityChecker::getBrokenContributionIds(200);
    $this->assign('brokenCount', count($brokenIds));
    $this->assign('brokenIds',   array_slice($brokenIds, 0, 10)); // preview first 10

    $this->add('text', 'contribution_ids', ts('Contribution IDs (comma-separated, or leave blank to repair all)'));
    $this->add('select', 'limit', ts('Max contributions to repair'), [
      50 => 50, 100 => 100, 200 => 200, 500 => 500,
    ]);
    $this->addButtons([
      ['type' => 'submit', 'name' => ts('Run Repair'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => ts('Cancel')],
    ]);
  }

  public function postProcess() {
    $values = $this->exportValues();
    $result = [];

    if (!empty($values['contribution_ids'])) {
      $ids = array_map('intval', array_filter(array_map('trim', explode(',', $values['contribution_ids']))));
      $result = CRM_Civiledger_BAO_ChainRepair::repairContributions($ids);
    }
    else {
      $limit  = (int) ($values['limit'] ?? 100);
      $result = CRM_Civiledger_BAO_ChainRepair::repairAll($limit);
    }

    $msg = "Repaired {$result['repaired']} contribution(s).";
    if (!empty($result['errors'])) {
      $msg .= ' Errors: ' . implode('; ', $result['errors']);
    }

    CRM_Core_Session::setStatus($msg, ts('Chain Repair'), empty($result['errors']) ? 'success' : 'warning');
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/civiledger/chain-repair', 'reset=1'));
  }
}
