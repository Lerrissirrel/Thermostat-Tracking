<?php
$g_start_time = microtime(true);
$skip_timeout_check = true; // Since the timeout check is embedded in commom.php for convenience, but is irrelevant here, skip it
require(dirname(__FILE__).'/../common.php');
session_write_close();

/**
	* This script periodically (ideally once a minute) queries each thermostat and writes the status into
	* the hvac_status and possibly temperature and setpoint tables if something has changed. There is just one
        * record in the hvac_status table for each thermostat and it shows the current status of the heat, cool, and fan, 
        * plus the time it saw that those first started.
	*
	* For each run the status is updated but not the start time. Once it goes from off to on, the start_time is updated.
	* When it goes from on to off, an entry is added to hvac_cycles
	* Date is simply the last time the status was updated
	*/

$now = date( 'Y-m-d H:i:00' );

$children = array();

// We're going to spawn a child for each thermostat individually since there's no reason they cannot run in parallel
// Otherwise, if you have enough thermostats, it could easily take longer than 1 minute in total
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
      $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." status: $pid was unnaturally terminated\n");
   }
}

// This is where we come back to after all the children complete.  Give some feedback in the log depending on how well things went
$log_string = str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." status: Execution time for all thermostats was " . (microtime(true) - $g_start_time) . " seconds.";
if (microtime(true) - $g_start_time > 60 ) // Log an error if we take more than 60 seconds, because we're going to poll again in one second
{
   $log->Error($log_string);
}
else if (microtime(true) - $g_start_time > 30 )
{
   $log->Warning($log_string);
}
else
{
   $log->Info($log_string);
}

exit;
/* End */

// This is all the logic we're going to run for each thermostat, in parallel
function child_main($thermostatRec)
{
   global $log;
   global $lockFile;
   global $now;
   global $dbConfig;
   global $timezone;
   $start_time = microtime(true);

   // Maybe it's possible to have one $pdo that all the children share, but I'm not sure
   $pdo = new PDO( "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['db']}", $dbConfig['username'], $dbConfig['password'] );
   $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

   // Set timezone for all MySQL functions
   $pdo->exec( "SET time_zone = '$timezone'" );

   /// Lots of sql statements get used in here!  Set them all up.

   // Query to location info about the thermostat.  Might find nothing if this is the first time.
   $sql = "SELECT * FROM {$dbConfig['table_prefix']}hvac_status WHERE tstat_uuid=?"; // Really should name columns instead of using *
   $getStatInfo = $pdo->prepare( $sql );

   // If this was the first contact, add info about the stat to the DB
   $sql = "INSERT INTO {$dbConfig['table_prefix']}hvac_status( tstat_uuid, date, start_date_heat, start_date_cool, start_date_fan, heat_status, cool_status, fan_status ) VALUES( ?, ?, ?, ?, ?, ?, ?, ? )";
   $insertStatInfo = $pdo->prepare( $sql );

   // Modify the thermostat data
   $sql = "UPDATE {$dbConfig['table_prefix']}thermostats SET tstat_uuid = ?, description = ?, model = ?, fw_version = ?, wlan_fw_version = ? WHERE id = ?";
   $updateStatInfo = $pdo->prepare( $sql );

   // Modify the hvac_status table
   $sql = "UPDATE {$dbConfig['table_prefix']}hvac_status SET date = ?, start_date_heat = ?, start_date_cool = ?, start_date_fan = ?, heat_status = ?, cool_status = ?, fan_status = ? WHERE tstat_uuid = ?";
   $updateStatStatus = $pdo->prepare( $sql );

   // Add new entry into the hvac_cycles table (When we've noticed an on->off change of a cycle)
   $sql = "INSERT INTO {$dbConfig['table_prefix']}hvac_cycles( tstat_uuid, system, start_time, end_time ) VALUES( ?, ?, ?, ? )";
   $cycleInsert = $pdo->prepare( $sql );

   // Query to retrieve prior setpoint.  Might find nothing if this is the first time.
   $sql = "SELECT * FROM {$dbConfig['table_prefix']}setpoints WHERE id=? ORDER BY switch_time DESC LIMIT 1";
   $getPriorSetPoint = $pdo->prepare( $sql );

   // Add a new setpoint change into the setpoint table
   $sql = "INSERT INTO {$dbConfig['table_prefix']}setpoints( id, set_point, mode, switch_time ) VALUES( ?, ?, ?, ? )";
   $insertSetPoint = $pdo->prepare( $sql );

   // Query to retrieve prior override.  Might find nothing if this is the first time.
   $sql = "SELECT * FROM {$dbConfig['table_prefix']}override WHERE id=? ORDER BY start_time DESC LIMIT 1";
   $getPriorOverride = $pdo->prepare( $sql );

   // Add the start of a newly discovered override
   $sql = "INSERT INTO {$dbConfig['table_prefix']}override( id, start_time, end_time ) VALUES( ?, ?, NULL)";
   $insertOverride = $pdo->prepare( $sql );

   // Add the end of a completed override
   $sql = "UPDATE {$dbConfig['table_prefix']}override SET end_time = ? WHERE start_time = ? AND id = ?";
   $updateOverride = $pdo->prepare( $sql );

   // Query to retrieve prior hold.  Might find nothing if this is the first time.
   $sql = "SELECT * FROM {$dbConfig['table_prefix']}hold WHERE id=? ORDER BY start_time DESC LIMIT 1";
   $getPriorHold = $pdo->prepare( $sql );

   // Add the start of a newly discovered hold
   $sql = "INSERT INTO {$dbConfig['table_prefix']}hold( id, start_time, end_time ) VALUES( ?, ?, NULL)";
   $insertHold = $pdo->prepare( $sql );

   // Add the end of a completed hold
   $sql = "UPDATE {$dbConfig['table_prefix']}hold SET end_time = ? WHERE start_time = ? AND id = ?";
   $updateHold = $pdo->prepare( $sql );

   // Query to retrieve prior indor temp/humidity.  Might find nothing if this is the first time.
   $sql = "SELECT * FROM {$dbConfig['table_prefix']}temperatures WHERE tstat_uuid=? ORDER BY date DESC LIMIT 1";
   $getPriorIndoor = $pdo->prepare( $sql );

   // Add a new temp/humidity line with indoor info only
   $sql = "INSERT INTO {$dbConfig['table_prefix']}temperatures( tstat_uuid, date, indoor_temp, outdoor_temp, indoor_humidity, outdoor_humidity ) VALUES( ?, ?, ?, ?, ?, ?)";
   $insertIndoor = $pdo->prepare( $sql );

   /// Now we're ready to do the real work for a single thermostat.
   //  1) Get the lock file (try to avoid contention at the thermostat which can cause it to act poorly
   //  

   $lockFileName = $lockFile . $thermostatRec['id'];
   $lock = @fopen( $lockFileName, 'w' );

   if( !$lock )
   {
      $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." status: Could not write to lock file $lockFileName" );
      exit(1);
   }

   // Well, we can at least open the file, now let's try to get the lock!

   // Try every X interval for Y seconds
   $exclusive_lock_time_out     = 40;  // In seconds - how long to try getting the exclusive lock.  This + execution time wants to be <60s
   $exclusive_lock_try_interval = 100; // In milliseconds - how often to try getting the lock again

   $exclusive_lock_tries = 1; // Keep track of how many times we've tried as compared to the maximum time
   $got_ex_lock = 0;
   $got_lock_time = 0;

   // This is trying to estimate the elapsed time by how many attempts and how long we wait between attemps
   // It should really be done with an actual time measurement, but it's close enough as it is as NOT getting the lock takes
   // almost no time
   while ($exclusive_lock_tries <= ($exclusive_lock_time_out * 1000)/$exclusive_lock_try_interval && $got_ex_lock == 0)
   {
      if( flock($lock, LOCK_EX | LOCK_NB) )
      {
         $got_ex_lock = 1;
         $got_lock_time = microtime(true);
if (1) // TMPMJH
{
         try
         { 
            // Query thermostat info
            $log->Info(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." status: Connecting to Thermostat ID = ({$thermostatRec['id']})  uuid  = ({$thermostatRec['tstat_uuid']}) ip = ({$thermostatRec['ip']}) name = ({$thermostatRec['name']})" );
            $stat = new Stat( $thermostatRec['ip'] );
   
            /**
            * This catches the uuid which is required for data insert.
            *
            * Really should use a surrogate key (thermostat_id) instead of the uuid for data storage.
            *
            * What do we do when there is a changed thermostat?  The history is tied to the uuid. That is BAD
            * Need a system generated surrogate key instead of uuid to join from thermostat table to data table.
            * Should compare the detected uuid back to the thermostat table record
            * On match, do nothing.  On 'no match', make sure it matches no other record too and then update existing record (and log it)
            */
   
             // Skip all this querying from the thermostat as they're really expensive
             // Note: this breaks auto detection of new thermostats from here, now relying on the Admin tab or manual setup of
             // the database for that.
             // Note 2: NOT checking for matching info from these calls is a bit risky as it assumes that the same thermostat 
             // lives at the same ip address forever.  Maybe just make the getSysName call and check that??
             if (0)
             {
                $stat->getSysInfo();
                if( $stat->connectOK != 0 )
                {
                   $log->Warning(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." status: connectOK is not zero!  We should not proceed!  connectOK = ($stat->connectOK).  Perhaps for a macro level retry even though the micro level retry already failed?" );
                  // An error here may not need to be fatal, but if it worked, should verify that stat uuid matches expected uuid in DB
                  // If it does not match expected, does it match ANY?  Email admin if the user ID for matched does not match user ID of expected!! (possible hacking?)
                }
   
                // Perhaps only check this info one time per day or when the reported uuid is not same as stored uuid
                $stat->getModel();
                if( $stat->connectOK != 0 )
                {
                   // An error here is non-fatal, simply decline to use this info
                   $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." status: Thermostat {$thermostatRec['name']} failed to respond with model number." );
                }
   
                $stat->getSysName();
                if( $stat->connectOK != 0 )
                {
                   // An error here is non-fatal, simply decline to use this info
                   $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." status: Thermostat {$thermostatRec['name']} failed to respond with system info." );
                }
             }
   
             // Get thermostat state (time, temp, mode, hold, override)
   
             $start_time_1 = microtime(true);
             $stat->getStat();
             if( $stat->connectOK == 0 )
             {
                // We've got the current temp, we should save it in the db although probably only want to do it if there is at least 
                // a 1 degree change since the last one to keep the number of DB entries down.  Also need to truncate the seconds off 
                // the time to get to nearest minute (like we round to the nearest 10 minutes for temps in the thermo_update_temps.php script)
                $tmode      = ($stat->tmode);
                $heatStatus = ($stat->tstate == 1) ? true : false;
                $coolStatus = ($stat->tstate == 2) ? true : false;
                $fanStatus  = ($stat->fstate == 1) ? true : false;
   
                // Get current setPoint from thermostat
                // t_heat or t_cool may not exist if thermostat is running in battery mode (will it even talk on WiFi if the power is out?)
                $setPoint = ($stat->tmode == 1) ? $stat->t_heat : $stat->t_cool;
             }
             else
             {
                $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." status: Thermostat {$thermostatRec['name']} failed to respond with present status" );
                // Instead of continue, I should throw a thermostat exception! (And exit since we check easch thermostat in its own thread)
                continue;	// Cannot continue workting on this thermostat, try the next one in the list.
             }
   
             $start_time_2 = microtime(true);
   
             // Get prior Indoor temp from database
             $status = $getPriorIndoor->execute(array($thermostatRec['tstat_uuid']));
             if ($status == true)
             {
                $row = $getPriorIndoor->fetch( PDO::FETCH_ASSOC);
                if (isset($row['indoor_temp']))
                {
                   $priorIndoorTemp = $row['indoor_temp'];
                }
             }
             if (!isset($priorIndoorTemp)) 
             {
                $priorIndoorTemp = 1111; // Use a noticable bad value
             } 
   
             // If the current indoor temp is at least .5 degree away from the last one we saw, store it and the current
             // humidity in the db.  (Note that a changing indoor humidity does not trigger this if the temp doesn't 
             // change - easy enough to do but I don't have a therm that measures humidity!
             // Should probably make the necessary difference in temeratures be a config value
             if ($status != true || abs($priorIndoorTemp - $stat->temp) >= .5)
             {
                $log->Info(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." status: ".$thermostatRec['name']." prior temp: ".$priorIndoorTemp." current temp ".$stat->temp);
                // Need to insert a new row
                $insertIndoor->execute( array( $thermostatRec['tstat_uuid'], $now, $stat->temp, NULL, $stat->humidity, NULL ) );
             }
   
             // Get prior setPoint from database
             $getPriorSetPoint->execute(array($thermostatRec['id']));
             $row = $getPriorSetPoint->fetch( PDO::FETCH_ASSOC);
             $priorSetPoint = $row['set_point'];
   
             // We will also double check that the mode hasn't changed, as we also log the mode with the setpoint
             $priorTmode = $row['mode'];
   
             // 
             // Handle whether there's an "override" in play or not
             // 
   
             $getPriorOverride->execute(array($thermostatRec['id']));
             $status = $getPriorOverride->execute(array($thermostatRec['id']));
   
             if ($status != true)
             {
                // The SQL failed for some reason.  Not sure what to do!
                // So don't do anything with the override this time around
                $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." status: Could not get prior override from DB when working on thermostat {$thermostatRec['name']}");
             }
             else if ($getPriorOverride->rowCount() < 1)
             {
                if ($stat->override != 0)
                {
                   // First time we see an override for this thermostat (I hope) so start a new record
                   $insertOverride->execute( array( $thermostatRec['id'], $now));
                }
                else
                {
                   // We're not currently in an override, so it's ok to not find a record if we
                   // never went into override on this thermostat before
                }
             }
             else // We found a record, check to see if it's still open and if so, see if we should close it
             {
                $row = $getPriorOverride->fetch( PDO::FETCH_ASSOC);
                $priorOverride = 0; // Assume there's no override
   
                // If we found a row and it has a start_time but no end_time we're in the middle of an override
                if ($row['start_time'] != NULL && $row['end_time'] == NULL)
                {
                   if ($stat->override != 0)
                   {
                      // We're still in the override, nothing to do
                   }
                   else
                   {
                      // We were in an override but aren't any more, update the table with the end time
                      $updateOverride->execute( array( $now, $row['start_time'], $thermostatRec['id']));
                   }
                }
                else if ($row['start_time'] != NULL && $row['end_time'] != NULL)
                {
                   // We found a record but it is not open.  If override is on right now, we need to start a new record
                   if ($stat->override != 0)
                   {
                      // We just noticed the start of a new override
                      $insertOverride->execute( array( $thermostatRec['id'], $now));
                   }
                   else
                   {
                      // We found a record that wasn't open but we're not currently in "override" so nothing to do
                   }
                }
                else
                {
                   // Should never get here, it means we found a record but the start time was NULL
                }
             }
   
             // 
             // Handle whether there's an "hold" in play or not
             // 
   
             $getPriorHold->execute(array($thermostatRec['id']));
             $status = $getPriorHold->execute(array($thermostatRec['id']));
             if ($status != true)
             { 
                // The SQL failed for some reason.  Not sure what to do!
                // So don't do anything with the hold this time around
                $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." status: Could not get prior hold from DB when working on thermostat {$thermostatRec['name']}");
             }
             else if ($getPriorHold->rowCount() < 1)
             {
                if ($stat->hold != 0)
                {
                   // First time we see a hold for this thermostat (I hope) so start a new record
                   $insertHold->execute( array( $thermostatRec['id'], $now));
                }
                else
                {
                   // We're not currently in a hold, so it's ok to not find a record if we
                   // never went into hold on this thermostat before
                }
             }
             else // We found a record, check to see if it's still open and if so, see if we should close it
             {
                $row = $getPriorHold->fetch( PDO::FETCH_ASSOC);
                $priorHold = 0; // Assume there's no hold
   
                // If we found a row and it has a start_time but no end_time we're in the middle of a hold
                if ($row['start_time'] != NULL && $row['end_time'] == NULL)
                {
                   if ($stat->hold != 0)
                   {
                      // We're still in the hold, nothing to do
                   }
                   else
                   {
                      // We were in a hold but aren't any more, update the table with the end time
                      $updateHold->execute( array( $now, $row['start_time'], $thermostatRec['id']));
                   }
                } 
                else if ($row['start_time'] != NULL && $row['end_time'] != NULL)
                {
                   // We found a record but is not open.  If hold is on right now, we need to start a new record
                   if ($stat->hold != 0)
                   {
                      // We just noticed the start of a new hold
                      $insertHold->execute( array( $thermostatRec['id'], $now));
                   }
                   else
                   {
                      // We found a record that wasn't open but we're not currently in "hold" so nothing to do
                   }
                }
                else
                {
                   // Should never get here, it means we found a record but the start time was NULL
                }
             }
   
             //
             // Get prior hvac status state info from DB
             //
   
             $priorStartDateHeat = null;
             $priorStartDateCool = null;
             $priorStartDateFan = null;
             $priorHeatStatus = false;
             $priorCoolStatus = false;
             $priorFanStatus = false;
   
             // Look up thermostat previous status based on the uuid (uuid as expected from DB).
             // This assumes that the IP address and uuid of a thermostat don't ever change.  We really shouldn't
             // assume that, but we do for now
             $getStatInfo->execute( array( $thermostatRec['tstat_uuid'] ) );
   
             $start_time_3 = microtime(true);
   
             if( $getStatInfo->rowCount() < 1 )
             { 
                // No prior hvac information found for this thermostat - hopefully that means it's a relatively new one!
                $log->Warn(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)."status: I think I've never found any hvac activity for this thermostat before! Uuid: ".$thermostatRec['tstat_uuid']);
   
               $startDateHeat = ($heatStatus) ? $now : null;
               $startDateCool = ($coolStatus) ? $now : null;
               $startDateFan  = ($fanStatus) ? $now : null;
   
               $log->Info(str_pad(getmypid(), 5, " ", STR_PAD_LEFT). " status: Inserting record for a brand new never before seen thermostat with time = ($now) H $heatStatus C $coolStatus F $fanStatus SDH $startDateHeat SDC $startDateCool SDF $startDateFan for UUID $stat->uuid" );
   
               $insertStatInfo->execute( array( $thermostatRec['tstat_uuid'], $now, $startDateHeat, $startDateCool, $startDateFan, $heatStatus, $coolStatus, $fanStatus ) );
            }
            else
            {
               // We found an entry for our thermostat, get the prior situation
               while( $row = $getStatInfo->fetch( PDO::FETCH_ASSOC ) )
               { 
                  // This SQL had _BETTER_ pull only one row or else there is a data integrity problem!
                  // and without an ORDER BY on the SELECT there is no way to know you're geting the same row from this each time
                  $priorStartDateHeat = $row['start_date_heat'];
                  $priorStartDateCool = $row['start_date_cool'];
                  $priorStartDateFan = $row['start_date_fan'];
                  $priorHeatStatus = (bool)$row['heat_status'];
                  $priorCoolStatus = (bool)$row['cool_status'];
                  $priorFanStatus = (bool)$row['fan_status'];
               }
   
               // update start dates if the cycle just started
               $newStartDateHeat = (!$priorHeatStatus && $heatStatus) ? $now : $priorStartDateHeat;
               $newStartDateCool = (!$priorCoolStatus && $coolStatus) ? $now : $priorStartDateCool;
               $newStartDateFan = (!$priorFanStatus && $fanStatus) ? $now : $priorStartDateFan;
   
               // if status has changed from on to off, update hvac_cycles in the DB for heat, cool and fan, respectively
               if( $priorHeatStatus && !$heatStatus )
               {
                  $cycleInsert->execute( array( $thermostatRec['tstat_uuid'], 1, $priorStartDateHeat, $now ) );
                  $newStartDateHeat = null;
               }
   
               if( $priorCoolStatus && !$coolStatus )
               {
                  $cycleInsert->execute( array( $thermostatRec['tstat_uuid'], 2, $priorStartDateCool, $now ) );
                  $newStartDateCool = null;
               }
   
               if( $priorFanStatus && !$fanStatus )
               {
                  $cycleInsert->execute( array( $thermostatRec['tstat_uuid'], 3, $priorStartDateFan, $now ) );
                  $newStartDateFan = null;
               }
   
               // update the status table in the DB
               $updateStatStatus->execute( array( $now, $newStartDateHeat, $newStartDateCool, $newStartDateFan, $heatStatus, $coolStatus, $fanStatus, $thermostatRec['tstat_uuid'] ) );
   
               $start_time_4 = microtime(true);
   
               //Update the setpoints table
               if( $setPoint != $priorSetPoint || $tmode != $priorTmode)
               {
                  $log->Info(str_pad(getmypid(), 5, " ", STR_PAD_LEFT). " status: Inserting changed setpoint record SP=$setPoint, old=($priorSetPoint), time=($now) " );
                  $insertSetPoint->execute( array( $thermostatRec['id'], $setPoint, $tmode, $now ) );
               }
            }
         }
         catch( Exception $e )
         {
            $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT). ' status: Thermostat Exception ' . $e->getMessage() );
         }
}
   
         $start_time_5 = microtime(true);
      }
      else
      {
         // We didn't get the exclusive lock, wait and try again
         $exclusive_lock_tries++;
         usleep($exclusive_lock_try_interval * 1000); // Time between retrie (in ms). Sleep for interval number of usecs.
      }
   }

   if ($got_ex_lock == 0)
   {
      $log->Error( str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." status: Couldn't get file lock for thermostat {$thermostatRec['name']} after ".$exclusive_lock_time_out." seconds" );
   }
   else if ($exclusive_lock_tries > 1 )
   {
      $log->Warning( str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." status: Took {$exclusive_lock_tries} attempts to get file lock for thermostat {$thermostatRec['name']}" );
   }

   // Release our lock
   if ($lock != 0)
   {
      flock( $lock, LOCK_UN );
   }

   // Close the lock file
   fclose( $lock );

   if ($got_ex_lock != 0)
   {
      $lock_string = str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." status: Execution time for thermostat {$thermostatRec['name']} was " . (microtime(true) - $start_time) . " seconds.  Got lock in: ".$got_lock_time - $start_time." 1: ".$start_time_1-$start_time." 2: ".$start_time_2-$start_time." 3: ".$start_time_3-$start_time." 4: ".$start_time_4-$start_time." 4: ".$start_time_5-$start_time;

      // Log an error if we take more than 59 seconds, because we're going to poll again in one second
      if ($got_lock_time - $start_time > 60 )
      {
         $log->Error($lock_string);
      }
      else if ($got_lock_time - $start_time > 30 )
      {
         $log->Warning($lock_string);
      }
      else if ($got_lock_time - $start_time > 10 )
      {
         $log->Info($lock_string);
      }
      else
      {
         $log->Info(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." status: End.  Execution time for thermostat {$thermostatRec['name']} was " . (microtime(true) - $start_time) . " seconds." );
      }
   }
}
?>

