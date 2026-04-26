<?php
/**
 * CiviLedger - Feature: Financial Period Close / Lock
 *
 * Stores lock records in civicrm_civiledger_period_lock.
 * AccountCorrection calls isTransactionLocked() before applying any correction.
 */
class CRM_Civiledger_BAO_PeriodClose {

  /**
   * Return the currently active lock, or NULL if no period is locked.
   */
  public static function getActiveLock(): ?array {
    $dao = CRM_Core_DAO::executeQuery("
      SELECT pl.*, c.display_name AS locked_by_name
      FROM civicrm_civiledger_period_lock pl
      LEFT JOIN civicrm_contact c ON c.id = pl.locked_by
      WHERE pl.is_active = 1
      ORDER BY pl.locked_at DESC
      LIMIT 1
    ");
    if ($dao->fetch()) {
      return $dao->toArray();
    }
    return NULL;
  }

  /**
   * Lock all transactions before $lockDate.
   *
   * @param string $lockDate Y-m-d
   * @param string $reason
   * @param int $userId civicrm_contact.id of the person locking
   * @return array  ['success' => bool, 'lock_id' => int, 'error' => string]
   */
  public static function lockPeriod(string $lockDate, string $reason, int $userId): array {
    if (self::getActiveLock()) {
      return ['success' => FALSE, 'error' => ts('A period lock is already active. Unlock it before locking a new period.')];
    }
    if (!$lockDate || !strtotime($lockDate)) {
      return ['success' => FALSE, 'error' => ts('Invalid lock date.')];
    }
    if (empty(trim($reason))) {
      return ['success' => FALSE, 'error' => ts('A reason is required to lock a period.')];
    }

    CRM_Core_DAO::executeQuery("
      INSERT INTO civicrm_civiledger_period_lock
        (lock_date, lock_reason, locked_by, locked_at, is_active)
      VALUES (%1, %2, %3, NOW(), 1)
    ", [
      1 => [$lockDate, 'String'],
      2 => [trim($reason), 'String'],
      3 => [$userId, 'Integer'],
    ]);

    $lockId = (int) CRM_Core_DAO::singleValueQuery('SELECT LAST_INSERT_ID()');

    CRM_Civiledger_BAO_AuditLog::record(
      CRM_Civiledger_BAO_AuditLog::EVENT_PERIOD_LOCK,
      'period_lock',
      $lockId,
      ['lock_date' => $lockDate, 'reason' => $reason]
    );

    return ['success' => TRUE, 'lock_id' => $lockId];
  }

  /**
   * Unlock the active period.
   *
   * @param int $lockId
   * @param string $unlockReason
   * @param int $userId
   * @return array
   */
  public static function unlockPeriod(int $lockId, string $unlockReason, int $userId): array {
    if (empty(trim($unlockReason))) {
      return ['success' => FALSE, 'error' => ts('A reason is required to unlock a period.')];
    }

    $affected = CRM_Core_DAO::executeQuery("
      UPDATE civicrm_civiledger_period_lock
      SET is_active = 0, unlock_reason = %1, unlocked_by = %2, unlocked_at = NOW()
      WHERE id = %3 AND is_active = 1
    ", [
      1 => [trim($unlockReason), 'String'],
      2 => [$userId, 'Integer'],
      3 => [$lockId, 'Integer'],
    ])->affectedRows();

    if (!$affected) {
      return ['success' => FALSE, 'error' => ts('Lock not found or already unlocked.')];
    }

    CRM_Civiledger_BAO_AuditLog::record(
      CRM_Civiledger_BAO_AuditLog::EVENT_PERIOD_UNLOCK,
      'period_lock',
      $lockId,
      ['unlock_reason' => $unlockReason]
    );

    return ['success' => TRUE];
  }

  /**
   * Returns TRUE if the given transaction date falls within a locked period.
   *
   * @param string $trxnDate Y-m-d or full datetime
   */
  public static function isTransactionLocked(string $trxnDate): bool {
    $lock = self::getActiveLock();
    if (!$lock) {
      return FALSE;
    }
    // Locked if trxn_date < lock_date (exclusive: the lock_date day itself is still editable)
    return substr($trxnDate, 0, 10) < $lock['lock_date'];
  }

  /**
   * Full lock/unlock history for the audit log display.
   */
  public static function getLockHistory(): array {
    return CRM_Core_DAO::executeQuery("
      SELECT
        pl.*,
        lc.display_name AS locked_by_name,
        uc.display_name AS unlocked_by_name
      FROM civicrm_civiledger_period_lock pl
      LEFT JOIN civicrm_contact lc ON lc.id = pl.locked_by
      LEFT JOIN civicrm_contact uc ON uc.id = pl.unlocked_by
      ORDER BY pl.locked_at DESC
    ")->fetchAll();
  }

}
