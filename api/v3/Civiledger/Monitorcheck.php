<?php
/**
 * API v3 action: Civiledger.Monitorcheck
 * Called by the CiviCRM scheduled job to run integrity + mismatch checks
 * and send alert emails when issues are found.
 */
function civicrm_api3_civiledger_monitorcheck($params) {
  $result = CRM_Civiledger_BAO_MonitoringAlert::runAndAlert();
  return civicrm_api3_create_success($result, $params, 'Civiledger', 'Monitorcheck');
}

function _civicrm_api3_civiledger_monitorcheck_spec(&$spec) {}
