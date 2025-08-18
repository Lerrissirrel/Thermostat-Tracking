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

$log->Info("setfan POST dump count: ".count($_REQUEST));

$fan = (isset($_REQUEST['fan'])) ? $_REQUEST['fan'] : null;    // Set fan value to which we'll set the "id"  thermostat

if ($fan == null || ($fan != 'On' && $fan != 'Auto'))
{
    // Bail out since we don't know what we're supposed to do.  Should probably print some json here to tell the caller of the failure
    $log->Error("setfan.php: Didn't receive fan value : *".$fan."*");
    print_status_and_data(1, "Didn't receive fan value");
    exit;
}

$id = (isset($_REQUEST['id'])) ? $_REQUEST['id'] : null;    // Set id to chosen thermost (or null if not chosen)

if( $id == null )
{
    // If there is no thermostat chosen then abort
    $log->Error( "setfan.php: Thermostat ID was NULL!" );
    // Need to redirect output to some image showing user there was an error and suggesting to read the logs.
    print_status_and_data(1, "Didn't receive thermostat");
    exit;
}

$found_stat = 0;
foreach( $thermostats as $thermostatRec )
{
    $log->Info($id.' against '.$thermostatRec['id']);
    if ($id == $thermostatRec['id'])
    {
        $log->Info("settemp.php: got a real Stat $id");
        $stat = new Stat( $thermostatRec['ip'] );
        $found_stat = 1;
        break;
    }
}

if ($found_stat == 0)
{
    // Did not find a thermostat associated with the id
    $log->Error( "setfan.php: Thermostat ID was bad! (".$id.")" );
    // Need to redirect output to some image showing user there was an error and suggesting to read the logs.
    print_status_and_data(2, "Didn't receive valid thermostat");
    exit;
}

$log->Info("setfan got therm id: $id ");
$log->Info("setfan value: $fan");
$stat->setFan("$fan");
$log->Info("Exiting setfan");
print_status_and_data(0, "Successfully turned fan ".$fan);
?>
