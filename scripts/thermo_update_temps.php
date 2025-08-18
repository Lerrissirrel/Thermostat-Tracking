<?php
$start_time = microtime(true);
$skip_timeout_check = true; // Since the timeout check is embedded in commom.php for convenience, but is irrelevant here, skip it
require(dirname(__FILE__).'/../common.php');
session_write_close();

//date_default_timezone_set( $timezone );
$log->Info(str_pad(getmypid(), 5, " ", STR_PAD_LEFT).' temps: Start.' );

$today = date( 'Y-m-d' );
$yesterday = date( 'Y-m-d', strtotime( 'yesterday' ));

// Get the local time of the system running this script.  Truncate the minutes and seconds to the nearest multiple 
// of 10 minutes since whatever is being used to run this script every half hour might get delayed a bit (like a cron job)
$unixTime = substr_replace(date('Y-m-d H:i:s'), "0:00", 15, 4);

/**
* This script updates the indoor and outdoor temperatures and today's and yesterday total run time for each thermostat.
* Recommended to be run every 30 minutes (No more often than every 10 minutes unless you change the time truncation above)
*/

try
{
   $sql = "SELECT NOW() as now_time, CONCAT( SUBSTR( NOW() , 1, 15 ) , '0:00' ) as magic_time;";
   $queryMySQLServer = $pdo->prepare( $sql );
   $queryMySQLServer->execute();
   $row = $queryMySQLServer->fetch( PDO::FETCH_ASSOC );
}
catch( Exception $e )
{
   $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT).' temps: DB Exception while preparing SQL: ' . $e->getMessage() );
   die();
}

$outdoorTemp = null;     // Default outside temp
$outdoorHumidity = null; // Default outside humidity (in case not working with CT80 or similar unit)

try
{
   $externalWeatherAPI = new ExternalWeather( $weatherConfig );
   $outsideData = $externalWeatherAPI->getOutdoorWeather( $ZIP );
   if (array($outsideData))
   {	
      $outdoorTemp = $outsideData['temp'];
      $outdoorHumidity = $outsideData['humidity'];
   }
   else
   {
      $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT).' temps: External weather failed: ' . $e->getMessage() );
      // Not a fatal error, keep going.
   }	
}
catch( Exception $e )
{
   $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT).' temps: External weather failed: ' . $e->getMessage() );
  // Not a fatal error, keep going.
}

// Now that we have the external weather information, we'll split off children for each thermostat to get their data and
// then update the database

$children = array();

foreach( $thermostats as $thermostatRec )
{
   if(($pid = pcntl_fork()) == 0)
   {
      exit(child_main($thermostatRec));
   }
   else
   {
      $children[] = $pid;
   }
}

foreach($children as $pid)
{
   $pid = pcntl_wait($status);
   if(pcntl_wifexited($status))
   {
      $code = pcntl_wexitstatus($status);
   }
   else
   {
      $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." temps: $pid was unnaturally terminated");
   }
}


$log_string = str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." temps: Execution time for all thermostats was " . (microtime(true) - $start_time) . " seconds.";

if (microtime(true) - $start_time > 60 ) // Log an error if we take more than 60 seconds, because we're going to poll again in one second
{
   $log->Error($log_string);
}
else if (microtime(true) - $start_time > 30 )
{
   $log->Warning($log_string);
}
else
{
   $log->Info($log_string);
}

exit;
/* End */

function child_main($thermostatRec)
{
   global $outdoorTemp;
   global $outdoorHumidity;
   global $unixTime;
   global $today;
   global $yesterday;
   
   global $log;
   global $lockFile;
   global $now;
   global $dbConfig;
   global $timezone;
   
   $start_time = microtime(true);

   $pdo = new PDO( "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['db']}", $dbConfig['username'], $dbConfig['password'] );
   $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

   // Set timezone for all MySQL functions
   $pdo->exec( "SET time_zone = '$timezone'" );

   // Prepare the sql statement for updating the indoor/outdoor temp/humidity
   // Notice that it is a REPLACE INTO because we want it to take precidence over information that may come from the status script
   // at the same timestamp
   $sql = "REPLACE INTO {$dbConfig['table_prefix']}temperatures( tstat_uuid, date, indoor_temp, outdoor_temp, indoor_humidity, outdoor_humidity ) VALUES ( ?, \"$unixTime\", ?, ?, ?, ? )";
   $queryTemp = $pdo->prepare( $sql );

   // Prepare the sql statement for hvac runtimes - probably should change this delete/insert into a "insert or replace"
   $sql = "DELETE FROM {$dbConfig['table_prefix']}run_times WHERE date = ? AND tstat_uuid = ?";
   $queryRunDelete = $pdo->prepare( $sql );

   // Prepare the sql statement to insert (or, essentially, update) an hvac runtime
   $sql = "INSERT INTO {$dbConfig['table_prefix']}run_times( tstat_uuid, date, heat_runtime, cool_runtime ) VALUES ( ?, ?, ?, ? )";
   $queryRunInsert = $pdo->prepare( $sql );

   // Try to open the lock file for this thermostat
   $lockFileName = $lockFile . $thermostatRec['id'];
   $lock = @fopen( $lockFileName, 'w' );
   if( !$lock )
   {
      $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." temps: Could not write to lock file $lockFileName" );
      exit(1);
   }

   // Try every X interval for Y seconds
   $exclusive_lock_time_out = 80;      // Defined in seconds      - how long to try getting the exclusive lock - can/should be
                                       //                           than "status"script since this one only runs every 30 minutes
   $exclusive_lock_try_interval = 100; // Defined in milliseconds - how often to try getting the lock again

   // We count retries, but don't limit ourselves by count, but rather by time
   $exclusive_lock_tries = 1;
   $got_ex_lock = 0;

   while ($exclusive_lock_tries <= ($exclusive_lock_time_out * 1000)/$exclusive_lock_try_interval && $got_ex_lock == 0)
   {
      if( flock( $lock, LOCK_EX | LOCK_NB) )
      {
         $got_ex_lock = 1;
if (1)
{
         try
         {
            // Query thermostat info
            $indoorHumidity = null;
            $stat = new Stat( $thermostatRec['ip'] );

            if( strstr($thermostatRec['model'], 'CT80') !== false )
            { 
               // Get indoor humidity for CT80
               $stat->getHumidity();
               // Actually, won't the humidity come back from the getStat() call if it is available on the thermostat?
            }

            // Fetch and log the indoor and outdoor temperatures for this half-hour increment
            $stat->getStat();

            if( $stat->connectOK == 0 )
            {
               $queryTemp->execute(array( $thermostatRec['tstat_uuid'], $stat->temp, $outdoorTemp, $stat->humidity, $outdoorHumidity ) );
            }
            else
            {
               $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." temps: Error getting temperatures from {$thermostatRec['id']} {$thermostatRec['tstat_uuid']} {$thermostatRec['ip']} {$thermostatRec['name']}.  No data stored." );
            }

            // Fetch and log the run time for yesterday and today
            $stat->getDataLog();

            if( $stat->connectOK == 0 )
            {
               // We just keep updating the current situation for today every time we run this script
               // Remove zero or one rows for today and then insert one row for today.
               $queryRunDelete->execute( array($today, $thermostatRec['tstat_uuid']) );
               // Add new run time record for today
               $queryRunInsert->execute( array($thermostatRec['tstat_uuid'], $today, $stat->runTimeHeat, $stat->runTimeCool) );

               // We update yesterday's total (which won't change once it's yesterday) every time we run this script, too
               // Ought to keep track of when "yesterday" was last updated and if it was any time "today" then skip this!
               // Remove zero or one rows for yesterday and then insert one row for yesterday.
               $queryRunDelete->execute( array($yesterday, $thermostatRec['tstat_uuid']) );
               // Add new run time for yesterday
               $queryRunInsert->execute( array($thermostatRec['tstat_uuid'], $yesterday, $stat->runTimeHeatYesterday, $stat->runTimeCoolYesterday) );
            }
            else
            {
               $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." temps: Error getting run times from {$thermostatRec['id']} {$thermostatRec['tstat_uuid']} {$thermostatRec['ip']} {$thermostatRec['name']}.  No data stored." );
            }

         }
         catch( Exception $e )
         {
            // Does t_lib even throw exceptions?  I don't think it does.
            $log->Info(str_pad(getmypid(), 5, " ", STR_PAD_LEFT).' temps: Thermostat Exception: ' . $e->getMessage() );
         }
}
      }
      else
      {
         usleep($exclusive_lock_try_interval * 1000); // Time between retrie (in ms). Sleep for interval number of usecs.
         $log->Info(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." temps: Couldn't get file lock for thermostat {$thermostatRec['name']} on try {$exclusive_lock_tries}" );
         $exclusive_lock_tries++;
      }
   } // While loop on trying to get exclusive lock

   // Unlock and close the lock file
   if ($lock != 0)
   {
      flock( $lock, LOCK_UN );
   }
   fclose( $lock );

   if ($got_ex_lock == 0)
   {
      $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." temps: Couldn't get file lock for {$thermostatRec['name']} temps after ".$exclusive_lock_time_out." seconds");
   }
   else
   { 
      // Probably too verbose at a "warning" but cover the case where we didn't get the lock on the first try
      if ($exclusive_lock_tries > 1 )
      {
         $log->Warning(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." temps: Took {$exclusive_lock_tries} attempts to get file lock for thermostat {$thermostatRec['name']} temps" );
      }

      // Log something with severity depending on how long we took 
      $log_string = str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." temps: Execution time for thermostat {$thermostatRec['name']} was " . (microtime(true) - $start_time) . " seconds.";
      if (microtime(true) - $start_time > 60 )
      {
         $log->Error($log_string);
      }
      else if (microtime(true) - $start_time > 30 )
      {
         $log->Warning($log_string);
      }
      else
      {
         $log->Info($log_string);
      }
   }
}

?>
