-- CiviLedger upgrade 1002: add hash-chained central audit log table
CREATE TABLE IF NOT EXISTS `civicrm_civiledger_audit_log` (
  `id`          int UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_type`  varchar(50)  NOT NULL                  COMMENT 'REPAIR, CORRECTION, PERIOD_LOCK, PERIOD_UNLOCK',
  `entity_type` varchar(50)  NOT NULL DEFAULT ''        COMMENT 'contribution, financial_trxn, period_lock, etc.',
  `entity_id`   int UNSIGNED DEFAULT NULL               COMMENT 'PK of the affected row',
  `actor_id`    int UNSIGNED DEFAULT NULL               COMMENT 'FK civicrm_contact.id',
  `logged_at`   datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `detail`      text         COLLATE utf8mb4_unicode_ci COMMENT 'JSON snapshot: before/after values, counts, reason',
  `entry_hash`  varchar(64)  NOT NULL                  COMMENT 'SHA-256 of all fields in this row',
  `prev_hash`   varchar(64)  NOT NULL DEFAULT ''        COMMENT 'entry_hash of the previous row (chain link)',
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_entity`     (`entity_type`, `entity_id`),
  KEY `idx_logged_at`  (`logged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CiviLedger: hash-chained immutable audit log for all ledger changes';
