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

$log->Info("update_thermostats POST dump count: ".count($_REQUEST));
$log->Info("update_thermostats.php REQUEST: ".print_r($_REQUEST, true));

$therm_array = (isset($_REQUEST['therms'])) ? json_decode($_REQUEST['therms']) : null;

$log->Info("therm_array: ".print_r($therm_array, true));

if( $therm_array == null )
{
   $log->Error("update_thermostats.php: Did not get a value array of ids and properties");
   print_status_and_data(1, "Invalid list of thermostats");
   exit;
}

// This is the SQL to update the value for each thermostat.  Note that you can run this section multiple times
// and it will set the display_order to match the alphabetical ordering by thermostat name (starting with 0)

$sql = "UPDATE {$dbConfig['table_prefix']}thermostats SET tstat_uuid = ?, name = ?, description = ?, ip = ?, model = ?, fw_version = ?, wlan_fw_version = ?, enabled = ? WHERE id = ?";
$updateStatInfo = $pdo->prepare( $sql );

// Loop over our known thermostats and use the 'id' to index into the table and update the display_order according to our input
$log->Info("Therm array count: ".count((array)$therm_array));

// Sanity check, just in case, make sure we don't go off the deep end.
$bad_therm_id = 0;

for ($id = 1; $id <= count((array)$therm_array) && $bad_therm_id < 5; $id++)
{
$sql = "UPDATE {$dbConfig['table_prefix']}thermostats SET tstat_uuid = ?, name = ?, description = ?, ip = ?, model = ?, fw_version = ?, wlan_fw_version = ?, enabled = ? WHERE id = ?";
   $updateStatInfo = $pdo->prepare( $sql );

   $log->Info("update_thermostats.php: Checking $id");
   if (isset($therm_array->{$id}) && $therm_array->{$id}->changed == "yes")
   {
      $real_id = $therm_array->{$id}->id;

      $log->Info("update_thermostats.php: Therm ".$real_id."(".$therm_array->{$id}->id.") changed: ".$therm_array->{$id}->changed);

      if (!isset($therm_array->{$id}->changed))
      {
         // something is wrong
         $log->Error("update_thermostats.php: Trying to update an invalid thermostat id: ".$real_id);
         $bad_therm_id++;
         continue;
      }

      try {
         $f_enabled = 0;
         if (isset($therm_array->{$id}->enabled) && $therm_array->{$id}->enabled == true)
         {
            $f_enabled = 1;
         } 
         $log->Info("update_thermostats.php: trying to set therm ".$id." to uuid/name/desc/ip/model/fw_version/wlan_fw_version/enabled/id ".$therm_array->{$id}->uuid." ".$therm_array->{$id}->name." ".$therm_array->{$id}->desc." ".$therm_array->{$id}->ip." ".$therm_array->{$id}->model." ".$therm_array->{$id}->fw_version." ".$therm_array->{$id}->wlan_fw_version." ".$f_enabled." ".$real_id);
 
         // Actually update the db now:
         $status = $updateStatInfo->execute(array( $therm_array->{$id}->uuid, $therm_array->{$id}->name, $therm_array->{$id}->desc, $therm_array->{$id}->ip, $therm_array->{$id}->model, $therm_array->{$id}->fw_version, $therm_array->{$id}->wlan_fw_version, $f_enabled, $real_id));
         $count = $updateStatInfo->rowCount();
 
         if ($status == true)
         {
            // When trying the update but the row doesn't exist, it seems $status is still "true" but the rowCount (modified rows) will be 0.  
            // let's assume that means the row doesn't exist and try to add it.
            if ($count == 0)
            {
              // Maybe this is a new row, let's try to insert it
              $log->Info("update_thermostats.php: trying to insert new therm ".$id." to uuid/name/desc/ip/model/fw_version/wlan_fw_version/enabled/id ".$therm_array->{$id}->uuid." ".$therm_array->{$id}->name." ".$therm_array->{$id}->desc." ".$therm_array->{$id}->ip." ".$therm_array->{$id}->model." ".$therm_array->{$id}->fw_version." ".$therm_array->{$id}->wlan_fw_version." ".$f_enabled." ".$real_id);
              $sql = "INSERT {$dbConfig['table_prefix']}thermostats(tstat_uuid, name, description, ip, model, fw_version, wlan_fw_version, enabled, id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
              $updateStatInfo = $pdo->prepare( $sql );
              $status = $updateStatInfo->execute(array( $therm_array->{$id}->uuid, $therm_array->{$id}->name, $therm_array->{$id}->desc, $therm_array->{$id}->ip, $therm_array->{$id}->model, $therm_array->{$id}->fw_version, $therm_array->{$id}->wlan_fw_version, $f_enabled, $real_id));
              $count = $updateStatInfo->rowCount();
              if ($status == true && $count != 0)
              {
                 $log->Info("update_thermostats.php: Successfully added new thermostat ".$therm_array->{$id}->name."(id: ".$real_id.") : Status ".$status);
              }
              else
              {
                 $log->Error("update_thermostats.php: Failed to update AND failed to add ".$therm_array->{$id}->name."(id: ".$real_id.") : Status ".$status);
                 print_status_and_data(1, "Failed to update AND failed to add a thermostat");
                 exit;
              }
            }
            else
            {
               $log->Info("update_thermostats.php: Successfully updated ".$therm_array->{$id}->name."(id: ".$real_id.") : Status ".$status);
            }
         }
         else
         {
            $log->Error("update_thermostats.php: Failed to update ".$therm_array->{$id}->name."(id: ".$real_id.") : Status ".$status);
            print_status_and_data(1, "Failed to update a thermostat");
            exit;
         }
      }
      catch ( Exception $e )
      {
        $log->Error("update_thermostats.php: failed to execute SQL: ".$sql);
        $log->Error("update_thermostats.php: SQL status: ".$e->errorInfo[1]);
        $log->Error("update_thermostats.php: The exception code is: ". $e->getCode());
        $log->Error("update_thermostats.php: Message: ".$e->getMessage());
        $log->Error("update_thermostats.php: Bailing out!");
        print_status_and_data(2, "Something bad happened, check the log!");
        exit;
      }
   }
}
print_status_and_data(0, "Sucessfully updated the thermostat table");
?>

