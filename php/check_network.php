<?php
# This script is not currently used
session_start();

function check_full_access_network()
{
   global $log;
   global $my_root;

   if (!isset($_SESSION['full_access_ip']) && !isset($_SESSION['isloggedin']))
   {
      // 0 = We weren't logged in to begin with
      return 0;
   }

$log->Error("Current IP: ".$_SERVER['REMOTE_ADDR']." fai: ".(session_value('full_access_ip')?1:0));

   // Double check that we're still on the right network
   if (session_value('full_access_ip') && !cidr_match($_SERVER['REMOTE_ADDR'], $full_access_netmask))
   {
      $log->Error("check_network.php: Was on local network but not any more");
      unset($_SESSION['full_access_ip']);
      unset($_SESSION['isloggedin']);
      unset($_SESSION['login_user']);

      // 2 = We were on the full access network but now we're not
      return 2;
   }
   $log->Error("check_network.php: On full access network");

   // 1 = We're logged in and all is good
   return 1;
}
?>
