<?php
chdir('../');
$siteRoot = dirname(dirname(__FILE__))."/";
include ($siteRoot."ini.php"); // Файл с настройками
include ($siteRoot."include/kernel.class.php"); //Ядро
include ($siteRoot."include/install_modules.class.php");
include ($siteRoot."include/mysql_table.class.php");
include ($siteRoot."admin/data_tree.class.php");
include ($siteRoot."include/edit_content.class.php");
include ($siteRoot."admin/manager_interface.class.php");    //Управление основным интефейсом
include ($siteRoot."admin/top_menu.class.php");
include ($siteRoot."admin/manager_users.class.php");
include ($siteRoot."admin/manager_modules.class.php");
include ($siteRoot."admin/manager_structue.class.php");
include ($siteRoot."admin/manager_properties_page.class.php");
include ($siteRoot."admin/manager_global_properties.class.php");
include ($siteRoot."admin/manager_stat.class.php");
include ($siteRoot."admin/parser_properties.class.php");
include ($siteRoot."admin/backup.class.php");
include ($siteRoot."admin/manager_chmod.class.php");
include ($siteRoot."include/pub_interface.class.php");

$kernel = new kernel(PREFIX);

if (defined('SSL_CONNECTION') && SSL_CONNECTION && (!isset($_SERVER['HTTPS'])))
    $kernel->pub_redirect_refresh_reload($_SERVER['REQUEST_URI'], SSL_CONNECTION);

if (SHOW_INT_ERRORE_MESSAGE)
    error_reporting(E_ALL);
else
    error_reporting(0);

$expiry = 60*24*7;
ini_set('session.gc_maxlifetime', $expiry);
session_start();
setcookie(session_name(), session_id(), time()+$expiry*60, "/");

$main_interface = new manager_interface();
$main_interface->start();