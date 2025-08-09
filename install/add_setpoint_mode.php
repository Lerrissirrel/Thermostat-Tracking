<?php
/* This php script adds a new column to the "setpoints" table that tracks the mode for each set point
   This allows us to color the setpoint line in charts to indicate whether it's for heat (red) or cool (blue), otherwise
   it emains black
 */
require_once( '../common.php' );

// We'll optimistically try to add the column, and if it's already there we'll catch that fact and exit the script

/* We'll be adding the "mode" column to the setpoints table */
$column_name = 'mode';

$sql = "ALTER TABLE {$dbConfig['table_prefix']}setpoints
ADD COLUMN `{$column_name}` TINYINT(4) NULL AFTER `set_point`";

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
     echo "Successfully added column ".$column_name." to the setpoints table\n";
   }
   else
   {
     echo "Something went wrong with adding the ".$column_name." column to setpoints table.  Status: ".$status1."\n";
     exit;
   }

}

?>

