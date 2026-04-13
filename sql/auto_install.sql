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
