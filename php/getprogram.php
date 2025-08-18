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

//$log->Error("POST dump".count($_REQUEST));

$id = (isset($_REQUEST['id'])) ? $_REQUEST['id'] : null;    // Set id to chosen thermost (or null if not chosen)
if( $id == null )
{
   $log->Error( "getprogram.php: Thermostat ID was not chosen, picking one from".$thermostat['id'].": $id" );
   print_status_and_data(1, "Thermostat ID was not passed in");
   exit;
}

$heat_or_cool = (isset($_REQUEST['heat_or_cool'])) ? $_REQUEST['heat_or_cool'] : null;    // Set id to chosen thermost (or null if not chosen)
if( $heat_or_cool != 'heat' && $heat_or_cool != 'cool')
{
    $log->Error( "getprogram.php: Did not get heat or cool: ".$_REQUEST['heat_or_cool']);
    print_status_and_data(1, "Did not get heat or cool");
    exit;
}

foreach( $thermostats as $thermostatRec )
{
//    $log->Error($id.' against '.$thermostatRec['id']);
    if ($id == $thermostatRec['id'])
    {
//        $log->Error("getprogram.php: got a real new Stat $id");
        $stat = new Stat( $thermostatRec['ip'] );
        break;
    }
}

//$log->Error("getprogram got therm id: $id ");
//$log->Error("getprogram therm ip: ".$stat->uuid);
$stat->getProgram($heat_or_cool);

//echo $stat->stat_program;
    print_status_and_data(0, $stat->stat_program);
//echo "";
//$log->Error("Exiting getprogram");
?>
