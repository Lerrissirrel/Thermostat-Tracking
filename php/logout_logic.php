<?php
$sstatus = session_status();
if($sstatus == PHP_SESSION_NONE)
{
    //There is no active session
    session_start();
}

$log->Error("logout_logic.php: User ".session_value('login_user')." logged out. ".$_SERVER['REMOTE_ADDR']);
session_unset();
session_destroy();
setcookie('c_isloggedin', 0, 0, "/");
setcookie('c_notimeout', 0, 0, "/");
?>

