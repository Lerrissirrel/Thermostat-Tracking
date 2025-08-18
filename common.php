<?php
/** Location for code that is common to all pages in the project.
 *
 * Performs database connection and log in based on credentials stored in config.php
*/

require_once( 'config/config.php' );
global $my_root;
global $timezone;
global $skip_timeout_check;

require_once( $my_root.'/lib/t_lib.php' );
require_once( $my_root.'/lib/ExternalWeather.php' );
// This is to load php composer libraries.  Currently only https://github.com/katzgrau/KLogger
require_once( $my_root.'/vendor/autoload.php' );  
require_once( $my_root.'/php/check_timeout_logic.php' );

// I'd love to say I fully understand session management, but I don't.
// The idea here is to open the session if it isn't alreayd open, and don't if there's one open.
// Then we do a "write close" since keeping it open for writes will serialize various pieces of code
// that have the session open (for writes).  For instance, the initial load of the page kicks off all
// tabs in parallel.  If we don't close the session for writes, much of it ends up serialized for no
// reason since we don't need to write to the session
$sstatus = session_status();
if($sstatus == PHP_SESSION_NONE)
{
    //There is no active session
    session_start();

    // Closing the writing of the session prevents parallel operations from blocking, but obviously you can't write to it any more.
    // Fortunately you can still read $_SESSION[] after this
    session_write_close();
}

// Set timezone for all PHP functions
date_default_timezone_set( $timezone );

// Set timezone for all MySQL functions
$pdo = new PDO( "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['db']}", $dbConfig['username'], $dbConfig['password'] );
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$pdo->exec( "SET time_zone = '$timezone'" );

// Establish connection to log file
$log_string = 'Psr\\Log\\LogLevel::'.$log_level;
$log = new Katzgrau\KLogger\Logger($logDir, constant($log_string));

// A bunch of login/logout timeout logic:

// If we were told to skip the timeout logic (say "no auto logout" is set), skip it all
// This should probably go inside the check_logged_in_and_no_timeout() routine itself
if (isset($skip_timeout_check) && $skip_timeout_check == true)
{
   $log->Info('common.php: Skipping timeout check: '.$skip_timeout_check);
}
else
{
   $result = check_logged_in_and_no_timeout();
   // 1 = logged in
   if ($result != 1)
   {
      $log->Info("common.php: checked timeout and we're not logged in: ".$result);
      // We timed out or are otherwise not logged in.  2 = timed out
      if ($result == 2)
      {
         require_once( $my_root.'/php/logout_logic.php' );
      }
   }

   // If we're still logged in, give ourselves some more time (but never more than $login_timeout worth)
   // real_user_logged_in differentiates between someone on the local network vs a real user login
   if (session_value('real_user_logged_in') && session_value('isloggedin') && !session_value('notimeout') && 
       (session_value('idle_timeout') - time()) < $login_timeout)
   {
      // We have one case where we DO want to update the session, so we re-open (and then re-close) the session
      session_start();
      $_SESSION['idle_timeout'] += $login_timeout;
      session_write_close();
      $log->Info('Added '.$login_timeout."s to the timeout.  New timeout at: ".session_value('idle_timeout'));
   }

}

// Reset for the next time common.php is included
$skip_timeout_check = false;

// Get list of thermostats
// TODO: Move this to after user logs in future and get only stats for the selected user?
// TODO: Get two lists.  $allThermostats and $userThermostats.  "all" is for those scripts collecting data.  "user" is for user looking at instant status and charts.

// Build two lists: thermostats[] which includes all enabled stats, and thermostats_disabled[] which includes any that have been
// disabled in the Admin tab
try
{
   $thermostats = array();
   $thermostats_disabled = array();
   $sql = "SELECT * FROM {$dbConfig['table_prefix']}thermostats ORDER BY display_order asc";
   foreach( $pdo->query($sql) as $row )
   {
      if ($row['enabled'] == 1)
      {
         $thermostats[] = $row;
      }
      else
      {
         $thermostats_disabled[] = $row;
      }
   }
}
catch( Exception $e )
{	
   // This is a fatal error, should I die()?  Presumably any downstream code will just find an empty stat list and handle it
   $log->Fatal( 'common.php: Error getting thermostat list' );
}

// Some miscellaneous functions we want avilable to use in various places

// Checks to see if an IP address is within the specified network mask - IPV4 only!
function cidr_match($ip, $range)
{
   if ($ip == "")
   {
      return 1;
   }

   list ($subnet, $bits) = explode('/', $range);
   if ($bits === null) {
       $bits = 32;
   }
   $ip = ip2long($ip);
   $subnet = ip2long($subnet);
   $mask = -1 << (32 - $bits);
   $subnet &= $mask; # nb: in case the supplied subnet wasn't correctly aligned
   return ($ip & $mask) == $subnet;
}

// Trying to simplify life so that we don't have to check if a variable exists in the SESSION every time we access one to avoid
// getting warnings when they don't exist
function session_value($ses)
{
   if (isset($_SESSION[$ses]))
   {
      return $_SESSION[$ses];
   }
   else
   {
      return null;
   }
}

// Used to return a logical status and return data (as a string, but could be whatever format the caller is 
// expecting like json) from an AJAX call (like most of the scrips in php/)
function print_status_and_data($status, $data)
{
   global $log;
   $good_data = $data;

   // Just to get the error code, if any
   json_decode($data);

   // If it doesn't look like the we got json data (like if it's just a textual error message) generate basic JSON to send back
   if (json_last_error() != JSON_ERROR_NONE)
   {
      $good_data = '{"response": "'.$data.'"}';
   }
   $ret_data =  '{ "status": "'.$status.'", "data": '.$good_data.'}';
   $log->Info("common.php: print_status_and_data: ".$ret_data);
   echo $ret_data;
}
?>

