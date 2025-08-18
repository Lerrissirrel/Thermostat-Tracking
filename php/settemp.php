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

$log->Info("settemp POST dump count: ".count($_REQUEST));

$temp = (isset($_REQUEST['temp'])) ? $_REQUEST['temp'] : null;    // Set temp value to which we'll set the "id"  thermostat
$mode = (isset($_REQUEST['mode'])) ? $_REQUEST['mode'] : null;    // Get the mode so we know which set temp to change

if ($mode != 'heat' && $mode != 'cool')
{
   $log->Error("settemp.php: Didn't receive valid mode : *".$mode."*");
   print_status_and_data(1, "Didn't receive valid mode : *".$mode."*");
   exit;

}
if ($temp == null || $temp < 50 || $temp > 90)
{
   // Bail out since we don't know what we're supposed to do.  Should probably print some json here to tell the caller of the failure
   $log->Error("settemp.php: Didn't receive valid temperature : *".$temp."*");
   print_status_and_data(1, "Didn't receive valid temperature : *".$temp."*");
   exit;
}
else
{
    $log->Info("settemp.php: got a temp value: ".$temp);
}
$id = (isset($_REQUEST['id'])) ? $_REQUEST['id'] : null;    // Set id to chosen thermost (or null if not chosen)
if( $id == null )
{
   $log->Error( "settemp.php: Thermostat ID was not chosen");
   print_status_and_data(1, "No valid thermostat passed in");
   exit;
}

foreach( $thermostats as $thermostatRec )
{
    $log->Info($id.' against '.$thermostatRec['id']);
    if ($id == $thermostatRec['id'])
    {
        $log->Info("settemp.php: got a real Stat $id");
        $stat = new Stat( $thermostatRec['ip'] );
        break;
    }
}

$log->Info("settemp got therm id: $id ");
$log->Info("settemp value: $temp, mode: $mode");
$stat->setTemp("$temp", "$mode");
$log->Info("Exiting settemp");
//echo "{\"temp\": \"$temp\", \"mode\": \"$mode\"}";
print_status_and_data(0, "{\"temp\": \"$temp\", \"mode\": \"$mode\"}");
?>
