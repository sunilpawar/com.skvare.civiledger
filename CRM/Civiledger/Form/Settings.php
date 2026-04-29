<?php
/**
 * CiviLedger — Settings Form
 *
 * Persists configuration via Civi::settings() under the extension's namespace.
 * URL: /civicrm/admin/civiledger/settings
 */
class CRM_Civiledger_Form_Settings extends CRM_Core_Form {

  public function buildQuickForm(): void {
    CRM_Utils_System::setTitle(ts('CiviLedger — Settings'));
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.civiledger', 'css/civiledger.css');

    // Health Monitor
    $this->addElement('checkbox', 'civiledger_alert_enabled',
      ts('Enable nightly health check email alerts'));
    $this->add('text', 'civiledger_alert_emails', ts('Alert recipients'),
      ['class' => 'huge', 'placeholder' => 'admin@example.com, finance@example.com']);

    // Chain Repair
    $this->add('text', 'civiledger_batch_size', ts('Repair list page size'), ['size' => 6]);
    $this->addRule('civiledger_batch_size', ts('Enter a positive integer.'), 'positiveInteger');

    // Audit Trail
    $this->addElement('checkbox', 'civiledger_dup_fi_detection',
      ts('Enable duplicate financial item detection on Audit Trail'));

    // Duplicate Payment Detector
    $this->add('text', 'civiledger_dup_payment_window', ts('Duplicate payment time window (minutes)'), ['size' => 6]);
    $this->addRule('civiledger_dup_payment_window', ts('Enter a positive integer.'), 'positiveInteger');

    $this->addButtons([
      ['type' => 'submit', 'name' => ts('Save Settings'), 'isDefault' => TRUE],
    ]);

    // Pass last health-check run info to the template.
    $cached  = CRM_Civiledger_BAO_MonitoringAlert::getCachedResult();
    $lastRun = CRM_Civiledger_BAO_MonitoringAlert::getLastRunTime();
    $this->assign('lastRun',   $lastRun);
    $this->assign('lastTotal', (int) ($cached['total'] ?? 0));

    parent::buildQuickForm();
  }

  public function setDefaultValues(): array {
    return [
      'civiledger_alert_enabled'    => Civi::settings()->get('civiledger_alert_enabled') ?? 1,
      'civiledger_alert_emails'     => Civi::settings()->get('civiledger_alert_emails') ?? '',
      'civiledger_batch_size'       => Civi::settings()->get('civiledger_batch_size') ?? 50,
      'civiledger_dup_fi_detection'    => Civi::settings()->get('civiledger_dup_fi_detection') ?? 1,
      'civiledger_dup_payment_window'  => Civi::settings()->get('civiledger_dup_payment_window') ?? 10,
    ];
  }

  public function postProcess(): void {
    $values = $this->exportValues();

    Civi::settings()->set('civiledger_alert_enabled',
      (int) !empty($values['civiledger_alert_enabled']));
    Civi::settings()->set('civiledger_alert_emails',
      trim($values['civiledger_alert_emails'] ?? ''));
    Civi::settings()->set('civiledger_batch_size',
      max(1, (int) ($values['civiledger_batch_size'] ?? 50)));
    Civi::settings()->set('civiledger_dup_fi_detection',
      (int) !empty($values['civiledger_dup_fi_detection']));
    Civi::settings()->set('civiledger_dup_payment_window',
      max(1, (int) ($values['civiledger_dup_payment_window'] ?? 10)));

    CRM_Core_Session::setStatus(ts('CiviLedger settings saved.'), ts('Saved'), 'success');
    CRM_Utils_System::redirect(
      CRM_Utils_System::url('civicrm/admin/civiledger/settings', 'reset=1')
    );
  }

}
