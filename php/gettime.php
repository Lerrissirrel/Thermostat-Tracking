<?php
// This script is Not currently used - the time comes back in getcurrentstatus.php through https://<therm>/tstat
require_once '../common.php';

$log->Info("POST dump".count($_REQUEST));

$id = (isset($_REQUEST['id'])) ? $_REQUEST['id'] : null;    // Set id to chosen thermost (or null if not chosen)
if( $id == null )
{
   $log->Error("gettime.php: No Thermostat id was passed in");
   print_status_and_data(1, "No thermostat id was passed in");
   exit;
}

$found_stat = 0;
foreach( $thermostats as $thermostatRec )
{
   if ($id == $thermostatRec['id'])
   {
      $stat = new Stat( $thermostatRec['ip'] );
      $found_stat = 1;
      break;
   }
}

if( $found_stat == 0)
{
   $log->Error("gettime.php: Bogus thermostat id passed in");
   print_status_and_data(1, "Bogus thermostat id passed in");
   exit;
}

$stat->getTime();
print_status_and_data(0, $stat->time);
?>
