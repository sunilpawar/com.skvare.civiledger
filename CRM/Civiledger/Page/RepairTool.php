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
    $activeLock = CRM_Civiledger_BAO_PeriodClose::getActiveLock();

    if ($action === 'repair_one') {
      $contributionId = (int) CRM_Utils_Request::retrieve('cid', 'Integer');
      if ($activeLock && self::isContributionLocked($contributionId, $activeLock['lock_date'])) {
        $this->assign('lockedError', ts('Contribution #%1 falls within the locked period (before %2). Repair is not allowed.', [
          1 => $contributionId,
          2 => $activeLock['lock_date'],
        ]));
      }
      else {
        $result = CRM_Civiledger_BAO_RepairTool::repairContribution($contributionId);
        $this->assign('repairResult', $result);
      }
    }
    elseif ($action === 'repair_batch') {
      $ids = array_map('intval', explode(',', CRM_Utils_Request::retrieve('ids', 'String') ?? ''));
      $ids = array_filter($ids);
      if ($ids) {
        $lockedSkipped = 0;
        if ($activeLock) {
          [$ids, $lockedSkipped] = self::filterLockedIds($ids, $activeLock['lock_date']);
          if ($lockedSkipped) {
            $this->assign('lockedSkipped', $lockedSkipped);
          }
        }
        if ($ids) {
          $result = CRM_Civiledger_BAO_RepairTool::repairBatch($ids);
          $this->assign('batchResult', $result);
        }
        elseif ($lockedSkipped) {
          $this->assign('lockedError', ts('All selected contributions are within the locked period (before %1). No repairs performed.', [
            1 => $activeLock['lock_date'],
          ]));
        }
      }
    }

    $batchSize = max(1, (int) (Civi::settings()->get('civiledger_batch_size') ?? 50));
    $broken = array_slice(
      CRM_Civiledger_BAO_IntegrityChecker::checkMissingContributionTrxnLink(), 0, $batchSize
    );
    $brokenItems = array_slice(
      CRM_Civiledger_BAO_IntegrityChecker::checkMissingFinancialItemTrxnLink(), 0, $batchSize
    );

    // Mark each row as locked when it falls within the locked period.
    if ($activeLock) {
      $lockDate = $activeLock['lock_date'];
      foreach ($broken as &$row) {
        $row['is_locked'] = !empty($row['receive_date']) && substr($row['receive_date'], 0, 10) < $lockDate;
      }
      unset($row);
      foreach ($brokenItems as &$row) {
        $row['is_locked'] = !empty($row['receive_date']) && substr($row['receive_date'], 0, 10) < $lockDate;
      }
      unset($row);
    }

    $this->assign('activeLock', $activeLock);
    $this->assign('brokenContributions', $broken);
    $this->assign('brokenItems', $brokenItems);
    $this->assign('totalBroken', count($broken) + count($brokenItems));
    $this->assign('batchSize', $batchSize);
    $this->assign('integrityUrl', CRM_Utils_System::url('civicrm/civiledger/integrity-check'));
    $this->assign('settingsUrl', CRM_Utils_System::url('civicrm/admin/civiledger/settings'));
    $this->assign('periodCloseUrl', CRM_Utils_System::url('civicrm/civiledger/period-close'));
    $this->assign('cms_type', CIVICRM_UF);

    parent::run();
  }

  private static function isContributionLocked(int $contributionId, string $lockDate): bool {
    $receiveDate = CRM_Core_DAO::singleValueQuery(
      'SELECT receive_date FROM civicrm_contribution WHERE id = %1',
      [1 => [$contributionId, 'Integer']]
    );
    return $receiveDate && substr($receiveDate, 0, 10) < $lockDate;
  }

  /**
   * Split $ids into [unlocked[], lockedCount].
   */
  private static function filterLockedIds(array $ids, string $lockDate): array {
    $unlocked = [];
    $lockedCount = 0;
    foreach ($ids as $id) {
      if (self::isContributionLocked($id, $lockDate)) {
        $lockedCount++;
      }
      else {
        $unlocked[] = $id;
      }
    }
    return [$unlocked, $lockedCount];
  }

}
