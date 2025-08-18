<?php
/* This php script adds a new column to the "thermostats" table that is used to determine the order the thermostats are listed
   in the drop downs of the various tabs.  Prior, they were listed in alphabetical order.  And, in fact, even after running this
   script, they'll still be shown in the same order.  They order can be changed via the "admin" tab
 */
/* Unfotunately this is all copied from ../common.php with a slight modification to not use "display_order" in the query since that's the thing
   we're adding here, if it doesn't already exist
 */

require_once( '../config.php' );
require_once( '../lib/t_lib.php' );
require_once( '../lib/ExternalWeather.php' );
require_once( '../vendor/autoload.php' );  // This is an external library with original location https://github.com/katzgrau/KLogger

global $timezone;

// Set timezone for all PHP functions
date_default_timezone_set( $timezone );

$pdo = new PDO( "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['db']}", $dbConfig['username'], $dbConfig['password'] );
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

// Set timezone for all MySQL functions
$pdo->exec( "SET time_zone = '$timezone'" );

// Establish connection to log file
// Valid log levels: EMERGENCY ALERT CRITICAL ERROR  WARNING NOTICE INFO DEBUG
$log = new Katzgrau\KLogger\Logger($logDir, Psr\Log\LogLevel::ERROR );

$f_skip_to_next = 0;

// Get list of thermostats in alphabetical order of their names
try
{
        $thermostats = array();
        $sql = "SELECT * FROM {$dbConfig['table_prefix']}thermostats ORDER BY name asc";
        foreach( $pdo->query($sql) as $row )
        {
                        $thermostats[] = $row;
        }
}
catch( Exception $e )
{       // This is a fatal error, should I die()?
        $log->Fatal( 'Error getting thermostat list' );
}


// We'll optimistically try to add the column, and if it's already there we'll catch that fact and exit the script
// Note that this doesn't validate that there are actual VALUES for the thermostats' display_order, just that the 
// column exists.  So if something went wrong and we were able to create the column but not update the values
// running this script a second time won't help.  You would either need to manually edit the table or you could
// try skipping this part that adds the column, and let it continue on to try to update the values

/* We'll be adding the "display_order" column to the thermostats table */
$column_name = 'display_order';

$sql = "ALTER TABLE {$dbConfig['table_prefix']}thermostats 
ADD COLUMN `{$column_name}` TINYINT(3) NULL AFTER `description`";

$updateStatInfo = $pdo->prepare( $sql );

try {
  $status1 = $updateStatInfo->execute();
}
catch( Exception $e )
{
  if ($e->errorInfo[1] == "1060")
  {
    echo "Column ".$column_name." already exists in table, skipping the rest\n";
    $f_skip_to_next = 1;
  }
  else
  {
    echo "SQL status: ".$e->errorInfo[1]."\n";
    echo "The exception code is: " . $e->getCode()."\n\n";
    echo "Message: ".$e->getMessage()."\n";
    echo "Bailing out!\n";
    exit;
  }
}

if ($f_skip_to_next != 1)
{
   if ($status1 == true)
   {
     echo "Successfully added column ".$column_name." to thermostats\n";
   }
   else
   {
     echo "Something went wrong with adding the ".$column_name." column to thermostats.  Status: ".$status1."\n";
     exit;
   }

   // This is the SQL to update the value for each thermostat.  Note that you can run this section multiple times
   // and it will set the display_order to match the alphabetical ordering by thermostat name (starting with 0)
   
   $sql = "UPDATE {$dbConfig['table_prefix']}thermostats SET {$column_name} = ? WHERE id = ?";
   $updateStatInfo = $pdo->prepare( $sql );
   
   $display_order = 0;

   foreach( $thermostats as $thermostatRec )
   {
     try {
        $status = $updateStatInfo->execute(array( $display_order++, $thermostatRec['id']));
        if ($status == true)
        {
           echo "Successfully updated ".$thermostatRec['name']." : Status ".$status;
        }
        else
        {
           echo "Failed to update ".$thermostatRec['name']." : Status ".$status;
        }
     }
     catch ( Exception $e )
     {
       echo "SQL status: ".$e->errorInfo[1]."\n";
       echo "The exception code is: " . $e->getCode()."\n\n";
       echo "Message: ".$e->getMessage()."\n";
       echo "Bailing out!\n";
       exit;
  }
   echo "\n";
   }
}

$f_skip_to_next = 0;

// We'll optimistically try to add the column, and if it's already there we'll catch that fact and exit the script
// Note that this doesn't validate that there are actual VALUES for the thermostats' display_order, just that the
// column exists.  So if something went wrong and we were able to create the column but not update the values
// running this script a second time won't help.  You would either need to manually edit the table or you could
// try skipping this part that adds the column, and let it continue on to try to update the values

/* We'll be adding the "enabled" column to the thermostats table */
$column_name = 'enabled';

$sql = "ALTER TABLE {$dbConfig['table_prefix']}thermostats
ADD COLUMN `{$column_name}` TINYINT(3) NULL AFTER `description`";

$updateStatInfo = $pdo->prepare( $sql );

try {
  $status1 = $updateStatInfo->execute();
}
catch( Exception $e )
{
  if ($e->errorInfo[1] == "1060")
  {
    echo "Column ".$column_name." already exists in table, skipping the rest\n";
    $f_skip_to_next = 1;
  }
  else
  {
    echo "SQL status: ".$e->errorInfo[1]."\n";
    echo "The exception code is: " . $e->getCode()."\n\n";
    echo "Message: ".$e->getMessage()."\n";
    echo "Bailing out!\n";
    exit;
  }
}

if ($f_skip_to_next == 0)
{
   if ($status1 == true)
   {
     echo "Successfully added column ".$column_name." to thermostats\n";
   }
   else
   {
     echo "Something went wrong with adding the ".$column_name." column to thermostats.  Status: ".$status1."\n";
     exit;
   }

   // This is the SQL to update the value for each thermostat.  Note that you can run this section multiple times
   // and it will set the display_order to match the alphabetical ordering by thermostat name (starting with 0)
   
   $sql = "UPDATE {$dbConfig['table_prefix']}thermostats SET {$column_name} = ? WHERE id = ?";
   $updateStatInfo = $pdo->prepare( $sql );
   
   $display_order = 0;
   
   foreach( $thermostats as $thermostatRec )
   {
     try {
        // Assume all thermostats are enabled.  Value is 'yes'.
        $status = $updateStatInfo->execute(array( 1, $thermostatRec['id']));
        if ($status == true)
        {
           echo "Successfully updated ".$thermostatRec['name']." : Status ".$status;
        }
        else
        {
           echo "Failed to update ".$thermostatRec['name']." : Status ".$status;
        }
     }
     catch ( Exception $e )
     {
       echo "SQL status: ".$e->errorInfo[1]."\n";
       echo "The exception code is: " . $e->getCode()."\n\n";
       echo "Message: ".$e->getMessage()."\n";
       echo "Bailing out!\n";
       exit;
     }
   echo "\n";
   }
}

?>

