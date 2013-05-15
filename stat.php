<?php
//Здесь происходит работа только с BackOffice-м.
//Этот скрипт подключается только в том случае, если у посетителя сайта включен JS
//После этого он апдейтит статистку для этого IP, помечая, что этот IP принадлежит человеку.

include ("ini.php"); // Файл с настройками

include ("include/kernel.class.php"); //Ядро
include ("include/pub_interface.class.php");

session_cache_expire(60*60*24*7);
session_start();


$kernel = new kernel(PREFIX);
$IDHost	= isset($_SESSION['IDHost'])?(int)$_SESSION['IDHost']:0;

if ($IDHost)
{
    $sql = "UPDATE ".PREFIX."_stat_host
            SET f_people=1
            WHERE IDHost=".$IDHost.";";

	$kernel->runSQL($sql);
}


?>

