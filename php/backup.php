<?php

require_once( '../common.php' );

if(!isset($_SESSION))
{
  session_start();
}
if(!isset($_SESSION['isloggedin']))
{
  print_status_and_data(99, "Not logged in");
  exit;
}

$log->Info("Staring backup");
$return = backupAllTables();
if ($return == 0)
{
   print_status_and_data(0, "Backup Complete");
}
else
{
   print_status_and_data(1, "Backup Failed with status ".$return);
}

$log->Info("Finished backup");

function backupOneTable( $tableName, $now )
{
	global $log;
	global $dbConfig;
	global $rootDir;

        $output_file = "{$rootDir}backup/$now.{$tableName}.sql";

        // First just do the dump so that we can check the status of the mysqldump command itself
	$command = "mysqldump -u {$dbConfig['username']} -p{$dbConfig['password']} -h {$dbConfig['host']} {$dbConfig['db']} {$tableName} > {$output_file}";

	//$command = "mysqldump -u {$dbConfig['username']} -p{$dbConfig['password']} -h {$dbConfig['host']} {$dbConfig['db']} {$tableName} > {$rootDir}backup/$now.{$tableName}.sql";

	// Be careful, this log command writes your DB password!
//	$log->Error( "backup: backupOneTable: Trying backup using\n" . $command );
	// Be careful, this log command writes your DB password!

	// Maybe need a try/catch around this?
	$rv = exec( $command, $output, $return);

        // $rv is from the output of the command, so isn't helpful to check completion unless you have something to parse.  Here we redirected the output so definitely
        // nothing to parse
	if($return != 0)
	{
		$log->Error( "backup: backupOneTable: mysqldump failed with rv *$rv* and return of *$return*." );
	}
        else
        {
            // Second, if the mysqldump succeeded then we want to compress the file
	    $command = "gzip -9 {$output_file}";
	    $rv = exec( $command, $output, $return);

	    if($return != 0)
            {
		$log->Error( "backup: backupOneTable: gzip failed with rv *$rv* and return of *$return*." );
            }
        }
/* Technically works, but is ugly (not like tar)
	// Concatenate the .sql to the gzip
	$command = "gzip -c {$rootDir}backup/$now.{$tableName}.sql >> {$rootDir}backup/{$dbConfig['table_prefix']}.$now.gz";
$log->Info( 'backup: backupOneTable: Trying to concatenate with ' . $command );
	$rv = exec( $command );
*/
	return $return;
}

function backupAllTables()
{
	global $log;
	global $dbConfig;
	global $rootDir;
        $return = 0;

	$now = date( 'Y-m-d-H-i', time() );

	$log->Info( "backup: backupAllTables: Backup starting." );
	$tableList = array( 'hvac_cycles', 'hvac_status', 'run_times', 'setpoints', 'temperatures', 'thermostats', 'time_index', 'hold', 'override', 'users', 'user_therm_prefs' );
	foreach(  $tableList as $tableName )
	{
		$log->Info( "backup: backupAllTables: Backup starting for table: (" . $dbConfig['table_prefix'] . $tableName . ")" );
		$return = backupOneTable( $dbConfig['table_prefix'] . $tableName, $now );
                if ($return != 0)
                {
                   return $return;
                }
	}
}
?>
