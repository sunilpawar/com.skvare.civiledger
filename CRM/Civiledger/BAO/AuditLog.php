<?php
/**
 * CiviLedger — Hash-Chained Audit Log
 *
 * Every write operation (repair, correction, period lock/unlock) records an entry here.
 * Each row stores a SHA-256 hash of its own data plus the previous row's hash,
 * forming a tamper-evident chain.  Any modification or deletion of a past entry
 * breaks the chain, which verifyChain() will detect.
 */
class CRM_Civiledger_BAO_AuditLog {

  const PAGE_SIZE = 50;

  // Canonical event-type constants used by callers
  const EVENT_REPAIR        = 'REPAIR';
  const EVENT_CORRECTION    = 'CORRECTION';
  const EVENT_PERIOD_LOCK   = 'PERIOD_LOCK';
  const EVENT_PERIOD_UNLOCK = 'PERIOD_UNLOCK';

  /**
   * Record one auditable event.
   *
   * @param string   $eventType  One of the EVENT_* constants above.
   * @param string   $entityType 'contribution', 'financial_trxn', 'period_lock', …
   * @param int|null $entityId   Primary key of the affected row.
   * @param array    $detail     Arbitrary data (before/after values, counts, reason…).
   * @return int  ID of the newly inserted audit row.
   */
  public static function record(string $eventType, string $entityType, ?int $entityId, array $detail = []): int {
    $actorId    = (int) CRM_Core_Session::getLoggedInContactID() ?: NULL;
    $now        = date('Y-m-d H:i:s');
    $detailJson = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Fetch the hash of the most recent entry so we can chain to it.
    $prevHash = (string) CRM_Core_DAO::singleValueQuery(
      "SELECT entry_hash FROM civicrm_civiledger_audit_log ORDER BY id DESC LIMIT 1"
    ) ?: '';

    $actorSql  = $actorId  ? (int) $actorId  : 'NULL';
    $entitySql = $entityId ? (int) $entityId : 'NULL';

    // Two-step insert: first get the auto-increment id, then compute the real hash.
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_civiledger_audit_log
         (event_type, entity_type, entity_id, actor_id, logged_at, detail, entry_hash, prev_hash)
       VALUES (%1, %2, {$entitySql}, {$actorSql}, %3, %4, 'computing', %5)",
      [
        1 => [$eventType,  'String'],
        2 => [$entityType, 'String'],
        3 => [$now,        'String'],
        4 => [$detailJson, 'String'],
        5 => [$prevHash,   'String'],
      ]
    );

    $newId = (int) CRM_Core_DAO::singleValueQuery('SELECT LAST_INSERT_ID()');

    // The hash covers every field including the row id, so it is unique per row.
    $hashInput = implode('|', [
      $newId,
      $eventType,
      $entityType,
      $entityId  ?? '',
      $actorId   ?? '',
      $now,
      $detailJson,
      $prevHash,
    ]);
    $entryHash = hash('sha256', $hashInput);

    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_civiledger_audit_log SET entry_hash = %1 WHERE id = %2",
      [1 => [$entryHash, 'String'], 2 => [$newId, 'Integer']]
    );

    return $newId;
  }

  /**
   * Walk every row in insertion order, recompute each hash, and check the chain.
   *
   * @return array {valid: bool, total: int, broken_at: int|null, message: string}
   */
  public static function verifyChain(): array {
    $rows = CRM_Core_DAO::executeQuery(
      "SELECT id, event_type, entity_type, entity_id, actor_id,
              logged_at, detail, entry_hash, prev_hash
       FROM civicrm_civiledger_audit_log ORDER BY id ASC"
    )->fetchAll();

    $result   = ['valid' => TRUE, 'total' => count($rows), 'broken_at' => NULL, 'message' => ''];
    $prevHash = '';

    foreach ($rows as $row) {
      $hashInput = implode('|', [
        $row['id'],
        $row['event_type'],
        $row['entity_type'],
        $row['entity_id']  ?? '',
        $row['actor_id']   ?? '',
        $row['logged_at'],
        $row['detail'],
        $row['prev_hash'],
      ]);
      $expected = hash('sha256', $hashInput);

      if ($row['entry_hash'] !== $expected || $row['prev_hash'] !== $prevHash) {
        $result['valid']     = FALSE;
        $result['broken_at'] = (int) $row['id'];
        $result['message']   = "Chain broken at entry #{$row['id']} — data may have been tampered with";
        return $result;
      }
      $prevHash = $row['entry_hash'];
    }

    $n = $result['total'];
    $result['message'] = $n === 0
      ? 'No entries yet — chain is empty'
      : "Chain intact — {$n} " . ($n === 1 ? 'entry' : 'entries') . ' verified';
    return $result;
  }

  /**
   * Paginated log entries with optional filters.
   */
  public static function getEntries(array $filters = [], int $limit = self::PAGE_SIZE, int $offset = 0): array {
    [$where, $params] = self::buildWhere($filters);
    return CRM_Core_DAO::executeQuery(
      "SELECT al.*, c.display_name AS actor_name
       FROM civicrm_civiledger_audit_log al
       LEFT JOIN civicrm_contact c ON c.id = al.actor_id
       WHERE {$where}
       ORDER BY al.id DESC
       LIMIT {$limit} OFFSET {$offset}",
      $params
    )->fetchAll();
  }

  public static function getTotal(array $filters = []): int {
    [$where, $params] = self::buildWhere($filters);
    return (int) CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_civiledger_audit_log WHERE {$where}",
      $params
    );
  }

  /** Distinct event types for the filter dropdown. */
  public static function getEventTypes(): array {
    $rows = CRM_Core_DAO::executeQuery(
      "SELECT DISTINCT event_type FROM civicrm_civiledger_audit_log ORDER BY event_type"
    )->fetchAll();
    $out = ['' => ts('-- All Types --')];
    foreach ($rows as $row) {
      $out[$row['event_type']] = $row['event_type'];
    }
    return $out;
  }

  private static function buildWhere(array $filters): array {
    $conds  = ['1=1'];
    $params = [];
    $i      = 1;
    if (!empty($filters['event_type'])) {
      $conds[]    = "event_type = %{$i}";
      $params[$i++] = [$filters['event_type'], 'String'];
    }
    if (!empty($filters['entity_type'])) {
      $conds[]    = "entity_type = %{$i}";
      $params[$i++] = [$filters['entity_type'], 'String'];
    }
    if (!empty($filters['date_from'])) {
      $conds[]    = "logged_at >= %{$i}";
      $params[$i++] = [$filters['date_from'] . ' 00:00:00', 'String'];
    }
    if (!empty($filters['date_to'])) {
      $conds[]    = "logged_at <= %{$i}";
      $params[$i++] = [$filters['date_to'] . ' 23:59:59', 'String'];
    }
    return [implode(' AND ', $conds), $params];
  }

}
