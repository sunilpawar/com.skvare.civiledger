<?php
/**
 * CiviLedger - Feature: Scheduled Integrity Monitoring + Email Alerts
 *
 * Called by the CiviCRM scheduled job (Civiledger.Monitorcheck).
 * Results are cached in CiviCRM settings so hook_civicrm_check can read
 * them cheaply without re-running queries on every admin page load.
 */
class CRM_Civiledger_BAO_MonitoringAlert {

  const SETTING_LAST_RESULT  = 'civiledger_last_check_result';
  const SETTING_LAST_RUN     = 'civiledger_last_check_run';
  const SETTING_ALERT_EMAILS  = 'civiledger_alert_emails';
  const SETTING_ALERT_ENABLED = 'civiledger_alert_enabled';

  /**
   * Run all checks, cache results, email admins if issues found.
   */
  public static function runAndAlert(): array {
    $integritySummary = CRM_Civiledger_BAO_IntegrityChecker::getSummaryCounts();
    $mismatchSummary  = CRM_Civiledger_BAO_MismatchDetector::getSummary();

    $totalIntegrity = (int) ($integritySummary['total'] ?? 0);
    $totalMismatch  = (int) ($mismatchSummary['total'] ?? 0);
    $totalIssues    = $totalIntegrity + $totalMismatch;

    // Cache for System Status (hook_civicrm_check reads this, no live queries)
    Civi::settings()->set(self::SETTING_LAST_RESULT, [
      'integrity' => $integritySummary,
      'mismatch'  => $mismatchSummary,
      'total'     => $totalIssues,
    ]);
    Civi::settings()->set(self::SETTING_LAST_RUN, date('Y-m-d H:i:s'));

    $alertEnabled = (bool) (Civi::settings()->get(self::SETTING_ALERT_ENABLED) ?? 1);
    $emailSent = FALSE;
    if ($alertEnabled && $totalIssues > 0) {
      self::sendAlertEmail($integritySummary, $mismatchSummary, $totalIssues);
      $emailSent = TRUE;
    }

    // Write every check run to the hash-chained audit log regardless of outcome.
    CRM_Civiledger_BAO_AuditLog::record('HEALTH_CHECK', 'system', NULL, [
      'integrity'  => $integritySummary,
      'mismatch'   => $mismatchSummary,
      'total'      => $totalIssues,
      'email_sent' => $emailSent,
    ]);

    return [
      'integrity_issues' => $totalIntegrity,
      'mismatch_issues'  => $totalMismatch,
      'total_issues'     => $totalIssues,
      'email_sent'       => $emailSent,
      'run_at'           => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Return cached last-run results (used by hook_civicrm_check).
   */
  public static function getCachedResult(): array {
    return Civi::settings()->get(self::SETTING_LAST_RESULT) ?? [];
  }

  public static function getLastRunTime(): ?string {
    return Civi::settings()->get(self::SETTING_LAST_RUN);
  }

  // ---------------------------------------------------------------------------

  private static function sendAlertEmail(array $integrity, array $mismatch, int $total): void {
    // Build recipient list from settings; fall back to domain From address.
    $recipientsSetting = trim(Civi::settings()->get(self::SETTING_ALERT_EMAILS) ?? '');
    $emails = $recipientsSetting
      ? array_filter(array_map('trim', explode(',', $recipientsSetting)))
      : [];
    if (empty($emails)) {
      [, $fallback] = CRM_Core_BAO_Domain::getNameAndEmail();
      if ($fallback) {
        $emails[] = $fallback;
      }
    }
    if (empty($emails)) {
      return;
    }

    $domainName   = CRM_Core_BAO_Domain::getDomain()->name ?? 'CiviCRM';
    $dashUrl      = CRM_Utils_System::url('civicrm/civiledger/dashboard',         'reset=1', TRUE);
    $integrityUrl = CRM_Utils_System::url('civicrm/civiledger/integrity-check',   'reset=1', TRUE);
    $mismatchUrl  = CRM_Utils_System::url('civicrm/civiledger/mismatch-detector', 'reset=1', TRUE);
    $runAt        = self::getLastRunTime();

    $subject = ts('[CiviLedger] %1 financial issue(s) detected — %2', [1 => $total, 2 => $domainName]);
    $html    = self::buildHtml($integrity, $mismatch, $total, $domainName, $dashUrl, $integrityUrl, $mismatchUrl, $runAt);
    $text    = self::buildText($integrity, $mismatch, $total, $domainName, $dashUrl, $runAt);

    foreach ($emails as $toEmail) {
      CRM_Utils_Mail::send([
        'toEmail' => $toEmail,
        'toName'  => $domainName . ' Admin',
        'subject' => $subject,
        'html'    => $html,
        'text'    => $text,
      ]);
    }
  }

  private static function buildHtml(
    array $integrity, array $mismatch, int $total,
    string $domain, string $dashUrl, string $integrityUrl, string $mismatchUrl, ?string $runAt
  ): string {
    $statusColor = $total > 0 ? '#dc3545' : '#28a745';
    $statusText  = $total > 0
      ? "{$total} issue(s) require attention"
      : 'All checks passed — no issues found';

    $intRows = '';
    $intLabels = [
      'missing_line_items'              => 'Missing Line Items',
      'missing_financial_items'         => 'Missing Financial Items',
      'missing_contribution_trxn_link'  => 'Missing Contribution → Trxn Links',
      'missing_financial_item_link'     => 'Missing Financial Item → Trxn Links',
      'orphaned_financial_trxn'         => 'Orphaned Financial Transactions',
    ];
    foreach ($intLabels as $key => $label) {
      $count = (int) ($integrity[$key] ?? 0);
      $color = $count > 0 ? '#dc3545' : '#28a745';
      $intRows .= "<tr><td style='padding:4px 8px'>{$label}</td><td style='padding:4px 8px;color:{$color};font-weight:bold;text-align:right'>{$count}</td></tr>";
    }

    $misRows = '';
    $misLabels = [
      'line_item_mismatch'      => 'Line Item Mismatches',
      'financial_item_mismatch' => 'Financial Item Mismatches',
      'trxn_mismatch'           => 'Transaction Mismatches',
    ];
    foreach ($misLabels as $key => $label) {
      $count = (int) ($mismatch[$key] ?? 0);
      $color = $count > 0 ? '#dc3545' : '#28a745';
      $misRows .= "<tr><td style='padding:4px 8px'>{$label}</td><td style='padding:4px 8px;color:{$color};font-weight:bold;text-align:right'>{$count}</td></tr>";
    }

    return "
<html><body style='font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto'>
<div style='background:#2c3e50;color:#fff;padding:20px 24px;border-radius:6px 6px 0 0'>
  <h2 style='margin:0'>CiviLedger Integrity Report</h2>
  <p style='margin:4px 0 0;opacity:.8'>{$domain} &mdash; {$runAt}</p>
</div>
<div style='background:#f8f9fa;border:1px solid #dee2e6;border-top:none;padding:20px 24px'>
  <p style='font-size:18px;color:{$statusColor};font-weight:bold'>{$statusText}</p>

  <h3 style='border-bottom:1px solid #dee2e6;padding-bottom:6px'>Integrity Checker</h3>
  <table style='width:100%;border-collapse:collapse;font-size:14px'>
    <tr style='background:#e9ecef'><th style='padding:4px 8px;text-align:left'>Check</th><th style='padding:4px 8px;text-align:right'>Count</th></tr>
    {$intRows}
  </table>

  <h3 style='border-bottom:1px solid #dee2e6;padding-bottom:6px;margin-top:20px'>Mismatch Detector</h3>
  <table style='width:100%;border-collapse:collapse;font-size:14px'>
    <tr style='background:#e9ecef'><th style='padding:4px 8px;text-align:left'>Check</th><th style='padding:4px 8px;text-align:right'>Count</th></tr>
    {$misRows}
  </table>

  <div style='margin-top:24px'>
    <a href='{$dashUrl}' style='background:#2c3e50;color:#fff;padding:8px 16px;border-radius:4px;text-decoration:none;margin-right:8px'>Open Dashboard</a>
    <a href='{$integrityUrl}' style='background:#6c757d;color:#fff;padding:8px 16px;border-radius:4px;text-decoration:none;margin-right:8px'>Integrity Checker</a>
    <a href='{$mismatchUrl}' style='background:#6c757d;color:#fff;padding:8px 16px;border-radius:4px;text-decoration:none'>Mismatch Detector</a>
  </div>
</div>
</body></html>";
  }

  private static function buildText(
    array $integrity, array $mismatch, int $total,
    string $domain, string $dashUrl, ?string $runAt
  ): string {
    $lines = [
      "CiviLedger Integrity Report — {$domain}",
      "Run at: {$runAt}",
      str_repeat('-', 40),
      "Total issues: {$total}",
      '',
      'INTEGRITY CHECKER:',
      "  Missing Line Items:              " . ($integrity['missing_line_items'] ?? 0),
      "  Missing Financial Items:         " . ($integrity['missing_financial_items'] ?? 0),
      "  Missing Contribution-Trxn Links: " . ($integrity['missing_contribution_trxn_link'] ?? 0),
      "  Missing Financial Item Links:    " . ($integrity['missing_financial_item_link'] ?? 0),
      "  Orphaned Financial Trxns:        " . ($integrity['orphaned_financial_trxn'] ?? 0),
      '',
      'MISMATCH DETECTOR:',
      "  Line Item Mismatches:            " . ($mismatch['line_item_mismatch'] ?? 0),
      "  Financial Item Mismatches:       " . ($mismatch['financial_item_mismatch'] ?? 0),
      "  Transaction Mismatches:          " . ($mismatch['trxn_mismatch'] ?? 0),
      '',
      "Dashboard: {$dashUrl}",
    ];
    return implode("\n", $lines);
  }

}
