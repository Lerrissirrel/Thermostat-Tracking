<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once '../common.php';

$sstatus = session_status();
if($sstatus == PHP_SESSION_NONE)
{
    //There is no active session
    session_start();
}

// Now we check if the data from the login form was submitted, isset() will check if the data exists.
if ( !isset($_POST['username'], $_POST['password']) ) 
{
        $_SESSION['failed_login'] = TRUE;
        $log->Error("authenticate.php: User and/or password not set, someone called directly?");
	// Could not get the data that should have been sent.
}
else
{
   if (extension_loaded('pam')) 
   {
       if ($_POST['username'] == '' || $_POST['password'] == '' || !pam_auth($_POST['username'], $_POST['password'], $error)) 
       {
           $_SESSION['failed_login'] = TRUE;
           if ($_POST['username'] != '' && $_POST['password'] != '')
           {
              $pass = $_POST['password'];
              if ($_POST['username'] == $admin_account && $admin_account != "")
              {
                 $pass = "redacted";
              }
              $log->Error("authenticate.php: Failed login :".$_POST['username'].": Password :".$pass.": auth response: ".$error);
           }
       }
       else 
       {
          // Success!  We're logged in
          session_regenerate_id();
          $_SESSION['isloggedin'] = TRUE;
          $_SESSION['real_user_logged_in'] = TRUE;
          unset($_SESSION['full_access_ip']);

          setcookie('c_isloggedin', 1, 0, '/');
          $_SESSION['login_user'] = $_POST['username'];
          $log_string = "User: ".$_POST['username']." has logged in";
          if ($_SESSION['login_user'] == $admin_account && $admin_account != "")
          {
              $log_string .= " and is the admin";
              $_SESSION['isadmin'] = TRUE;
          }
          if (isset($allow_no_auto_logout) && $allow_no_auto_logout == true && isset($_POST['remember']))
          { 
             $log_string .= ". \"Keep me logged in\" is set";
             $log->Info('authenticate.php: remember = '.$_POST['remember']);
             $_SESSION['notimeout'] = TRUE;
             setcookie('c_notimeout', 1, 0, '/');
          }
          else
          {
             $log_string .= ". \"Keep me logged in\" is not set";
             setcookie('c_notimeout', 0, 0, '/');
             // Set the initial timeout value
             $_SESSION['idle_timeout'] = time() + $login_timeout; 
             unset($_SESSION['forced_logout']);
             unset($_SESSION['notimeout']);
             unset($_SESSION['full_access_ip']);
          }
          $log->Error($log_string.". ".$_SERVER['REMOTE_ADDR']);
          $log->Info("authenticate.php: Successful login :".$_POST['username'].": auth response: ".$error);
       }
   }
   else 
   {
       $log->Error("authenticate.php: pam.so not loaded");
   }
}
header('Location: /therm_controlt/thermo.php');

?>
