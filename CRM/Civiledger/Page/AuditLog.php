<?php
/**
 * CiviLedger — Audit Log Page
 *
 * Displays the hash-chained central audit log and allows reviewers to verify
 * chain integrity.  All write operations (repair, correction, period lock)
 * write to this log via CRM_Civiledger_BAO_AuditLog::record().
 *
 * URL: /civicrm/civiledger/audit-log
 */
class CRM_Civiledger_Page_AuditLog extends CRM_Core_Page {

  const PAGE_SIZE = 50;

  public function run() {
    CRM_Utils_System::setTitle(ts('CiviLedger — Audit Log'));
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css');

    $dateFrom  = CRM_Utils_Request::retrieve('date_from',  'String') ?: '';
    $dateTo    = CRM_Utils_Request::retrieve('date_to',    'String') ?: '';
    $eventType = CRM_Utils_Request::retrieve('event_type', 'String') ?: '';
    $page      = max(1, (int) CRM_Utils_Request::retrieve('page', 'Positive') ?: 1);
    $verify    = (bool) CRM_Utils_Request::retrieve('verify', 'Boolean');

    $filters = array_filter([
      'event_type' => $eventType,
      'date_from'  => $dateFrom,
      'date_to'    => $dateTo,
    ]);

    $offset  = ($page - 1) * self::PAGE_SIZE;
    $entries = CRM_Civiledger_BAO_AuditLog::getEntries($filters, self::PAGE_SIZE, $offset);
    $total   = CRM_Civiledger_BAO_AuditLog::getTotal($filters);

    // Decode the JSON detail column for the template
    foreach ($entries as &$entry) {
      $entry['detail_decoded'] = $entry['detail']
        ? json_decode($entry['detail'], TRUE)
        : [];
    }
    unset($entry);

    $chainResult = NULL;
    if ($verify) {
      $chainResult = CRM_Civiledger_BAO_AuditLog::verifyChain();
    }

    $this->assign('entries',      $entries);
    $this->assign('total',        $total);
    $this->assign('page',         $page);
    $this->assign('pageSize',     self::PAGE_SIZE);
    $this->assign('hasMore',      ($offset + self::PAGE_SIZE) < $total);
    $this->assign('hasPrev',      $page > 1);
    $this->assign('dateFrom',     $dateFrom);
    $this->assign('dateTo',       $dateTo);
    $this->assign('eventType',    $eventType);
    $this->assign('eventTypes',   CRM_Civiledger_BAO_AuditLog::getEventTypes());
    $this->assign('chainResult',  $chainResult);
    $this->assign('verifyUrl',    CRM_Utils_System::url('civicrm/civiledger/audit-log',
      "reset=1&verify=1&date_from={$dateFrom}&date_to={$dateTo}&event_type={$eventType}"));
    $this->assign('cms_type',     CIVICRM_UF);

    parent::run();
  }

}
