<?php
/**
 * Managed entity: registers the CiviLedger integrity monitor as a CiviCRM scheduled job.
 * The job is installed disabled — an admin must enable it and choose the frequency.
 */
return [
  [
    'name'    => 'Job_CivilEdgerMonitorCheck',
    'entity'  => 'Job',
    'cleanup' => 'always',
    'update'  => 'unmodified',
    'params'  => [
      'version'      => 3,
      'name'         => 'CiviLedger: Integrity Monitor',
      'description'  => 'Runs integrity checker and mismatch detector, caches results for the System Status page, and emails admins when issues are found.',
      'run_frequency' => 'Daily',
      'api_entity'   => 'Civiledger',
      'api_action'   => 'Monitorcheck',
      'parameters'   => '',
      'is_active'    => 0,
    ],
  ],
];
