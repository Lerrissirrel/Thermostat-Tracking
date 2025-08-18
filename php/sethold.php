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

$log->Info("sethold POST dump count: ".count($_REQUEST));

$hold = (isset($_REQUEST['hold'])) ? $_REQUEST['hold'] : null;    // Set hold value to which we'll set the "id"  thermostat

if ($hold == null || ($hold != 'On' && $hold != 'Off'))
{
    // Bail out since we don't know what we're supposed to do.  Should probably print some json here to tell the caller of the failure
    $log->Error("sethold.php: Didn't receive hold value : *".$hold."*");
    print_status_and_data(1, "Didn't receive hol value");
    exit;
}
else
{
    $log->Info("sethold.php: got a hold value: ".$hold);
}
$id = (isset($_REQUEST['id'])) ? $_REQUEST['id'] : null;    // Set id to chosen thermost (or null if not chosen)
if( $id == null )
{
    $log->Error( "sethold.php: Thermostat ID was not passed in");
    print_status_and_data(2, "Therm ID was not passed in");
    exit;
}

$found_therm = 0;
foreach( $thermostats as $thermostatRec )
{
    $log->Info($id.' against '.$thermostatRec['id']);
    if ($id == $thermostatRec['id'])
    {
        $log->Info("sethold.php: got a real new Stat $id");
        $stat = new Stat( $thermostatRec['ip'] );
        $found_therm = 1;
        break;
    }
}
if ($found_therm == 0)
{
    $log->Error( "sethold.php: Invalid therm ID");
    print_status_and_data(2, "Invalid therm ID");
    exit;
}

$log->Info("sethold got therm id: $id ");
$log->Info("sethold value: $hold");
$stat->setHold("$hold");
$log->Info("Exiting sethold");
print_status_and_data(0, "Success");
?>
