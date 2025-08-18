<?php

require_once '../common.php';

$result = file_get_contents('../tmp_data/Daily_override.txt');

if ($result != FALSE)
{
   echo $result;
   echo "\n";
}
else
{
    $log->Error("read_daily_data4.php: Failed to open file ../tmp_data/Daily_override.txt");
    http_response_code(403);
}

?>
