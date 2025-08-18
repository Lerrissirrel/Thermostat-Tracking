<?php
require_once '../common.php';

if(!isset($_SESSION))
{
    session_start();
}
if(!isset($_SESSION['isloggedin']))
{
  print_status_and_data(99, "Illegal - not logged in");
  exit;
}
else
{
   $log->Info("test_therm session: ".print_r($_SESSION, true));
}

$log->Info("test_thermostats POST dump count: ".count($_REQUEST));
$log->Info("test_thermostats.php REQUEST: ".print_r($_REQUEST, true));

$therm_array = (isset($_REQUEST['therm'])) ? json_decode($_REQUEST['therm']) : null;

$log->Info("therm_array: ".print_r($therm_array, true));

if( $therm_array == null)
{
   $log->Error("update_thermostats.php: Did not get a value array of ids and properties");
   print_status_and_data(1, "Invalid information passed in array of ids and properties");
   exit;
}

$stat = new Stat( $therm_array->ip );

if ($stat == null)
{
   $log->Error("test_thermostats.php: Therm ".$therm_array['id']." couldn't create Stat structure");
   print_status_and_data(1, "caught failure to create stat structure from ".$therm_array['id']);
   exit;
}


try
{
   $stat->getSysName();
}
catch( Exception $e )
{
   $log->Error("test_thermostat.php: caught exception from getSysName(): ".$e);
   print_status_and_data(1, "caught exception from getSysName()");
   exit;
}

try
{
  $stat->getSysInfo();
}
catch ( Exception $e )
{
   $log->Error("test_thermostat.php: caught exception from getSysInfo(): ".$e);
   print_status_and_data(1, "caught exception from getSysInfo()");
   exit;
}

try
{
  $stat->getModel();
}
catch ( Exception $e )
{
    $log->Error("test_thermostat.php: caught exception from getModel(): ".$e);
    print_status_and_data(1, "caught exception from getModel()");
    exit;
}

$output = "{\"name\": \"$stat->sysName\", \"model\": \"$stat->model\", \"uuid\": \"$stat->uuid\", \"fw_version\": \"$stat->fw_version\", \"wlan_fw_version\": \"$stat->wlan_fw_version\"}";
//echo $output;
print_status_and_data(0, $output);
exit;
?>

