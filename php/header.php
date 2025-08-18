<?php
/**
* This is a separate file because I *think* it's where all the user ID code will go.
*
*/

//Things that show in the common header
$sstatus = session_status();
if($sstatus == PHP_SESSION_NONE)
{
    //There is no active session
    session_start();
}

if (session_value('real_user_logged_in') != TRUE && isset($full_access_netmask) && cidr_match($_SERVER['REMOTE_ADDR'], $full_access_netmask))
{
   $log->Info("header.php: On local network: ".$_SERVER['REMOTE_ADDR']." matches ".$full_access_netmask);
   $_SESSION['full_access_ip'] = 1;
   $_SESSION['isloggedin'] = TRUE;
   $_SESSION['login_user'] = $full_access_name;
   unset($_SESSION['idle_timeout']);
   setcookie('c_isloggedin', 1, 0, '/');
}
else
{
   // Double check that we're still on the right network
   if (session_value('full_access_ip') && !cidr_match($_SERVER['REMOTE_ADDR'], $full_access_netmask))
   {
      $log->Warning("header.php: Was on local network but not any more: ".$_SERVER['REMOTE_ADDR']);
      unset($_SESSION['full_access_ip']);
      unset($_SESSION['isloggedin']);
      unset($_SESSION['login_user']);
   }
}

$htmlString = '';
if(session_value('isloggedin') && session_value('real_user_logged_in') == TRUE)
{	
   // If the user is logged in show one thing
   $htmlString = "<span style='margin-right: 1em'>Welcome " . session_value('login_user') . "</span>";
   if(session_value('full_access_ip') != TRUE)
   {
      $htmlString .= "<input type='button' style='margin-right: 1vw;' value='Logout' onClick='javascript: update(\"logout\");'>";
   }
}
else
{
   // If the user is logged out, show them a different thing

   // If they are logged out and yet the action was to log in, then they tried and failed.  Tell them they messed up.
   $warningString = '';
   $warningString = '<span style="flex: 1; width: 25ch;"></span> ';
   if (session_value('failed_login'))
   {
      $warningString = '<span style="font-weight:bold; margin-left: 1em; flex: 1; width: 38ch; color: darkred; display: inline-flex; align-items: center;">Incorrect username and/or password.</span> ';
      unset($_SESSION['failed_login']);
   }
   if (session_value('isloggedin') && session_value('full_access_ip'))
   {
      $warningString = '<span style="font-weight:bold; margin-left: 1em; flex: 1; width: 38ch; display: inline-flex; align-items: center;">Welcome '.$full_access_name.'!</span> ';
   }

   $htmlString .= "<form autocapitalize=none action='php/authenticate.php' style='display: flex; width: 100%; ' method='post'>" .
                   $warningString .
                   "<span style='flex: 1;'>Username <input type='text' name='username' ></span>" .
                   "<span style='margin-left: 1vw;' '>Password <input style='flex: 1' type='password' name='password' ></span>";

   if (isset($allow_no_auto_logout) && $allow_no_auto_logout == true)
   {
      $htmlString .= "<span style='flex: 1'>Disable auto-logout <input type='checkbox' name='remember'></span>";
   }
   else
   {
      $htmlString .= "<span style='flex: 1'> </span>";
   }
   $htmlString .= "<input style='margin-left: 1em; margin-right: 1em;' type='submit' value='Login'>" .
//							 "<input type='button' onClick='javascript: location.href=\"#register\";' value='Register'>" .
	          "</form>";
}

echo $htmlString;
?>
