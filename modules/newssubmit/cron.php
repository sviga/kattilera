<?php
if (!defined('STDIN'))
    die ("Error: I can only run in command promt");

chdir("../..");

include_once("ini.php");
include_once("include/kernel.class.php"); //Ядро
include_once("include/pub_interface.class.php");
include_once("admin/manager_modules.class.php"); //Менеджер управления модулями
include_once('modules/newssubmit/mysql_submit.php');
include_once('modules/newssubmit/newssubmit.class.php');
include_once('modules/newsi/newsi.class.php');
set_time_limit(0);
$kernel = new kernel(PREFIX);
$mod = new newssubmit('newssubmit1');
$mod->run_submit(true);


?>