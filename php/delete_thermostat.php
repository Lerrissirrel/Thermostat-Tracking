<?php
require_once '../common.php';

if(!isset($_SESSION['isloggedin']))
{
  print_status_and_data(99, "Not logged in");
  exit;
}

$log->Info("delete_thermostats POST dump count: ".count($_REQUEST));
$log->Info("delete_thermostats.php REQUEST: ".print_r($_REQUEST, true));

$id = (isset($_REQUEST['id'])) ? json_decode($_REQUEST['id']) : null;

if( $id == null)
{
   $log->Error("delete_thermostats.php: Did not get a valid array of ids and properties");
   print_status_and_data(1, "Did not get a valid array of ids and properties");
   exit;
}

foreach( array_merge($thermostats, $thermostats_disabled) as $thermostatRec )
{
   if ($id == $thermostatRec['id'])
   {
      $log->Info("delete_thermostat.php: got a real Stat ".$id);
      $stat = new Stat( $thermostatRec['ip'] );
      break;
   }
}

if (!isset($stat))
{
   $log->Error("delete_thermostat.php: failed to find a Stat with id ".$id.".  But silently succeed since it was probably a newly added therm that hadn't been saved yet.");
   print_status_and_data(0, '{"response": "failed to find a Stat with id '.$id.'.  But silently succeed since it was probably a newly added therm that hadn\'t been saved yet."}');
   exit;
}

$sql = "DELETE FROM {$dbConfig['table_prefix']}thermostats WHERE id = ?";
$deleteStat = $pdo->prepare( $sql );

$log->Info("delete_thermostats.php: Deleting therm ".$thermostatRec['name']." with ID ".$id);

try 
{
   // Actually update the db now:
   $status = $deleteStat->execute(array($id));
   $count = $deleteStat->rowCount();

   if ($status == true && $count > 0)
   {
      $log->Info("delete_thermostats.php: Successfully deleted thermostat ".$thermostatRec['name']."(id: ".$id.") : Status ".$status);
   }
   else
   {
      $log->Error("delete_thermostats.php: Failed to delete ".$thermostatRec['name']."(id: ".$id.") : Status ".$status);
      print_status_and_data(1, "Failed to delete ".$thermostatRec['name']."(id: ".$id.") : Status ".$status);
      exit;
   }
   print_status_and_data(0, "success!");
   exit;
}
catch ( Exception $e )
{
   $log->Error("delete_thermostats.php: failed to execute SQL: ".$sql);
   $log->Error("delete_thermostats.php: SQL status: ".$e->errorInfo[1]);
   $log->Error("delete_thermostats.php: The exception code is: ". $e->getCode());
   $log->Error("delete_thermostats.php: Message: ".$e->getMessage());
   $log->Error("delete_thermostats.php: Bailing out!");
   print_status_and_data(2, "Failed with exception, see thermo log for details.");
   exit;
}

?>

