<?php
require_once '../common.php';

session_write_close();

//$log->Error("POST dump".count($_REQUEST));

$id = (isset($_REQUEST['id'])) ? $_REQUEST['id'] : null;    // Set id to chosen thermost (or null if not chosen)
if( $id == null )
{
   // Older versions chose a thermostat for you, but while that allows the operation to succeed it means
   // passing incorrect information to the caller.  Better to fail.
   $log->Error( "getcurrentstat.php: Thermostat ID was not passed in");
   print_status_and_data(1, "No id was passed in");
   exit;
}

$stat = null;

//foreach( array_merge($thermostats, $thermostats_disabled) as $thermostatRec )
foreach( $thermostats as $thermostatRec )
{
//    $log->Error($id.' against '.$thermostatRec['id']);
    if ($id == $thermostatRec['id'])
    {
//        $log->Error("getcurrentstat.php: got a real new Stat $id");
        $stat = new Stat( $thermostatRec['ip'] );
        break;
    }
}

if( $id == null || $stat == null)
{
    // If there still is not one chosen then abort
//    $log->Error( "getcurrentstat.php: Thermostat ID was NULL!" );
    // Need to redirect output to some image showing user there was an error and suggesting to read the logs.
    $log->Error("Failed to find the right thermostat to talk to id = *".$id."*");
    print_status_and_data(2, "Failed to find the right thermostat to talk to id = *".$id."*");
    exit;
}

//$log->Error("getcurrentstat got therm id: $id ");
//$log->Error("getcurrentstat therm ip: ".$stat->uuid);
try
{
   $stat->getStat();
}
catch( Exception $e )
{
    $log->Error("getcurrentstat.php: caught exception from getStat(): ".$e);
    echo "getcurrentstatus.php: getStat() threw an exception:".$e;
    print_status_and_data(3, "getcurrentstatus.php: getStat() threw an exception:".$e);
    exit;
}
$output = "{\"temp\": $stat->temp, \"override\": $stat->override, \"tmode\": $stat->tmode, \"t_heat\": $stat->t_heat,\"t_cool\": $stat->t_cool,\"fmode\": $stat->fmode,\"hold\": $stat->hold, \"fstate\": $stat->fstate, \"tstate\": $stat->tstate, \"day\": $stat->day, \"time\": \"$stat->time\"}";

$log->Info($output);
print_status_and_data(0, $output);
?>
