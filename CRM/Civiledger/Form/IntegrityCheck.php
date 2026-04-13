<?php
/**
 * CiviLedger - Feature 1: Integrity Check Form
 */
class CRM_Civiledger_Form_IntegrityCheck extends CRM_Core_Form {

  public function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('CiviLedger — Financial Integrity Check'));

    $checkType = CRM_Utils_Request::retrieve('check_type', 'String') ?: 'all';
    $summary   = CRM_Civiledger_BAO_IntegrityChecker::getSummary();
    $rows      = [];

    if ($checkType === 'missing_financial_items' || $checkType === 'all') {
      $rows['missing_financial_items'] = CRM_Civiledger_BAO_IntegrityChecker::getMissingFinancialItems(50);
    }
    if ($checkType === 'missing_eft_fi' || $checkType === 'all') {
      $rows['missing_eft_fi'] = CRM_Civiledger_BAO_IntegrityChecker::getMissingEftFinancialItem(50);
    }
    if ($checkType === 'missing_line_items' || $checkType === 'all') {
      $rows['missing_line_items'] = CRM_Civiledger_BAO_IntegrityChecker::getMissingLineItems(50);
    }

    $this->assign('summary',   $summary);
    $this->assign('rows',      $rows);
    $this->assign('checkType', $checkType);

    $this->addButtons([['type' => 'cancel', 'name' => ts('Back')]]);
  }

  public function postProcess() {}
}
