<?php
/**
 * CiviLedger - Page: Financial Period Close / Lock
 */
class CRM_Civiledger_Page_PeriodClose extends CRM_Core_Page {

  public function run() {
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');
    CRM_Utils_System::setTitle(ts('CiviLedger — Financial Period Close'));

    $action  = CRM_Utils_Request::retrieve('operation', 'String') ?? '';
    $userId  = (int) CRM_Core_Session::getLoggedInContactID();
    if ($action === 'lock') {
      $this->handleLock($userId);
      return;
    }
    if ($action === 'unlock') {
      $this->handleUnlock($userId);
      return;
    }

    $this->assign('activeLock',   CRM_Civiledger_BAO_PeriodClose::getActiveLock());
    $this->assign('lockHistory',  CRM_Civiledger_BAO_PeriodClose::getLockHistory());
    $this->assign('todayDate',    date('Y-m-d'));
    $this->assign('cms_type', CIVICRM_UF);
    parent::run();
  }

  private function handleLock(int $userId): void {
    $lockDate = CRM_Utils_Request::retrieve('lock_date', 'String') ?? '';
    $reason   = CRM_Utils_Request::retrieve('lock_reason', 'String') ?? '';
    $result = CRM_Civiledger_BAO_PeriodClose::lockPeriod($lockDate, $reason, $userId);

    if ($result['success']) {
      CRM_Core_Session::setStatus(
        ts('Period locked. All transactions before %1 are now protected from correction.', [1 => $lockDate]),
        ts('Period Locked'), 'success'
      );
    }
    else {
      CRM_Core_Session::setStatus($result['error'], ts('Error'), 'error');
    }
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/civiledger/period-close', 'reset=1'));
  }

  private function handleUnlock(int $userId): void {
    $lockId = (int) CRM_Utils_Request::retrieve('lock_id', 'Integer');
    $reason = CRM_Utils_Request::retrieve('unlock_reason', 'String') ?? '';

    $result = CRM_Civiledger_BAO_PeriodClose::unlockPeriod($lockId, $reason, $userId);

    if ($result['success']) {
      CRM_Core_Session::setStatus(ts('Period unlocked.'), ts('Unlocked'), 'success');
    }
    else {
      CRM_Core_Session::setStatus($result['error'], ts('Error'), 'error');
    }
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/civiledger/period-close', 'reset=1'));
  }

}
