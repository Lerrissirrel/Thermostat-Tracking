<?php
require_once '../common.php';
$result = file_get_contents('../tmp_data/History_temps.txt');

if ($result != FALSE)
{
   echo $result;
   echo "\n";
}
else
{
    $log->Error("read_hist_data.php: Failed to open file ../tmp_data/History_temps.txt");
    http_response_code(403);
}

?>
