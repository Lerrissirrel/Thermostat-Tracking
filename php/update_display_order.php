<?php
require_once '../common.php';

if(!isset($_SESSION))
{
  session_start();
}
if(!isset($_SESSION['isloggedin']))
{
  print_status_and_data(99, "Not logged in");
  exit;
}

$log->Info("update_display_order POST dump count: ".count($_REQUEST));
$log->Info("update_display_order.php REQUEST: ".print_r($_REQUEST, true));

$therm_array = (isset($_REQUEST['order'])) ? json_decode($_REQUEST['order']) : null;

if( $therm_array == null )
{
   $log->Error("update_display_order.php: Did not get a value array of ids and order");
   print_status_and_data(1, "Internal error: Bad thermostat list");
   exit;
}

// This is the SQL to update the value for each thermostat.  Note that you can run this section multiple times
// and it will set the display_order to match the alphabetical ordering by thermostat name (starting with 0)

$column_name = 'display_order';
$sql = "UPDATE {$dbConfig['table_prefix']}thermostats SET {$column_name} = ? WHERE id = ?";
$updateStatInfo = $pdo->prepare( $sql );

// Loop over our known thermostats and use the 'id' to index into the table and update the display_order according to our input
foreach( $thermostats as $thermostatRec )
{

  $order_val = intval($therm_array[$thermostatRec['id']]);

  // Do some basic sanity checking to make sure the order index is within the range of the number of thermostats we have
  if ($order_val > count($thermostats) || $order_val < 0)
  {
     $log->Error("update_display_order.php: thermostat ".$thermostatRec['id']." was given an invalid order: ".$order_val);
     print_status_and_data(1, "Internal error: Bad value for a thermostats order");
     exit;
  }

  try {
     $log->Info("update_display_order.php: trying to set therm ".$thermostatRec['id']." display at index ".$order_val);
     $status = $updateStatInfo->execute(array( $order_val, $thermostatRec['id']));
     if ($status == true)
     {
        $log->Info("update_display_order.php: Successfully updated ".$thermostatRec['name']." : Status ".$status);
     }
     else
     {
        $log->Error("update_display_order.php: Failed to update ".$thermostatRec['name']." : Status ".$status);
     }
  }
  catch ( Exception $e )
  {
    $log->Error("update_display_order.php: failed to execute SQL: ".$sql);
    $log->Error("update_display_order.php: SQL status: ".$e->errorInfo[1]);
    $log->Error("update_display_order.php: The exception code is: ". $e->getCode());
    $log->Error("update_display_order.php: Message: ".$e->getMessage());
    $log->Error("update_display_order.php: Bailing out!");
    print_status_and_data(1, "Something very bad happened, check the log!");
    exit;
  }
}

print_status_and_data(0, "Successfully updated the display order");
?>

