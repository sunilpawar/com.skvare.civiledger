<?php
/**
 * Page: Chain Repair Tool
 */
class CRM_Civiledger_Page_RepairTool extends CRM_Core_Page {

  public function run() {
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');
    CRM_Utils_System::setTitle(ts('CiviLedger — Financial Chain Repair Tool'));

    $action = CRM_Utils_Request::retrieve('action', 'String') ?? '';
    $result = NULL;

    if ($action === 'repair_one') {
      $contributionId = (int) CRM_Utils_Request::retrieve('cid', 'Integer');
      $result = CRM_Civiledger_BAO_RepairTool::repairContribution($contributionId);
      $this->assign('repairResult', $result);
    }
    elseif ($action === 'repair_batch') {
      $ids = array_map('intval', explode(',', CRM_Utils_Request::retrieve('ids', 'String') ?? ''));
      $ids = array_filter($ids);
      if ($ids) {
        $result = CRM_Civiledger_BAO_RepairTool::repairBatch($ids);
        $this->assign('batchResult', $result);
      }
    }

    // Always show broken chains so user can select what to repair.
    // Limit the result sets to the configured batch size.
    $batchSize = max(1, (int) (Civi::settings()->get('civiledger_batch_size') ?? 50));
    $broken      = array_slice(
      CRM_Civiledger_BAO_IntegrityChecker::checkMissingContributionTrxnLink(), 0, $batchSize
    );
    $brokenItems = array_slice(
      CRM_Civiledger_BAO_IntegrityChecker::checkMissingFinancialItemTrxnLink(), 0, $batchSize
    );

    $this->assign('brokenContributions', $broken);
    $this->assign('brokenItems', $brokenItems);
    $this->assign('totalBroken', count($broken) + count($brokenItems));
    $this->assign('batchSize', $batchSize);
    $this->assign('integrityUrl', CRM_Utils_System::url('civicrm/civiledger/integrity-check'));
    $this->assign('settingsUrl', CRM_Utils_System::url('civicrm/admin/civiledger/settings'));
    $this->assign('cms_type', CIVICRM_UF);

    parent::run();
  }

}
