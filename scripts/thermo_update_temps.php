<?php
$start_time = microtime(true);
require(dirname(__FILE__).'/../common.php');

// set the timezone from the config
date_default_timezone_set( $timezone );
$log->logInfo( 'temps: Start.' );
$today = date( 'Y-m-d' );
$yesterday = date( 'Y-m-d', strtotime( 'yesterday' ));

// Get the local time of the system running this script.  Truncate the minutes and seconds to the nearest multiple of 10 minutes since whatever is being used to run this script every half hour might get delayed a bit (like a cron job)
$unixTime = substr_replace(date('Y-m-d H:i:s'), "0:00", 15, 4);

// This script updates the indoor and outdoor temperatures and today's and yesterday total run time for each thermostat.
try
{
	$sql = "SELECT NOW() as now_time, CONCAT( SUBSTR( NOW() , 1, 15 ) , '0:00' ) as magic_time;";
	$queryMySQLServer = $pdo->prepare( $sql );

	//$sql = "INSERT INTO {$dbConfig['table_prefix']}temperatures( tstat_uuid, date, indoor_temp, outdoor_temp, indoor_humidity, outdoor_humidity ) VALUES ( ?, CONCAT( SUBSTR( NOW() , 1, 15 ) , \"0:00\" ), ?, ?, ?, ? )";
	$sql = "INSERT INTO {$dbConfig['table_prefix']}temperatures( tstat_uuid, date, indoor_temp, outdoor_temp, indoor_humidity, outdoor_humidity ) VALUES ( ?, \"$unixTime\", ?, ?, ?, ? )";
	$queryTemp = $pdo->prepare( $sql );

	$sql = "DELETE FROM {$dbConfig['table_prefix']}run_times WHERE date = ? AND tstat_uuid = ?";
	$queryRunDelete = $pdo->prepare( $sql );

	$sql = "INSERT INTO {$dbConfig['table_prefix']}run_times( tstat_uuid, date, heat_runtime, cool_runtime ) VALUES ( ?, ?, ?, ? )";
	$queryRunInsert = $pdo->prepare( $sql );
}
catch( Exception $e )
{
	$log->logError( 'temps: DB Exception while preparing SQL: ' . $e->getMessage() );
	die();
}

$queryMySQLServer->execute();
$row = $queryMySQLServer->fetch( PDO::FETCH_ASSOC );
//$log->logInfo( "temps: The MySQL server thinks that the magic formatted time is {$row['magic_time']} where unix (on the webserver) thinks it is $unixTime" );


$outdoorTemp = null;						// Default outside temp
$outdoorHumidity = null;				// Default outside humidity (in case not working with CT80 or similar unit)
try
{
	$externalWeatherAPI = new ExternalWeather( $weatherConfig );
	$outsideData = $externalWeatherAPI->getOutdoorWeather( $ZIP );
        if (array($outsideData))
	{	
        $outdoorTemp = $outsideData['temp'];
	$outdoorHumidity = $outsideData['humidity'];
$log->logError( "temps: Outside Weather for {$ZIP}: Temp $outdoorTemp Humidity $outdoorHumidity" );
	}
	else
	{
	$log->logError( 'temps: External weather failed: ' . $e->getMessage() );
	}	
}
catch( Exception $e )
{
	$log->logError( 'temps: External weather failed: ' . $e->getMessage() );
	// Not a fatal error, keep going.
}

foreach( $thermostats as $thermostatRec )
{
	$lockFileName = $lockFile . $thermostatRec['id'];
	$lock = @fopen( $lockFileName, 'w' );
	if( !$lock )
	{
		$log->logError( "temps: Could not write to lock file $lockFileName" );
		continue;
	}

	// Try every X interval for Y seconds
	$exclusive_lock_time_out = 10; // Defined in seconds - how long to try getting the exclusive lock
	$exclusive_lock_try_interval = 100; // Defined in milliseconds - how often to try getting the lock again

        $exclusive_lock_tries = 1;
	$got_ex_lock = 0;

        while ($exclusive_lock_tries <= ($exclusive_lock_time_out * 1000)/$exclusive_lock_try_interval && $got_ex_lock == 0)
        {
	if( flock( $lock, LOCK_EX || LOCK_NB) )
	{
                $got_ex_lock = 1;
		try
		{
			// Query thermostat info
			$indoorHumidity = null;
//$log->logInfo( "temps: Connecting to {$thermostatRec['id']} {$thermostatRec['tstat_uuid']} {$thermostatRec['ip']} {$thermostatRec['name']}" );
			$stat = new Stat( $thermostatRec['ip'] );

			$stat->getSysInfo();	// Get uuid for for insert key (yuck)
			if( $stat->connectOK != 0 )
			{
				$log->logError( "temps: Error getting UUID from {$thermostatRec['id']} {$thermostatRec['tstat_uuid']} {$thermostatRec['ip']} {$thermostatRec['name']}.  Aborting communication with this unit." );
				continue;
			}

			$stat->getModel();		// Get model to know if humidity is available
// Instead of asking the thermostat what his model is, should rely upon the entry in the thermostat table?
			if( strstr($stat->model, 'CT80') !== false )
			{ // Get indoor humidity for CT80
				$stat->getHumidity();
// Actually, won't the humidity come back from the getStat() call if it is available on the thermostat?
			}

			// Fetch and log the indoor and outdoor temperatures for this half-hour increment
			$stat->getStat();
//$log->logInfo( "temps: Back from low level communication I have the error code as ($stat->connectOK)" );
//$log->logInfo( "temps: Back from low level communication I have the temperature as ($stat->temp)" );
//$log->logInfo( "temps: UUID $stat->uuid IT " . $stat->temp . " OT $outdoorTemp IH $stat->humidity OH $outdoorHumidity  at PHP time = " . date("Y-m-d H:i:s") );
			if( $stat->connectOK == 0 )
			{
				$queryTemp->execute(array( $stat->uuid, $stat->temp, $outdoorTemp, $stat->humidity, $outdoorHumidity ) );
			}
			else
			{
				$log->logError( "temps: Error getting temperatures from {$thermostatRec['id']} {$thermostatRec['tstat_uuid']} {$thermostatRec['ip']} {$thermostatRec['name']}.  No data stored." );
			}


			// Fetch and log the run time for yesterday and today
			$stat->getDataLog();
//			$log->logInfo( "temps: Run Time Today - Inserting RTH {$stat->runTimeHeat} RTC {$stat->runTimeCool} U $stat->uuid T $today" );
//			$log->logInfo( "temps: Run Time Yesterday - Inserting RTH {$stat->runTimeHeatYesterday} RTC {$stat->runTimeCoolYesterday} U $stat->uuid T $yesterday" );

			if( $stat->connectOK == 0 )
			{
				$queryRunDelete->execute( array($today, $stat->uuid) );	// Remove zero or one rows for today and then insert one row for today.
				$queryRunInsert->execute( array($stat->uuid, $today, $stat->runTimeHeat, $stat->runTimeCool) );	// Add new run time record for today

// Ought to keep track of when "yesterday" was last updated and if it was any time "today" then skip this!
				$queryRunDelete->execute( array($yesterday, $stat->uuid) );	// Remove zero or one rows for yesterday and then insert one row for yesterday.
				$queryRunInsert->execute( array($stat->uuid, $yesterday, $stat->runTimeHeatYesterday, $stat->runTimeCoolYesterday) );	// Add new run time for yesterday
			}
			else
			{
				$log->logError( "temps: Error getting run times from {$thermostatRec['id']} {$thermostatRec['tstat_uuid']} {$thermostatRec['ip']} {$thermostatRec['name']}.  No data stored." );
			}

		}
		catch( Exception $e )
		{	// Does t_lib even throw exceptions?  I don't think it does.
			$log->logInfo( 'temps: Thermostat Exception: ' . $e->getMessage() );
		}
	}
	else
	{
		usleep($exclusive_lock_try_interval * 1000); // Time between retrie (in ms). Sleep for interval number of usecs.
		$log->logError( "temps: Couldn't get file lock for thermostat {$thermostatRec['name']} on try {$exclusive_lock_tries}" );
		$exclusive_lock_tries++;
	}
        } // While loop on trying to get exclusive lock

	if ($got_ex_lock == 0)
        {
                $log->logError( "temps: Couldn't get file lock for {$thermostatRec['name']} temps" );
        }
        else if ($exclusive_lock_tries > 1 )
        {
                $log->logError( "temps: Took {$exclusive_lock_tries} attempts to get file lock for thermostat {$thermostatRec['name']} temps" );
        }

        if ($lock != 0)
        {
                flock( $lock, LOCK_UN );
        }
        fclose( $lock );

        if (microtime(true) - $start_time > 59 ) // Log an error if we take more than 59 seconds, because we're going to poll again in one second
        {
                $log->logError( "temps: Execution time for thermostat {$thermostatRec['name']} was " . (microtime(true) - $start_time) . " seconds." );
        }
        else if (microtime(true) - $start_time > 30 )
        {
                $log->logWarn( "temps: Execution time for thermostat {$thermostatRec['name']} was " . (microtime(true) - $start_time) . " seconds." );
        }
        else
        {
		$log->logInfo( "temps: End.  Execution time for thermostat {$thermostatRec['name']} was " . (microtime(true) - $start_time) . " seconds." );
        }
}

?>
