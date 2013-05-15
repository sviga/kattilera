<?php
class sentinel_install extends install_modules
{
    function install($id_module, $reinstall = false)
    {
        global $kernel;
        $q="CREATE TABLE `".$kernel->pub_prefix_get()."_sentinel_filehashes` (
          `file` varchar(255) CHARACTER SET utf8 NOT NULL,
          `hash` varchar(32) CHARACTER SET utf8 NOT NULL,
          UNIQUE KEY `file` (`file`)
        ) ENGINE=MyISAM";
        $kernel->runSQL($q);
    }

    function uninstall($id_module)
    {
        global $kernel;
        $q="DROP TABLE IF EXISTS `".$kernel->pub_prefix_get()."_sentinel_filehashes`";
        $kernel->runSQL($q);
    }

    function install_children($id_module, $reinstall = false)
    {
    }

    function uninstall_children($id_module)
    {
    }
}

$install = new sentinel_install();
$install->set_name('[#sentinel_base_name#]');
$install->set_id_modul('sentinel');
$install->set_admin_interface(1);


// Емейл для уведомлений
$property = new properties_string();
$property->set_caption('[#sentinel_email4notify#]');
$property->set_default('');
$property->set_id('email4notify');
$install->add_modul_properties($property);

// Емейл отправителя для уведомлений (запуск из крона, нету HTTP_HOST)
$property = new properties_string();
$property->set_caption('[#sentinel_email4notify_from#]');
if (isset($_SERVER["HTTP_HOST"]))
    $def="robot@".preg_replace('~^www\.~i','',$_SERVER["HTTP_HOST"]);
else
    $def='';
$property->set_default($def);
$property->set_id('email4notify_from');
$install->add_modul_properties($property);

