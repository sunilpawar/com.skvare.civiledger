-- CiviLedger upgrade 1001: add repair log table
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
