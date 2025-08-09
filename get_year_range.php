<?php
// Php script to get the earliest and most recent years for which we have hvac details
// Can be used to build drop downs like in the compare tab
require_once( 'common.php' );

$id = (isset($_REQUEST['id'])) ? $_REQUEST['id'] : null;    // Set id to chosen thermost (or null if not chosen)
if( $id == null )
{
    // If the thermostat to display was not chosen, choose one

    $thermostat = array_pop($thermostats);
    if( is_array($thermostat) && isset($thermostat['id']))
    {
        $id = $thermostat['id'];
        $log->logError( "get_year_range.php: Thermostat ID was not chosen, picking one from".$thermostat['id'].": $id" );
    }
}

foreach( $thermostats as $thermostatRec )
{
    $log->logError($id.' against '.$thermostatRec['id']);
    if ($id == $thermostatRec['id'])
    {
        $log->logError("get_year_range.php: got a real new Stat $id");
        $stat = new Stat( $thermostatRec['ip'] );
        break;
    }
}

if( $id == null )
{
    // If there still is not one chosen then abort
    $log->logError( "get_year_range.php: Thermostat ID was NULL!" );
    // Need to redirect output to some image showing user there was an error and suggesting to read the logs.
    return;
}

$minmaxSQL = "SELECT
    MIN(YEAR (end_time)) AS earliest_year,
    MAX(YEAR (end_time)) AS latest_year
FROM {$dbConfig['table_prefix']}hvac_cycles
WHERE tstat_uuid = ?;
";

$queryMinmax = $pdo->prepare( $minmaxSQL );
$result = $queryMinmax->execute( array($stat->uuid) );

// Set a default just in case
$result_json = "{\"earliest_year\": \"1999\", \"latest_year\": \"2000\"}";

if ($result == true)
{
   while ($row = $queryMinmax->fetch(PDO::FETCH_ASSOC))
   {
     // Check to make sure 
     if (!isset($row['earliest_year']) || $row['earliest_year'] == "" || $row['earliest_year'] < 2000 || $row['earliest_year'] > date("Y"))
     {
       $earliest_year = '1999'; // Set a noticable default
     } 
     else 
     { 
       $earliest_year = $row['earliest_year'];
     };

     if (!isset($row['latest_year']) || $row['latest_year'] == "" || $row['latest_year'] < 2000 || $row['latest_year'] > date("Y")) 
     {
       $latest_year = $earliest_year + 1; // Set a reasonable default
     } 
     else 
     { 
       $latest_year = $row['latest_year'];
     };

     $result_json = "{\"earliest_year\": {$earliest_year}, \"latest_year\": {$latest_year}}";
   }
}
echo $result_json;
?>
