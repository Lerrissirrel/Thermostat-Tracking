<?php
require_once '../common.php';

if(!isset($_SESSION['isloggedin']))
{
  print_status_and_data(99, "Not logged in");
  exit;
}

$therms = array();
$arch = @exec( 'uname -i' );

if (!is_executable('./find_therms.'.$arch))
{
   $log->Error("find_therms.php: php/find_therms.".$arch." is unavailable or not executable.\n    System architecture ".$arch." may not be supported.\n    Try compiling lib/find_therms.c and moving it to php/find_therms.<arch> where <arch> is the output of 'uname -i'");
   print_status_and_data(1, "php/find_therms.".$arch." is unavailable or not executable.  See therm logs for details");
   exit;
}

exec( './find_therms.'.$arch.' --quiet', $therms, $return_var);

if ($return_var != 0)
{
   print_status_and_data(1, "Failed to run executable to find thermostats, status: ".$return_var);
   exit;
}

$counter = 0;
$therms_out = array();

// exec() conveniently puts the output in an array for us, so just loop over it and extract the IP addresses
for ($i = 0; $i < count($therms); $i++)
{
   if ($therms[$i] != "")
   {
      $url_array = parse_url(substr($therms[$i], 1));
      array_push( $therms_out, $url_array['host']);
   }
}
print_status_and_data(0, json_encode($therms_out));
?>
