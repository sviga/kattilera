<?php
class evalmod_install extends install_modules
{
    const id = 'evalmod';

	function install($id_module, $reinstall = false)
	{
		global $kernel;
	    $sql = 'CREATE TABLE `'.$kernel->pub_prefix_get().'_evalmod`
                (
                    `id_modul` varchar(255) NOT NULL ,
                    `text_php` text,
                    PRIMARY KEY  (`id_modul`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8';
	    $kernel->runSQL($sql);
	}

	function uninstall($id_module)
	{
		global $kernel;
        $sql = 'DROP TABLE `'.$kernel->pub_prefix_get().'_evalmod`';
	    $kernel->runSQL($sql);
	}


	function install_children($id_module, $reinstall = false)
	{
	}

	function uninstall_children($id_module)
	{
	}
}



$install = new evalmod_install();

$install->set_name('[#evalmod_modul_base_name#]');

$install->set_id_modul(evalmod_install::id);

$install->set_admin_interface(2);


$install->add_public_metod('pub_eval_code', '[#evalmod_pub_eval_code#]');

$install->add_public_metod('pub_eval_file_set','[#evalmod_eval_file_set#]');
$p = new properties_file();
$p->set_id('including_file');
$p->set_default('modules/evalmod/files/test.php');
$p->set_patch('modules/evalmod/files/');
$p->set_caption('Имя файла');
$p->set_mask('php');
$install->add_public_metod_parametrs('pub_eval_file_set',$p);

$install->module_copy[0]['name'] = 'evalmod_modul_base_name1';
?>
