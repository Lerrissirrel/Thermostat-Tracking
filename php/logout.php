<?php
$skip_timeout_check = true;

require_once '../common.php';
$log->Info("logout.php: should have just skipped timeout check in common.php");

$sstatus = session_status();
if($sstatus == PHP_SESSION_NONE)
{
    //There is no active session
    $log->Error("logout.php: No session when called!");
    session_start();
}

$was_logged_in = isset($_SESSION['isloggedin']);

//if (isset($_SESSION['isloggedin']) || (isset($_SESSION['full_access_ip']) && $_SESSION['full_access_ip']))
if ($was_logged_in)
{
   $log->Error("logout.php: User ".session_value('login_user')." logged out. ".$_SERVER['REMOTE_ADDR']);
}
else
{
   // This can happen if we detect a login timeout during an Ajax call which will then get us here but
   // we will have noticed the timeout and partially handled the logout from common.php
   $log->Info("logout.php: Got here without being logged in to begin with");
}

session_unset();
session_destroy();
setcookie('c_isloggedin', 0, 0, "/");
setcookie('c_notimeout', 0, 0, "/");

if ($was_logged_in)
{
   // Redirect to the login page:
   $log->Info("logout.php: changing location to thermo.php");
   // Can't echo anything or calling header() won't work!
   header('Location: /therm_controlt/thermo.php');
}
?>

