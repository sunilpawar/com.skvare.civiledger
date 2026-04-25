-- CiviLedger - auto_install.sql
-- Stores correction log entries for audit purposes

CREATE TABLE IF NOT EXISTS `civicrm_civiledger_correction_log` (
  `id`                    int UNSIGNED NOT NULL AUTO_INCREMENT,
  `financial_trxn_id`     int UNSIGNED NOT NULL  COMMENT 'FK to original civicrm_financial_trxn.id',
  `reversal_trxn_id`      int UNSIGNED DEFAULT NULL COMMENT 'ID of the reversal transaction created',
  `new_trxn_id`           int UNSIGNED DEFAULT NULL COMMENT 'ID of the replacement transaction created',
  `old_from_account_id`   int UNSIGNED DEFAULT NULL,
  `old_to_account_id`     int UNSIGNED DEFAULT NULL,
  `new_from_account_id`   int UNSIGNED DEFAULT NULL,
  `new_to_account_id`     int UNSIGNED DEFAULT NULL,
  `reason`                text         COLLATE utf8mb4_unicode_ci NOT NULL,
  `corrected_by`          int UNSIGNED DEFAULT NULL COMMENT 'FK to civicrm_contact.id of user who made the correction',
  `corrected_date`        datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_financial_trxn_id` (`financial_trxn_id`),
  KEY `idx_corrected_date`    (`corrected_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CiviLedger: audit log of account corrections made via the correction tool';

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

CREATE TABLE IF NOT EXISTS `civicrm_civiledger_repair_log` (
  `id`              int UNSIGNED NOT NULL AUTO_INCREMENT,
  `contribution_id` int UNSIGNED NOT NULL,
  `action`          varchar(20)  NOT NULL                 COMMENT 'fixed, skip, warning, error, info',
  `message`         text         NOT NULL                 COLLATE utf8mb4_unicode_ci,
  `repaired_by`     int UNSIGNED DEFAULT NULL             COMMENT 'FK to civicrm_contact.id',
  `repaired_at`     datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contribution_id` (`contribution_id`),
  KEY `idx_repaired_at`     (`repaired_at`),
  KEY `idx_action`          (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CiviLedger: detailed log of each repair action taken on financial chains';

CREATE TABLE IF NOT EXISTS `civicrm_civiledger_period_lock` (
  `id`             int UNSIGNED NOT NULL AUTO_INCREMENT,
  `lock_date`      date         NOT NULL COMMENT 'Transactions before this date are locked',
  `lock_reason`    text         COLLATE utf8mb4_unicode_ci NOT NULL,
  `locked_by`      int UNSIGNED NOT NULL COMMENT 'FK civicrm_contact.id',
  `locked_at`      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `unlock_reason`  text         COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unlocked_by`    int UNSIGNED DEFAULT NULL COMMENT 'FK civicrm_contact.id',
  `unlocked_at`    datetime     DEFAULT NULL,
  `is_active`      tinyint      NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_is_active`  (`is_active`),
  KEY `idx_lock_date`  (`lock_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CiviLedger: audit log of period locks and unlocks';
