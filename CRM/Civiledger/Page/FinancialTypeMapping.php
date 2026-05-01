<?php
/**
 * CiviLedger — Financial Type Account Mapping Validator Page
 * URL: /civicrm/civiledger/financial-type-mapping
 */
class CRM_Civiledger_Page_FinancialTypeMapping extends CRM_Core_Page {

  public function run(): void {
    CRM_Utils_System::setTitle(ts('CiviLedger — Financial Type Account Mapping'));
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css');

    $filter  = CRM_Utils_Request::retrieve('filter', 'String') ?: 'all';
    $mapping = CRM_Civiledger_BAO_FinancialTypeMapping::getMapping();
    $summary = CRM_Civiledger_BAO_FinancialTypeMapping::getSummary();

    switch ($filter) {
      case 'issues':
        $mapping = array_values(array_filter($mapping, fn($t) => $t['status'] !== 'ok'));
        break;
      case 'active':
        $mapping = array_values(array_filter($mapping, fn($t) => $t['is_active']));
        break;
      case 'inactive':
        $mapping = array_values(array_filter($mapping, fn($t) => !$t['is_active']));
        break;
    }

    $this->assign('mapping',           $mapping);
    $this->assign('summary',           $summary);
    $this->assign('filter',            $filter);
    $this->assign('accountTypeLegend', CRM_Civiledger_BAO_FinancialTypeMapping::getAccountTypesLegend());
    $this->assign('acctUrl',           CRM_Utils_System::url('civicrm/admin/financial/financialAccount', 'reset=1'));
    $this->assign('ftListUrl',         CRM_Utils_System::url('civicrm/admin/financial/financialType', 'reset=1'));
    $this->assign('cms_type',          CIVICRM_UF);

    parent::run();
  }

}
