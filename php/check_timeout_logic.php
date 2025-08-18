<?php
$sstatus = session_status();
if($sstatus == PHP_SESSION_NONE)
{
    //There is no active session
    session_start();
}

function check_logged_in_and_no_timeout()
{
   global $log;
   global $my_root;

//      $e = new \Exception;
//      $e_str = print_r($e->getTraceAsString(), true);
//      $log->Error($loc_time." cliant stack: ".$e_str);
   if (!isset($_SESSION['full_access_ip']) && !isset($_SESSION['isloggedin']))
   {
      $log->Info("check_timeout_logic.php: not full access IP and not logged in");
      // 0 = We weren't logged in to begin with
      return 0;
   }
$log->Info("Checking timeout: ".session_value('idle_timeout')." vs ".time());
   if (!isset($_SESSION['full_access_ip']) && !isset($_SESSION['notimeout']) && isset($_SESSION['isloggedin']) && 
      session_value('idle_timeout') != null && session_value('idle_timeout') < time())
   {
      // We were logged in but determined we hit a timeout of that login
      $log->Info(" real_user: ".session_value('real_user_logged_in')." fai: ".session_value('full_access_ip')." totimeout: ".session_value('notimeout')." isloggedin: ".session_value('isloggedin')." to: ".session_value('idle_timeout')." time: ".time());
//      $log->Error($info_string);
      $log->Info("check_timeout_logic.php: Detected a login timeout");

      // 2 = We were logged in and we hit a timeout.  Let the caller figure out what to do with it!
      return 2;
   }

   // 1 = We're logged in and all is good
   return 1;
}
?>
