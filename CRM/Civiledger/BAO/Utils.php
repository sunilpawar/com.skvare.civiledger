<?php

/**
 * CiviLedger - Shared utility methods used across all features.
 *
 * @package  com.skvare.civiledger
 */
class CRM_Civiledger_BAO_Utils {

  /**
   * Get all financial accounts as id => name array.
   */
  public static function getFinancialAccounts(): array {
    $accounts = [];
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT id, name, financial_account_type_id, is_active FROM civicrm_financial_account ORDER BY name"
    );
    while ($dao->fetch()) {
      $accounts[$dao->id] = $dao->name;
    }
    return $accounts;
  }

  /**
   * Get financial account type label.
   */
  public static function getAccountTypeName(int $typeId): string {
    $types = CRM_Core_PseudoConstant::get('CRM_Financial_DAO_FinancialAccount', 'financial_account_type_id');
    return $types[$typeId] ?? 'Unknown';
  }

  /**
   * Get contribution status label.
   */
  public static function getContributionStatusName(int $statusId): string {
    $statuses = CRM_Contribute_PseudoConstant::contributionStatus();
    return $statuses[$statusId] ?? 'Unknown';
  }

  /**
   * Get contact display name by ID.
   */
  public static function getContactName(int $contactId): string {
    return CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $contactId, 'display_name') ?? "Contact #$contactId";
  }

  /**
   * Format currency amount.
   */
  public static function formatMoney(float $amount, string $currency = 'USD'): string {
    return CRM_Utils_Money::format($amount, $currency);
  }

  /**
   * Log an action taken by CiviLedger for audit purposes.
   */
  public static function logAction(string $action, int $contributionId, string $detail, int $userId = NULL): void {
    if (!$userId) {
      $session = CRM_Core_Session::singleton();
      $userId = $session->get('userID');
    }
    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_log (entity_table, entity_id, modified_id, modified_date, data)
       VALUES ('civicrm_contribution', %1, %2, NOW(), %3)",
      [
        1 => [$contributionId, 'Integer'],
        2 => [$userId ?: 0, 'Integer'],
        3 => ["CiviLedger [$action]: $detail", 'String'],
      ]
    );
  }

  /**
   * Get payment instrument label.
   */
  public static function getPaymentInstrumentName(int $instrumentId): string {
    $instruments = CRM_Contribute_PseudoConstant::paymentInstrument();
    return $instruments[$instrumentId] ?? "Instrument #$instrumentId";
  }

  /**
   * Build a contribution URL.
   */
  public static function getContributionUrl(int $contributionId): string {
    return CRM_Utils_System::url('civicrm/contact/view/contribution',
      "reset=1&id=$contributionId&action=view");
  }

  /**
   * Build an audit trail URL for a contribution.
   */
  public static function getAuditTrailUrl(int $contributionId): string {
    return CRM_Utils_System::url('civicrm/civiledger/audit-trail',
      "reset=1&contribution_id=$contributionId");
  }
}
