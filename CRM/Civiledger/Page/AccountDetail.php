<?php
/**
 * CiviLedger - Account Detail Page
 * Shows balance summary and paginated transaction movements for a single financial account.
 */
class CRM_Civiledger_Page_AccountDetail extends CRM_Core_Page {

  const PAGE_SIZE = 50;

  public function run() {
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');

    $accountId = (int) CRM_Utils_Request::retrieve('account_id', 'Positive');
    if (!$accountId) {
      CRM_Core_Error::statusBounce(ts('No account specified.'), CRM_Utils_System::url('civicrm/civiledger/dashboard', 'reset=1'));
    }

    $dateFrom = CRM_Utils_Request::retrieve('date_from', 'String') ?: date('Y-m-01');
    $dateTo   = CRM_Utils_Request::retrieve('date_to',   'String') ?: date('Y-m-d');
    $page     = max(1, (int) CRM_Utils_Request::retrieve('page', 'Positive') ?: 1);
    $offset   = ($page - 1) * self::PAGE_SIZE;

    // Fetch the account row from the balances list (single-account filter).
    $account = $this->_getAccountBalance($accountId, $dateFrom, $dateTo);
    if (empty($account)) {
      CRM_Core_Error::statusBounce(ts('Account not found.'), CRM_Utils_System::url('civicrm/civiledger/dashboard', 'reset=1'));
    }

    CRM_Utils_System::setTitle(ts('Account Detail — %1', [1 => $account['name']]));

    $movements = CRM_Civiledger_BAO_AccountBalance::getAccountMovements(
      $accountId, $dateFrom, $dateTo, self::PAGE_SIZE, $offset
    );

    // Fetch one extra row to know whether a next page exists.
    $hasMore = count(CRM_Civiledger_BAO_AccountBalance::getAccountMovements(
      $accountId, $dateFrom, $dateTo, 1, $offset + self::PAGE_SIZE
    )) > 0;

    $this->assign('account',   $account);
    $this->assign('accountId', $accountId);
    $this->assign('dateFrom',  $dateFrom);
    $this->assign('dateTo',    $dateTo);
    $this->assign('movements', $movements);
    $this->assign('page',      $page);
    $this->assign('hasMore',   $hasMore);
    $this->assign('hasPrev',   $page > 1);
    $this->assign('pageSize',  self::PAGE_SIZE);

    parent::run();
  }

  /**
   * Return the balance row for a single account ID.
   */
  private function _getAccountBalance($accountId, $dateFrom, $dateTo) {
    $balances = CRM_Civiledger_BAO_AccountBalance::getBalances($dateFrom, $dateTo);
    foreach ($balances as $row) {
      if ((int) $row['id'] === $accountId) {
        return $row;
      }
    }
    return NULL;
  }

}
