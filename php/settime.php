<?php
require_once '../common.php';

if(!isset($_SESSION))
{
  session_start();
}
if(!isset($_SESSION['isloggedin']))
{
  print_status_and_data(99, "Not logged in");
  exit;
}


$log->Info("settime POST dump count: ".count($_REQUEST));

$id = (isset($_REQUEST['id'])) ? $_REQUEST['id'] : null;    // Set id to chosen thermost (or null if not chosen)

if( $id == null )
{
   $log->Error( "settime.php: Thermostat ID was not chosen");
   print_status_and_data(1, "NULL thermostat");
   exit; 
}

$found_therm = 0;
foreach( $thermostats as $thermostatRec )
{
    $log->Info($id.' against '.$thermostatRec['id']);
    if ($id == $thermostatRec['id'])
    {
        $log->Info("settime.php: got a real new Stat $id");
        $stat = new Stat( $thermostatRec['ip'] );
        $found_therm = 1;
        break;
    }
}
if ($found_therm == 0)
{
   $log->Error( "settime.php: Invalid thermostat");
   print_status_and_data(1, "Invalid thermostat");
   exit; 
}

$log->Info("settime got therm id: $id ");
$stat->setTime();
$log->Info("Exiting settime");
print_status_and_data(0, "Successfully set program");
?>
