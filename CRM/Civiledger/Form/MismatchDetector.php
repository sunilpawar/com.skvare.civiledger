<?php
/**
 * CiviLedger - Feature 5: Amount Mismatch Detector Form
 */
class CRM_Civiledger_Form_MismatchDetector extends CRM_Core_Form {

  public function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('CiviLedger — Amount Mismatch Detector'));

    $mismatchType = CRM_Utils_Request::retrieve('mismatch_type', 'String') ?: 'all';
    $counts       = CRM_Civiledger_BAO_MismatchDetector::getMismatchCounts();
    $mismatches   = CRM_Civiledger_BAO_MismatchDetector::detectMismatches(50);

    $this->assign('mismatchType', $mismatchType);
    $this->assign('counts',       $counts);
    $this->assign('mismatches',   $mismatches);

    $this->addButtons([['type' => 'cancel', 'name' => ts('Back')]]);
  }

  public function postProcess() {}
}
