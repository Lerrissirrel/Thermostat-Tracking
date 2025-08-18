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

$log->Info("setprogram POST dump count: ".count($_REQUEST));

$id = (isset($_REQUEST['id'])) ? $_REQUEST['id'] : null;    // Set id to chosen thermost (or null if not chosen)
if( $id == null )
{
    $log->Error( "setprogram.php: Thermostat ID was NULL!" );
    print_status_and_data(1, "NULL Therm ID");
    exit;
}

$newsched = (isset($_REQUEST['newsched'])) ? $_REQUEST['newsched'] : null;   
if( $newsched == null )
{
    $log->Error("setprogram.php: ".isset($_REQUEST['newsched'])."no new sched ".$_REQUEST['newsched'] );
    print_status_and_data(1, "NULL new schedule");
    exit;
}

$heat_or_cool = (isset($_REQUEST['heat_or_cool'])) ? $_REQUEST['heat_or_cool'] : null;   
if( $heat_or_cool == null )
{
    $log->Error("setprogram.php: ".isset($_REQUEST['heat_or_cool'])."no new sched ".$_REQUEST['heat_or_cool'] );
    print_status_and_data(1, "Invalid mode");
    exit;
}

$found_therm = 0;
foreach( $thermostats as $thermostatRec )
{
    $log->Info("setprogram.php: ".$id.' against '.$thermostatRec['id']);
    if ($id == $thermostatRec['id'])
    {
        $log->Info("setprogram.php: got a real new Stat $id");
        $stat = new Stat( $thermostatRec['ip'] );
        $found_therm = 1;
        break;
    }
}
if( $found_therm == 0)
{
    $log->Error( "setprogram.php: Invalid thermostat");
    print_status_and_data(1, "Invalid thermostat");
    exit;
}

$log->Info("setprogram got therm id: $id ");
$log->Info("cool: ".$newsched);
$stat->setProgram($newsched, $heat_or_cool);
$log->Info("Exiting setProgram");
print_status_and_data(0, "Successfully set program");
?>
