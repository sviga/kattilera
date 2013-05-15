<?php
clearstatcache();
if (file_exists("../ini.php"))
	die("installed");

require('install_ftp.class.php');

session_start();
$expiry = 60*60*24*1;
setcookie(session_name(), session_id(), time()+$expiry, "/");

$install = new install_ftp();
if ((isset($_GET['install'])) && $_GET['install'] == 'start')
{
	set_time_limit(0);
	$install->install();
	die;
}
header("Content-Type: text/html; charset=utf-8");
print $install->start();