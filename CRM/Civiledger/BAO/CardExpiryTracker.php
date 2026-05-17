<?php
/**
 * CiviLedger - Payment Card Expiry Tracker
 *
 * Finds active recurring series whose vaulted payment token is expired or
 * expiring within the requested window.  Results are bucketed into:
 *   1. Already Expired    — LAST_DAY(expiry_date) < today
 *   2. Expiring Soon      — expires within 0–30 days
 *   3. Expiring Later     — expires in 31–N days (N = days_window filter, default 90)
 *
 * Only recurring series with a payment_token_id are included; series using
 * check, ACH, or cash (no token) are silently excluded.
 *
 * @package com.skvare.civiledger
 */
class CRM_Civiledger_BAO_CardExpiryTracker {

  /**
   * Run the check and return results in three buckets.
   *
   * @param array $filters  Keys: days_window (int, default 90),
   *                        payment_processor_id (int|'')
   * @return array  Keys: expired[], expiring_soon[], expiring_later[]
   */
  public static function runCheck(array $filters = []): array {
    $rows = self::getAllExpiringCards($filters);

    $expired = $expiringSoon = $expiringLater = [];
    foreach ($rows as $row) {
      $d = (int) $row['days_remaining'];
      if ($d < 0) {
        $expired[] = $row;
      }
      elseif ($d <= 30) {
        $expiringSoon[] = $row;
      }
      else {
        $expiringLater[] = $row;
      }
    }

    return [
      'expired'        => $expired,
      'expiring_soon'  => $expiringSoon,
      'expiring_later' => $expiringLater,
    ];
  }

  /**
   * Compute the frequency-adjusted monthly value for a single recurring row.
   */
  public static function rowMrr(array $row): float {
    $amount   = (float) $row['amount'];
    $interval = max(1, (int) $row['frequency_interval']);
    switch ($row['frequency_unit']) {
      case 'month': return $amount / $interval;
      case 'year':  return $amount / ($interval * 12.0);
      case 'week':  return $amount * 52.0 / (12.0 * $interval);
      case 'day':   return $amount * 365.0 / (12.0 * $interval);
      default:      return $amount;
    }
  }

  /**
   * Sum frequency-adjusted MRR across an array of rows.
   */
  public static function sumMrr(array $rows): float {
    $total = 0.0;
    foreach ($rows as $r) {
      $total += self::rowMrr($r);
    }
    return round($total, 2);
  }

  // -----------------------------------------------------------------------
  // Internal
  // -----------------------------------------------------------------------

  private static function getAllExpiringCards(array $filters): array {
    [$where, $params] = self::buildWhere($filters);
    $daysWindow = max(1, (int) ($filters['days_window'] ?? 90));

    $sql = "
      SELECT
        ct.id                                         AS contact_id,
        ct.display_name                               AS contact_name,
        e.email                                       AS contact_email,
        cr.id                                         AS recur_id,
        cr.amount,
        cr.currency,
        cr.frequency_interval,
        cr.frequency_unit,
        cr.payment_processor_id,
        COALESCE(pp.name, 'Unknown / Direct')         AS processor_name,
        pt.id                                         AS token_id,
        pt.masked_account_number,
        pt.expiry_date,
        DATE_FORMAT(pt.expiry_date, '%m/%Y')          AS expiry_display,
        LAST_DAY(pt.expiry_date)                      AS card_expiry_date,
        DATEDIFF(LAST_DAY(pt.expiry_date), CURDATE()) AS days_remaining
      FROM  civicrm_contribution_recur cr
      INNER JOIN civicrm_contact ct          ON ct.id = cr.contact_id AND ct.is_deleted = 0
      INNER JOIN civicrm_payment_token pt    ON pt.id = cr.payment_token_id
      LEFT  JOIN civicrm_email e             ON e.contact_id = ct.id AND e.is_primary = 1
      LEFT  JOIN civicrm_payment_processor pp ON pp.id = cr.payment_processor_id
      WHERE cr.contribution_status_id NOT IN (3, 4) -- Cancelled, Failed
        AND cr.cancel_date IS NULL
        AND pt.expiry_date IS NOT NULL
        AND LAST_DAY(pt.expiry_date) <= DATE_ADD(CURDATE(), INTERVAL {$daysWindow} DAY)
        {$where}
      ORDER BY days_remaining ASC
      LIMIT 500
    ";

    $rows = CRM_Core_DAO::executeQuery($sql, $params)->fetchAll();
    return self::addUrls($rows);
  }

  private static function buildWhere(array $filters): array {
    $where  = '';
    $params = [];
    $i      = 1;
    if (!empty($filters['payment_processor_id'])) {
      $where .= " AND cr.payment_processor_id = %{$i}";
      $params[$i++] = [(int) $filters['payment_processor_id'], 'Integer'];
    }
    return [$where, $params];
  }

  private static function addUrls(array $rows): array {
    foreach ($rows as &$row) {
      $row['contact_url'] = CRM_Utils_System::url(
        'civicrm/contact/view',
        "reset=1&cid={$row['contact_id']}"
      );
      $row['recur_url'] = CRM_Utils_System::url(
        'civicrm/contact/view/contributionrecur',
        "reset=1&action=view&id={$row['recur_id']}&cid={$row['contact_id']}&context=contribution"
      );
    }
    return $rows;
  }

}
