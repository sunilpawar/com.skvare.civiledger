<?php
/**
 * CiviLedger - Feature 3: Audit Trail Page
 */
class CRM_Civiledger_Page_AuditTrail extends CRM_Core_Page {

  public function run() {
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');
    CRM_Utils_System::setTitle(ts('CiviLedger — Financial Audit Trail'));

    $contributionId = CRM_Utils_Request::retrieve('contribution_id', 'Positive');
    $trail = NULL;
    $contributions = [];

    if ($contributionId) {
      $trail = CRM_Civiledger_BAO_AuditTrail::getTrail((int) $contributionId);
    }
    else {
      // Show search form + recent contributions
      $params = [
        'date_from' => CRM_Utils_Request::retrieve('date_from', 'String'),
        'date_to' => CRM_Utils_Request::retrieve('date_to', 'String'),
        'contact_id' => CRM_Utils_Request::retrieve('contact_id', 'Positive'),
        'limit' => 25,
      ];
      if (!empty(array_filter($params))) {
        $contributions = CRM_Civiledger_BAO_AuditTrail::searchContributions($params);
      }
    }

    $correctionUrl = CRM_Utils_System::url('civicrm/civiledger/account-correction');
    $this->assign('contributionId', $contributionId);
    $this->assign('chain', $trail);
    $this->assign('contributions', $contributions);
    $this->assign('correctionUrl', $correctionUrl);

    parent::run();
  }
}
