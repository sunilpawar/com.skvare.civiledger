<?php

/**
 * CiviLedger - Financial Audit & Integrity Extension
 * Extension key: com.skvare.civiledger
 *
 * @package  com.skvare.civiledger
 * @author   Skvare <info@skvare.com>
 * @license  AGPL-3.0
 */

require_once 'civiledger.civix.php';

use CRM_Civiledger_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 */
function civiledger_civicrm_config(&$config) {
  _civiledger_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 */
function civiledger_civicrm_install() {
  _civiledger_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 */
function civiledger_civicrm_enable() {
  _civiledger_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_navigationMenu().
 * Adds CiviLedger as a top-level item in the main navigation bar.
 */
function civiledger_civicrm_navigationMenu(&$menu) {
  $civiledgerItems = [
    [
      'attributes' => [
        'label' => ts('CiviLedger Dashboard'),
        'name' => 'civiledger_dashboard',
        'url' => 'civicrm/civiledger/dashboard',
        'icon' => 'crm-i fa-tachometer',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => NULL,
        'active' => 1,
      ],
    ],
    [
      'attributes' => [
        'label' => ts('Integrity Checker'),
        'name' => 'civiledger_integrity',
        'url' => 'civicrm/civiledger/integrity-check',
        'icon' => 'crm-i fa-check-circle',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => NULL,
        'active' => 1,
      ],
    ],
    [
      'attributes' => [
        'label' => ts('Mismatch Detector'),
        'name' => 'civiledger_mismatch',
        'url' => 'civicrm/civiledger/mismatch-detector',
        'icon' => 'crm-i fa-exclamation-triangle',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => NULL,
        'active' => 1,
      ],
    ],
    [
      'attributes' => [
        'label' => ts('Duplicate Payment Detector'),
        'name' => 'civiledger_duplicate_payments',
        'url' => 'civicrm/civiledger/duplicate-payments',
        'icon' => 'crm-i fa-clone',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => NULL,
        'active' => 1,
      ],
    ],
    [
      'attributes' => [
        'label' => ts('Duplicate Financial Trxn Detector'),
        'name' => 'civiledger_duplicate_trxn',
        'url' => 'civicrm/civiledger/duplicate-trxn',
        'icon' => 'crm-i fa-copy',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => NULL,
        'active' => 1,
      ],
    ],
    [
      'attributes' => [
        'label' => ts('Account Balance Dashboard'),
        'name' => 'civiledger_balance',
        'url' => 'civicrm/civiledger/balance',
        'icon' => 'crm-i fa-balance-scale',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => NULL,
        'active' => 1,
      ],
    ],
    [
      'attributes' => [
        'label' => ts('Account Correction Tool'),
        'name' => 'civiledger_correction',
        'url' => 'civicrm/civiledger/account-correction',
        'icon' => 'crm-i fa-exchange',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => NULL,
        'active' => 1,
      ],
    ],
    [
      'attributes' => [
        'label' => ts('Period Close / Lock'),
        'name' => 'civiledger_period_close',
        'url' => 'civicrm/civiledger/period-close',
        'icon' => 'crm-i fa-lock',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => 1,
        'active' => 1,
      ],
    ],
    [
      'attributes' => [
        'label' => ts('Financial Dashboard'),
        'name' => 'civiledger_financial_dashboard',
        'url' => 'civicrm/civiledger/financial-dashboard',
        'icon' => 'crm-i fa-bar-chart',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => NULL,
        'active' => 1,
      ],
    ],
    [
      'attributes' => [
        'label' => ts('Tax Mapping'),
        'name' => 'civiledger_tax_mapping',
        'url' => 'civicrm/civiledger/tax-mapping',
        'icon' => 'crm-i fa-percent',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => NULL,
        'active' => 1,
      ],
    ],
    [
      'attributes' => [
        'label' => ts('Audit Log'),
        'name' => 'civiledger_audit_log',
        'url' => 'civicrm/civiledger/audit-log',
        'icon' => 'crm-i fa-history',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => NULL,
        'active' => 1,
      ],
    ],
    [
      'attributes' => [
        'label' => ts('Financial Type Mapping'),
        'name' => 'civiledger_ft_mapping',
        'url' => 'civicrm/civiledger/financial-type-mapping',
        'icon' => 'crm-i fa-sitemap',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => NULL,
        'active' => 1,
      ],
    ],
    [
      'attributes' => [
        'label' => ts('Settings'),
        'name' => 'civiledger_settings',
        'url' => 'civicrm/admin/civiledger/settings',
        'icon' => 'crm-i fa-cog',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => NULL,
        'active' => 1,
      ],
    ],
  ];

  // Add CiviLedger as a top-level main nav bar item.
  $parentMenu = [[
    'attributes' => [
      'label' => ts('CiviLedger'),
      'name' => 'civiledger',
      'url' => NULL,
      'icon' => 'crm-i fa-calculator',
      'permission' => 'administer CiviCRM',
      'operator' => NULL,
      'separator' => NULL,
      'active' => 1,
      'weight' => 35,
    ],
    'child' => $civiledgerItems,
  ]];
  array_splice($menu, 3, 0, $parentMenu);

  _civiledger_civix_navigationMenu($menu);
}

/**
 * Implements hook_civicrm_check().
 * Adds CiviLedger issue counts to the CiviCRM System Status page.
 * Reads cached results from the last scheduled job run — no live DB queries.
 */
function civiledger_civicrm_check(&$messages) {
  $cached  = CRM_Civiledger_BAO_MonitoringAlert::getCachedResult();
  $lastRun = CRM_Civiledger_BAO_MonitoringAlert::getLastRunTime();

  if (empty($cached)) {
    $messages[] = new CRM_Utils_Check_Message(
      'civiledger_never_run',
      ts('CiviLedger integrity monitor has never run. <a href="%1">Enable the scheduled job</a> or run it manually.',
        [1 => CRM_Utils_System::url('civicrm/admin/job', 'reset=1')]),
      ts('CiviLedger: Monitor Not Configured'),
      \Psr\Log\LogLevel::NOTICE,
      'fa-exclamation-circle'
    );
    return;
  }

  $total = (int) ($cached['total'] ?? 0);
  if ($total > 0) {
    $dashUrl = CRM_Utils_System::url('civicrm/civiledger/dashboard', 'reset=1');
    $messages[] = new CRM_Utils_Check_Message(
      'civiledger_integrity_issues',
      ts('%1 financial integrity issue(s) detected (last check: %2). <a href="%3">View in CiviLedger</a>.',
        [1 => $total, 2 => $lastRun, 3 => $dashUrl]),
      ts('CiviLedger: Financial Issues Found'),
      \Psr\Log\LogLevel::WARNING,
      'fa-exclamation-triangle'
    );
  }
}

/**
 * Implements hook_civicrm_permission().
 */
function civiledger_civicrm_permission(&$permissions) {
  $permissions['access civiledger'] = [
    'label' => ts('CiviLedger: Access Financial Audit Tools'),
    'description' => ts('Allows users to access CiviLedger financial audit, integrity, and correction tools.'),
  ];
}
