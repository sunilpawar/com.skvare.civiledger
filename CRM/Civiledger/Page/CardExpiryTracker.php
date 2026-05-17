<?php
/**
 * CiviLedger - Payment Card Expiry Tracker Page
 *
 * @package com.skvare.civiledger
 */
class CRM_Civiledger_Page_CardExpiryTracker extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(ts('Payment Card Expiry Tracker'));

    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css')
      ->addScriptFile('com.skvare.civiledger', 'js/civiledger.js');

    $filters = [
      'days_window'          => CRM_Utils_Request::retrieve('days_window', 'Integer') ?: 90,
      'payment_processor_id' => CRM_Utils_Request::retrieve('payment_processor_id', 'Integer') ?: '',
    ];

    $results = CRM_Civiledger_BAO_CardExpiryTracker::runCheck($filters);

    // Pre-compute per-row display values (avoids Smarty arithmetic)
    foreach (['expired', 'expiring_soon', 'expiring_later'] as $bucket) {
      foreach ($results[$bucket] as &$row) {
        $row['monthly_value']      = CRM_Civiledger_BAO_CardExpiryTracker::rowMrr($row);
        $row['frequency_display']  = self::buildFrequencyDisplay($row);
        $d = (int) $row['days_remaining'];
        if ($d < 0) {
          $row['days_label'] = abs($d) . 'd ago';
          $row['days_class'] = 'expiry-expired';
        }
        elseif ($d === 0) {
          $row['days_label'] = 'Today';
          $row['days_class'] = 'expiry-today';
        }
        elseif ($d <= 7) {
          $row['days_label'] = 'In ' . $d . 'd';
          $row['days_class'] = 'expiry-urgent';
        }
        elseif ($d <= 30) {
          $row['days_label'] = 'In ' . $d . 'd';
          $row['days_class'] = 'expiry-soon';
        }
        else {
          $row['days_label'] = 'In ' . $d . 'd';
          $row['days_class'] = 'expiry-later';
        }
      }
      unset($row);
    }

    $summary = [
      'expired'        => count($results['expired']),
      'expiring_soon'  => count($results['expiring_soon']),
      'expiring_later' => count($results['expiring_later']),
    ];
    $summary['total'] = array_sum($summary);

    $mrrAtRisk = [
      'expired'        => CRM_Civiledger_BAO_CardExpiryTracker::sumMrr($results['expired']),
      'expiring_soon'  => CRM_Civiledger_BAO_CardExpiryTracker::sumMrr($results['expiring_soon']),
      'expiring_later' => CRM_Civiledger_BAO_CardExpiryTracker::sumMrr($results['expiring_later']),
    ];
    $mrrAtRisk['total'] = round(
      $mrrAtRisk['expired'] + $mrrAtRisk['expiring_soon'] + $mrrAtRisk['expiring_later'],
      2
    );

    // Active payment processors for the filter dropdown
    $processors = [];
    $procRows = CRM_Core_DAO::executeQuery("
      SELECT id, name
      FROM   civicrm_payment_processor
      WHERE  is_active = 1 AND is_test = 0
      ORDER  BY name ASC
    ")->fetchAll();
    foreach ($procRows as $p) {
      $processors[$p['id']] = $p['name'];
    }

    $this->assign('filters',    $filters);
    $this->assign('results',    $results);
    $this->assign('summary',    $summary);
    $this->assign('mrrAtRisk',  $mrrAtRisk);
    $this->assign('processors', $processors);
    $this->assign('cms_type',   CIVICRM_UF);

    parent::run();
  }

  private static function buildFrequencyDisplay(array $row): string {
    $interval = (int) $row['frequency_interval'];
    $unit     = $row['frequency_unit'];
    if ($interval === 1) {
      return ucfirst($unit) . 'ly';
    }
    return 'Every ' . $interval . ' ' . $unit . 's';
  }

}
