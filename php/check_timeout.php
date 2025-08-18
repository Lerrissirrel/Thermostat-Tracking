<?php
$skip_timeout_check = true;

require_once '../common.php';

//Things that show in the common header
//if(!isset($_SESSION))

$sstatus = session_status();
if($sstatus == PHP_SESSION_NONE)
{
    //There is no active session
    session_start();
}

require_once $my_root.'/php/check_timeout_logic.php';

// Double check that we're still on the right network
if (session_value('full_access_ip') && !cidr_match($_SERVER['REMOTE_ADDR'], $full_access_netmask))
{
   $log->Error("check_timeout.php: Was on local network but not any more");
   unset($_SESSION['full_access_ip']);
   unset($_SESSION['isloggedin']);
   unset($_SESSION['login_user']);

   // Treat loss of full access network like a timeout
   print_status_and_data(0, "timeout");
   exit;
}
else if (isset($full_access_netmask) && cidr_match($_SERVER['REMOTE_ADDR'], $full_access_netmask) && session_value('real_user_logged_in') != TRUE )
{
  // We're on a full access network and not logged in as someone explicitly things are ok - no timeout possible
  // logged in and all is good
  $log->info("check_timeout.php: We're not logged in but we're connected via a full access IP address - good - ".$_SERVER['REMOTE_ADDR']);
  print_status_and_data(0, "good");
  exit;
}
else
{

$result = check_logged_in_and_no_timeout();

if ($result == 0)
{
  // not loggedin
$log->Info("check_timeout.php: nologin");
  print_status_and_data(0, "nologin");
  exit;
  // Leave the cookie set so that detect this in javascript too (???)
//  setcookie('c_isloggedin', 0, 0, '/');
}
else if ($result == 1)
{
  // logged in and all is good
$log->Info("check_timeout.php: good");
  print_status_and_data(0, "good");
  exit;
}
else 
{
  // 2 == We were logged in but timed out (or somethign went wrong)
$log->Info("check_timeout.php: timeout");
   print_status_and_data(0, "timeout");
   exit;
  // Leave the cookie set so that detect this in javascript too (???)
//  setcookie('c_isloggedin', 0, 0, '/');
}
   print_status_and_data(1, "Should not get here!");
   exit;
}
?>
