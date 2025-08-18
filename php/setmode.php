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

$log->Info("setmode POST dump count: ".count($_REQUEST));

$mode = (isset($_REQUEST['mode'])) ? $_REQUEST['mode'] : null;    // Set mode value to which we'll set the "id"  thermostat

if ($mode == null || ($mode != 'Heat' && $mode != 'Cool' && $mode != 'Off'))
{
    // Bail out since we don't know what we're supposed to do.  Should probably print some json here to tell the caller of the failure
    $log->Error("sethmode.php: Didn't receive valid mode value : *".$mode."*");
    print_status_and_data(1, "Didn't receive valid mode value");
    exit;
}
else
{
    $log->Info("setmode.php: got a mode value: ".$mode);
}
$id = (isset($_REQUEST['id'])) ? $_REQUEST['id'] : null;    // Set id to chosen thermost (or null if not chosen)
if( $id == null )
{
    $log->Error("sethmode.php: Didn't receive a thermostate id");
    print_status_and_data(2, "Didn't receive a therm id");
    exit;
}
$found_therm = 0;

foreach( $thermostats as $thermostatRec )
{
    $log->Info($id.' against '.$thermostatRec['id']);
    if ($id == $thermostatRec['id'])
    {
        $log->Info("setmode.php: got a real new Stat $id");
        $stat = new Stat( $thermostatRec['ip'] );
        $found_therm = 1;
        break;
    }
}

if ($found_therm == 0)
{
    $log->Error("sethmode.php: Didn't receive a valid thermostate id");
    print_status_and_data(2, "Didn't receive valid therm id");
    exit;
}

$log->Info("setmode got therm id: $id ");
$log->Info("setmode value: $mode");
$stat->setMode("$mode");
$log->Info("Exiting setmode");
print_status_and_data(0, "{\"mode\": \"$mode\"}");
?>
