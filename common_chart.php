<?php
require_once( 'common.php' );

session_write_close();

// Modify the font path for the GD library.  Must use absolute and not relative paths.
// Should probably go in config.php
$my_fontpath = realpath( 'lib/fonts' );
putenv( 'GDFONTPATH='.$my_fontpath );

// Poor Bobby Tables is broken at the moment since the method of drawing the charts changed to
// place them explicitly in their divs, so Bobby needs some rework.  He's a nice boy, though, so
// we should keep him.
// Replaces chart with anti-hacking graphic (usually when web user has used a mal-formed date string)
function bobby_tables()
{
	$filename = './images/exploits_of_a_mom.png';
	$handle = fopen( $filename, 'r' );
	$contents = fread( $handle, filesize($filename) );
	fclose( $handle );
	echo $contents;
}

function validate_date( $some_date )
{
	$date_pattern = "/[2]{1}[0]{1}[0-9]{2}-[0-9]{2}-[0-9]{2}/";
	if( !preg_match( $date_pattern, $some_date ) || strlen($some_date) != 10)
	{	// I want it to be EXACTLY YYYY-MM-DD
                $log->Error("bogus date: ".$some_date);
//		bobby_tables();
		return false;
	}
	return true;
}

// Common code that should run for EVERY CHART page follows here
$id = (isset($_REQUEST['id'])) ? $_REQUEST['id'] : null;    // Set id to chosen thermost (or null if not chosen)
if( $id == null )
{ 
   // This is dubious.  1) When would it happen and 2) Do we really want to see a random stat's data if it does?
   // If the thermostat to display was not chosen, choose one
   $thermostat = array_pop($thermostats);
   if( is_array($thermostat) && isset($thermostat['id']))
   {
      $id = $thermostat['id'];
      $log->Error("common_chart.php: We picked a random stat (".$id.") because one wasn't set.  Really.");
   }
}

// We STILL have a null stat id??
if( $id == null )
{ 
   // If there still is not one chosen then abort
   $log->Error( 'common_chart.php: Thermostat ID was NULL!' );
   // Need to redirect output to some image showing user there was an error and suggesting to read the logs.
   return;
}

// Having now chosen a thermostat to display, gather information about it.
$sql = "SELECT tstat_uuid, name FROM {$dbConfig['table_prefix']}thermostats WHERE id = ?";
$query = $pdo->prepare( $sql );
$query->execute( array( $id ) );
$thermData = $query->fetchAll();
if( !isset($thermData[0]['tstat_uuid']) || empty($thermData[0]['tstat_uuid']))
{ 
   // If the chosen thermostat is not known to the system then abort
   $log->Error( 'common_chart.php: Requested thermostat ID was not found!' );
   // Need to redirect output to some image showing user there was an error and suggesting to read the logs.
   return;
}
$uuid = $thermData[0]['tstat_uuid'];
$statName = $thermData[0]['name'];
$log->Info("Common_chart.php: id: ".$id." uuid: ".$uuid." named: ".$statName);
?>
