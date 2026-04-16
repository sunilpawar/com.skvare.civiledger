<?php

/**
 * CiviLedger - Financial Audit & Integrity Extension
 * Extension key: com.skvare.civiledger
 *
 * @package  com.skvare.civiledger
 * @author   Skvare <info@skvare.com>
 * @license  AGPL-3.0
 */

use CRM_Civiledger_ExtensionUtil as E;

require_once 'CRM/Civiledger/BAO/Utils.php';

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
 * Adds CiviLedger menu items under Contributions.
 */
function civiledger_civicrm_navigationMenu(&$menu) {
  // Find the Contributions menu parent
  $contributionsKey = NULL;
  foreach ($menu as $key => $item) {
    if (!empty($item['attributes']['name']) && $item['attributes']['name'] === 'Contributions') {
      $contributionsKey = $key;
      break;
    }
  }

  // Build CiviLedger sub-menu
  $civiledgerItems = [
    [
      'attributes' => [
        'label' => ts('CiviLedger Dashboard'),
        'name' => 'civiledger_dashboard',
        'url' => 'civicrm/civiledger/dashboard',
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
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => NULL,
        'active' => 1,
      ],
    ],
  ];

  // Add CiviLedger parent menu item
  $civiledgerParent = [
    'attributes' => [
      'label' => ts('CiviLedger'),
      'name' => 'civiledger',
      'url' => 'civicrm/civiledger/dashboard',
      'permission' => 'administer CiviCRM',
      'operator' => NULL,
      'separator' => 1,
      'active' => 1,
    ],
    'child' => $civiledgerItems,
  ];

  if ($contributionsKey !== NULL) {
    $menu[$contributionsKey]['child'][] = $civiledgerParent;
  }
  else {
    // Fallback: add at top level
    $menu[] = $civiledgerParent;
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

/**
 * Civix bootstrap helper.
 */
function _civiledger_civix_civicrm_config(&$config = NULL) {
  static $configured = FALSE;
  if ($configured) {
    return;
  }
  $configured = TRUE;

  $extRoot = dirname(__FILE__) . DIRECTORY_SEPARATOR;
  $include_path = $extRoot . PATH_SEPARATOR . get_include_path();
  set_include_path($include_path);
}

function _civiledger_civix_civicrm_install() {
  // Run any install SQL
}

function _civiledger_civix_civicrm_enable() {
  // Nothing needed on enable
}
