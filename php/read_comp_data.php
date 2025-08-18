<?php
require_once '../common.php';

$result = file_get_contents('../tmp_data/Compare_data.txt');

if ($result != FALSE)
{
   echo $result;
   echo "\n";
}
else
{
    $log->Error("read_comp_data.php: Failed to open file ../tmp_data/Compare_data.txt");
    http_response_code(403);
}
?>
