<?php
/**
 * CiviLedger - Donor Cohort Retention Page
 *
 * @package com.skvare.civiledger
 */
class CRM_Civiledger_Page_CohortRetention extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(ts('Donor Cohort Retention'));

    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');

    $filters = [
      'cohort_from'       => CRM_Utils_Request::retrieve('cohort_from',       'String')  ?: date('Y-m', strtotime('-18 months')),
      'cohort_to'         => CRM_Utils_Request::retrieve('cohort_to',         'String')  ?: date('Y-m', strtotime('-2 months')),
      'max_months'        => CRM_Utils_Request::retrieve('max_months',        'Integer') ?: 12,
      'financial_type_id' => CRM_Utils_Request::retrieve('financial_type_id', 'Integer') ?: '',
    ];

    $data = CRM_Civiledger_BAO_CohortRetention::buildMatrix($filters);

    $financialTypes = CRM_Contribute_BAO_Contribution::buildOptions('financial_type_id');

    $this->assign('filters',          $filters);
    $this->assign('matrix',           $data['matrix']);
    $this->assign('maxMonths',        $data['max_months']);
    $this->assign('avgByMonth',       $data['avg_by_month']);
    $this->assign('totalDonors',      $data['total_donors']);
    $this->assign('secondGiftRate',   $data['second_gift_rate']);
    $this->assign('thirdMonthRate',   $data['third_month_rate']);
    $this->assign('yearRate',         $data['year_rate']);
    $this->assign('bestCohort',       $data['best_cohort']);
    $this->assign('bestCohortRate',   $data['best_cohort_rate']);
    $this->assign('financialTypes',   $financialTypes);
    $this->assign('monthOffsets',     range(0, $data['max_months']));
    $this->assign('cms_type',         CIVICRM_UF);

    parent::run();
  }

}
